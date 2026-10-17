import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/fee_gate_mark_seen');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

const summary = {
    total: 0,
    passed: 0,
    failed: 0,
    tests: [],
};

function recordResult(name, pass, details = '') {
    summary.total++;
    if (pass) {
        summary.passed++;
        console.log(`  [PASS] ${name} ${details ? '(' + details + ')' : ''}`);
    } else {
        summary.failed++;
        console.error(`  [FAIL] ${name} ${details ? '(' + details + ')' : ''}`);
    }
    summary.tests.push({ name, pass, details });
}

function runPhpHelper(action, param = '') {
    const output = execSync(`php playwright/e2e_test_helper.php ${action} ${param}`, { encoding: 'utf-8' });
    return JSON.parse(output.trim());
}

async function runFeeGateMarkSeenSuite() {
    console.log('======================================================================');
    console.log('  DXCLINIC — FEE GATE + LIVE QUEUE MARK SEEN E2E TEST SUITE           ');
    console.log('  UNPAID BLOCKS MARK SEEN -> PAYMENT ENABLES MARK SEEN -> IN_PROGRESS ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 }
    });

    const page = await context.newPage();

    try {
        // Step 1: Seed unpaid appointment
        console.log('[STEP 1] Seeding Unpaid Appointment with Token 777');
        const seedData = runPhpHelper('seed_fee_gate_appointment', 'democlinic');
        recordResult('Seed Unpaid Appointment', seedData.success, `ID: ${seedData.appointment_id}, Token: ${seedData.token}`);

        // Step 2: Login as Demo Admin
        console.log('[STEP 2] Authenticating as Admin');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await page.fill('#email', 'demoadmin@dxclinic.local');
        await page.fill('#password', 'DemoAdmin@2026!');
        await page.locator('#btn_submit_login').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        recordResult('Admin Login', page.url().includes('/dashboard'));

        // Step 3: Navigate to Live Queue
        console.log('[STEP 3] Opening Live Queue for democlinic');
        await page.goto(`${BASE_URL}/clinic/democlinic/queue/live`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_live_queue_unpaid.png') });

        // Step 4: Verify Fee Column shows UNPAID and Mark Seen is DISABLED
        console.log('[STEP 4] Verifying UNPAID fee status and disabled Mark Seen');
        const feeStatusLocator = page.locator('#fee_status_777');
        await feeStatusLocator.waitFor({ state: 'visible', timeout: 5000 });
        const feeText = await feeStatusLocator.innerText();
        recordResult('Fee Column Shows UNPAID', feeText.includes('UNPAID'), `Status: ${feeText}`);

        const markSeenBtn = page.locator('#btn_mark_seen_777');
        const isDisabled = await markSeenBtn.isDisabled();
        recordResult('Mark Seen Button Disabled When UNPAID', isDisabled, 'Button has disabled attribute');

        const feeAndPadBtn = page.locator('#btn_fee_pad_777');
        const hasFeePad = await feeAndPadBtn.isVisible();
        recordResult('FEE & PAD Button Visible for Unpaid', hasFeePad);

        // Step 5: Pay the fee via authoritative billing
        console.log('[STEP 5] Settling Consultation Fee (Simulating Fee & Pad completion)');
        const payResult = runPhpHelper('pay_appointment_fee', seedData.appointment_id);
        recordResult('Settle Consultation Bill', payResult.success, `Fee Status: ${payResult.fee_status}`);

        // Step 6: Reload Live Queue
        console.log('[STEP 6] Reloading Live Queue to reflect paid state');
        await page.reload({ waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_live_queue_paid.png') });

        // Step 7: Verify Fee Column shows PAID and Mark Seen is ENABLED
        const feePaidLocator = page.locator('#fee_status_777');
        const feePaidText = await feePaidLocator.innerText();
        recordResult('Fee Column Shows PAID', feePaidText.includes('PAID'), `Status: ${feePaidText}`);

        const markSeenPaidBtn = page.locator('#btn_mark_seen_777');
        const isEnabledAfterPay = !(await markSeenPaidBtn.isDisabled());
        recordResult('Mark Seen Button Enabled When PAID', isEnabledAfterPay);

        const btnHtml = await markSeenPaidBtn.evaluate(el => el.outerHTML);
        console.log('  [BUTTON HTML]:', btnHtml);

        page.on('request', req => {
            if (req.url().includes('livewire')) {
                console.log('  [NET REQUEST DATA]:', req.postData()?.substring(0, 300));
            }
        });

        // Step 8: Click Mark Seen
        console.log('[STEP 8] Clicking Mark Seen on Paid Appointment');
        const markSeenResponsePromise = page.waitForResponse(resp => {
            if (!resp.url().includes('livewire') || resp.request().method() !== 'POST') return false;
            const postData = resp.request().postData() || '';
            return postData.includes('markSeen');
        }, { timeout: 15000 });

        await markSeenPaidBtn.click();
        await markSeenResponsePromise;
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_after_mark_seen.png') });

        const bodyText = await page.locator('body').innerText();
        console.log('  [DEBUG BODY SNIPPET]:', bodyText.substring(0, 500).replace(/\n+/g, ' '));

        // Step 9: Verify backend visit transition
        console.log('[STEP 9] Verifying backend visit transitioned to IN_PROGRESS');
        let checkData;
        for (let i = 0; i < 6; i++) {
            checkData = runPhpHelper('check_appointment_visit', seedData.appointment_id);
            if (checkData.appointment && checkData.appointment.status === 'IN_PROGRESS') {
                break;
            }
            await page.waitForTimeout(500);
        }
        recordResult('Appointment Status IN_PROGRESS', checkData.appointment?.status === 'IN_PROGRESS', `Status: ${checkData.appointment?.status}`);
        recordResult('Visit Created and IN_PROGRESS', checkData.visit?.status === 'IN_PROGRESS', `Visit ID: ${checkData.visit?.id}`);
        recordResult('Visit started_at Populated', !!checkData.visit?.started_at, `Started: ${checkData.visit?.started_at}`);

        // Step 10: Repeat test for Operator Role
        console.log('[STEP 10] Testing Operator Role Fee Gate');
        await page.goto(`${BASE_URL}/logout`);
        // Seed another unpaid appointment
        const opSeed = runPhpHelper('seed_fee_gate_appointment', 'democlinic');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await page.fill('#email', 'demooperator@dxclinic.local');
        await page.fill('#password', 'DemoOp@2026!');
        await page.locator('#btn_submit_login').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        recordResult('Operator Login', page.url().includes('/dashboard'));

        await page.goto(`${BASE_URL}/clinic/democlinic/queue/live`, { waitUntil: 'networkidle' });
        const opMarkSeen = page.locator('#btn_mark_seen_777');
        const opDisabled = await opMarkSeen.isDisabled();
        recordResult('Operator Role Mark Seen Disabled on Unpaid', opDisabled);

        // Step 11: Repeat test for Doctor Role
        console.log('[STEP 11] Testing Doctor Role Fee Gate');
        await page.goto(`${BASE_URL}/logout`);
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await page.fill('#email', 'demodoctor@dxclinic.local');
        await page.fill('#password', 'DemoDoc01@2026!');
        await page.locator('#btn_submit_login').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        recordResult('Doctor Login', page.url().includes('/dashboard'));

        await page.goto(`${BASE_URL}/clinic/democlinic/queue/live`, { waitUntil: 'networkidle' });
        const docMarkSeen = page.locator('#btn_mark_seen_777');
        const docDisabled = await docMarkSeen.isDisabled();
        recordResult('Doctor Role Mark Seen Disabled on Unpaid', docDisabled);

    } catch (err) {
        console.error('Fatal error during fee gate test:', err);
        recordResult('Fee Gate E2E Execution', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n----------------------------------------------------------------------');
    console.log(`TOTAL: ${summary.total} | PASSED: ${summary.passed} | FAILED: ${summary.failed}`);
    console.log('----------------------------------------------------------------------\n');

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runFeeGateMarkSeenSuite();
