import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/pwa_fix');

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

async function runSuite() {
    console.log('======================================================================');
    console.log('  DXCLINIC — PATIENT PWA REGISTRATION 500 FIX PLAYWRIGHT E2E TEST     ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-PWA-E2E/1.0'
    });

    const page = await context.newPage();

    const consoleErrors = [];
    const http500Urls = [];

    page.on('console', msg => {
        if (msg.type() === 'error') {
            consoleErrors.push(msg.text());
        }
    });

    page.on('response', resp => {
        if (resp.status() >= 500) {
            http500Urls.push({ url: resp.url(), status: resp.status() });
        }
    });

    try {
        // ------------------------------------------------------------------
        // TEST 1: Load Canonical Public PWA /book/sharmacare
        // ------------------------------------------------------------------
        console.log('▶ [TEST 1] Loading /book/sharmacare...');
        const resp = await page.goto(`${BASE_URL}/book/sharmacare`, { waitUntil: 'networkidle' });
        const status = resp.status();
        const hasSharma = (await page.content()).includes('Sharma Care');
        recordResult('PWA /book/sharmacare loads with HTTP 200 and clinic branding', status === 200 && hasSharma, `status: ${status}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_pwa_loaded.png') });

        // ------------------------------------------------------------------
        // TEST 2: Submit with Invalid Mobile (98765 43210) — MUST NOT BE 500
        // ------------------------------------------------------------------
        console.log('▶ [TEST 2] Submitting with invalid phone (98765 43210)...');
        await page.fill('#pwa_patient_name', 'Sunita Devi');
        await page.fill('#pwa_patient_age', '42');
        await page.selectOption('#pwa_patient_sex', 'FEMALE');
        await page.fill('#pwa_patient_mobile', '98765 43210');
        await page.fill('#pwa_patient_address', 'House 12, Lanka Road');
        await page.fill('#pwa_patient_city', 'Varanasi');

        // Click submit
        await page.click('#btn_pwa_submit_booking');
        await page.waitForTimeout(1000);

        // Check no 500 occurred
        const had500OnInvalid = http500Urls.length > 0;
        recordResult('Submission with invalid phone does NOT trigger HTTP 500', !had500OnInvalid, had500OnInvalid ? JSON.stringify(http500Urls) : 'Zero 500 errors');

        // Check validation message appears
        const mobileError = await page.locator('text=Enter exactly 10 digits').count();
        recordResult('Validation message for mobile format is visible', mobileError > 0, `found: ${mobileError}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_invalid_mobile_validation.png') });

        // ------------------------------------------------------------------
        // TEST 3: Submit Valid Registration — Genuine Booking Success
        // ------------------------------------------------------------------
        console.log('▶ [TEST 3] Submitting valid new patient registration...');
        // Correct the mobile number to valid 10 digits
        await page.fill('#pwa_patient_mobile', '9876543219');
        await page.waitForTimeout(500);

        // Click submit
        await page.click('#btn_pwa_submit_booking');
        await page.waitForTimeout(2000);

        // Verify no 500
        recordResult('Valid booking submission does NOT trigger HTTP 500', http500Urls.length === 0, `500 count: ${http500Urls.length}`);

        // Verify confirmation card appears
        const confirmationHeader = await page.locator('text=Your Appointment is Confirmed!').count();
        const tokenElem = await page.locator('#confirmed_token_number').count();
        const tokenText = tokenElem > 0 ? await page.locator('#confirmed_token_number').innerText() : '';
        const pdfBtn = await page.locator('#btn_download_token_pdf').count();

        recordResult('Booking confirmation card is displayed', confirmationHeader > 0, `confirmation: ${confirmationHeader}`);
        recordResult('Queue token number is allocated and displayed', tokenElem > 0 && tokenText.trim().length > 0, `token: ${tokenText.trim()}`);
        recordResult('Download Token PDF button is present', pdfBtn > 0, `button found: ${pdfBtn}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_booking_success.png') });

        // ------------------------------------------------------------------
        // TEST 4: Book Another Button Resets Form Cleanly
        // ------------------------------------------------------------------
        console.log('▶ [TEST 4] Testing "Book Another Appointment" flow...');
        await page.click('#btn_book_another');
        await page.waitForTimeout(1000);
        const formVisible = await page.locator('#btn_pwa_submit_booking').count();
        recordResult('Clicking "Book Another" returns to fresh booking form', formVisible > 0);

        // ------------------------------------------------------------------
        // TEST 5: Tenant Isolation — Invalid Slug Returns 404
        // ------------------------------------------------------------------
        console.log('▶ [TEST 5] Testing invalid slug /book/INVALID_SLUG_PWA_TEST...');
        const respInvalid = await page.goto(`${BASE_URL}/book/INVALID_SLUG_PWA_TEST`);
        recordResult('Invalid clinic slug returns HTTP 404', respInvalid.status() === 404, `status: ${respInvalid.status()}`);

    } catch (err) {
        console.error('Fatal E2E error:', err);
        recordResult('Suite Execution without unhandled error', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  E2E RUN COMPLETED: ${summary.passed}/${summary.total} PASSED (${summary.failed} FAILED)`);
    console.log('======================================================================\n');

    const reportPath = path.join(SCREENSHOT_DIR, 'pwa_e2e_report.json');
    fs.writeFileSync(reportPath, JSON.stringify(summary, null, 2));
    console.log(`E2E Summary Report written to: ${reportPath}`);

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite().catch(err => {
    console.error(err);
    process.exit(1);
});
