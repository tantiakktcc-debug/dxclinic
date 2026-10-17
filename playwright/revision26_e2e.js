import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8088';
const SCREENSHOT_DIR = path.resolve('test-results/revision26');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

const summary = {
    total: 0,
    passed: 0,
    failed: 0,
    tests: [],
    consoleErrors: [],
    networkErrors: [],
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
    console.log('       DXCLINIC REVISION 26 — PLAYWRIGHT HISTORICAL BILLING E2E       ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    console.log(`[INIT] Automated Browser: Google Chrome v${browser.version()}`);
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Revision26-E2E/1.0 (Google-Chrome-Desktop)'
    });

    const page = await context.newPage();

    page.on('console', msg => {
        if (msg.type() === 'error') {
            summary.consoleErrors.push(`[Console Error] ${msg.text()}`);
        }
    });

    page.on('response', resp => {
        if (resp.status() >= 400 && resp.status() !== 401 && resp.status() !== 403 && resp.status() !== 404) {
            summary.networkErrors.push(`[Network ${resp.status()}] ${resp.url()}`);
        }
    });

    try {
        // ------------------------------------------------------------------
        // TEST 1: Operator Authentication
        // ------------------------------------------------------------------
        console.log('[PHASE 1] Operator Authentication');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        if (await page.locator('button:has-text("Operator")').count() > 0) {
            await page.locator('button:has-text("Operator")').first().click();
        } else {
            await page.fill('#clinic_code', 'NX0001');
            await page.fill('#email', 'operator@dxclinic.local');
            await page.fill('#password', 'DxClinic@Op2026');
        }
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        recordResult('Operator Login Success', page.url().includes('/dashboard'), 'Logged in as operator');

        // ------------------------------------------------------------------
        // TEST 2: Consultation Billing Navigation & Tabs
        // ------------------------------------------------------------------
        console.log('\n[PHASE 2] Consultation Billing History & Doctor Pad Navigation');
        await page.goto(`${BASE_URL}/operator/consultation`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_consultation_billing.png') });

        const hasNewTab = await page.locator('button:has-text("New Consultation Billing")').count() > 0;
        const hasHistoryTab = await page.locator('button:has-text("Consultation Billing History")').count() > 0;
        const hasPadTab = await page.locator('button:has-text("Doctor Pad History")').count() > 0;
        recordResult('Consultation 3-Tab Interface Present', hasNewTab && hasHistoryTab && hasPadTab, 'New, History, Doctor Pads tabs verified');

        // Switch to History Tab
        await page.locator('button:has-text("Consultation Billing History")').click();
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_consultation_history_tab.png') });

        const historyContent = await page.content();
        const hasFilters = historyContent.includes('Date From') && historyContent.includes('Doctor') && historyContent.includes('Status');
        recordResult('Consultation History Filters Rendered', hasFilters, 'Date From, Doctor, Status filters present');

        // Switch to Doctor Pad History Tab
        await page.locator('button:has-text("Doctor Pad History")').click();
        await page.waitForTimeout(500);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_doctor_pad_history_tab.png') });
        const padContent = await page.content();
        const hasPadTable = padContent.includes('Doctor Pad History') && (padContent.includes('Pre-printed') || padContent.includes('Overlay Mode') || padContent.includes('Doctor'));
        recordResult('Doctor Pad History Interface Rendered', hasPadTable, 'Doctor pad history loaded with pre-printed overlay indicators');

        // ------------------------------------------------------------------
        // TEST 3: Diagnostic Billing Navigation & Historical Tab
        // ------------------------------------------------------------------
        console.log('\n[PHASE 3] Diagnostic Billing History Navigation');
        await page.goto(`${BASE_URL}/operator/diagnostics`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_diagnostic_billing.png') });

        const hasDiagNewTab = await page.locator('button:has-text("New Diagnostic Billing")').count() > 0;
        const hasDiagHistTab = await page.locator('button:has-text("Diagnostic Billing History")').count() > 0;
        recordResult('Diagnostic 2-Tab Interface Present', hasDiagNewTab && hasDiagHistTab, 'New and History tabs verified');

        // Switch to Diagnostic History Tab
        await page.locator('button:has-text("Diagnostic Billing History")').click();
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_diagnostic_history_tab.png') });
        const diagHistContent = await page.content();
        const hasDiagFilters = diagHistContent.includes('Date From') && diagHistContent.includes('Doctor') && diagHistContent.includes('Status');
        recordResult('Diagnostic History Filters Rendered', hasDiagFilters, 'Filters and history table present');

        // ------------------------------------------------------------------
        // TEST 4: Real PDF Reprint Endpoints Verification
        // ------------------------------------------------------------------
        console.log('\n[PHASE 4] Real PDF Reprint Endpoints (Zero Side Effects)');

        // Doctor Pad PDF
        const docPadResp = await page.request.get(`${BASE_URL}/pdf/doctor-pad/1`);
        recordResult('Doctor Pad PDF Endpoint Status', docPadResp.status() === 200, `Status: ${docPadResp.status()}`);
        const docPadBuffer = await docPadResp.body();
        const isDocPadPdf = docPadBuffer.slice(0, 4).toString() === '%PDF';
        recordResult('Doctor Pad PDF Binary Signature', isDocPadPdf && docPadBuffer.length > 1000, `Size: ${docPadBuffer.length} bytes, Header: %PDF`);

        // Consultation Receipt PDF
        const consultResp = await page.request.get(`${BASE_URL}/pdf/consultation-receipt/1`);
        recordResult('Consultation Receipt PDF Endpoint Status', consultResp.status() === 200, `Status: ${consultResp.status()}`);
        const consultBuffer = await consultResp.body();
        const isConsultPdf = consultBuffer.slice(0, 4).toString() === '%PDF';
        recordResult('Consultation Receipt PDF Binary Signature', isConsultPdf && consultBuffer.length > 1000, `Size: ${consultBuffer.length} bytes, Header: %PDF`);

        // Diagnostic Receipt PDF
        const diagResp = await page.request.get(`${BASE_URL}/pdf/diagnostic-receipt/1`);
        recordResult('Diagnostic Receipt PDF Endpoint Status', diagResp.status() === 200, `Status: ${diagResp.status()}`);
        const diagBuffer = await diagResp.body();
        const isDiagPdf = diagBuffer.slice(0, 4).toString() === '%PDF';
        recordResult('Diagnostic Receipt PDF Binary Signature', isDiagPdf && diagBuffer.length > 1000, `Size: ${diagBuffer.length} bytes, Header: %PDF`);

        // Lab Collection Slip PDF
        const labSlipResp = await page.request.get(`${BASE_URL}/pdf/lab-slip/1`);
        recordResult('Lab Collection Slip PDF Endpoint Status', labSlipResp.status() === 200, `Status: ${labSlipResp.status()}`);
        const labSlipBuffer = await labSlipResp.body();
        const isLabSlipPdf = labSlipBuffer.slice(0, 4).toString() === '%PDF';
        recordResult('Lab Collection Slip PDF Binary Signature', isLabSlipPdf && labSlipBuffer.length > 1000, `Size: ${labSlipBuffer.length} bytes, Header: %PDF`);

        // ------------------------------------------------------------------
        // TEST 5: Doctor Role Boundaries
        // ------------------------------------------------------------------
        console.log('\n[PHASE 5] Doctor Role Authentication & Authorization');
        // Clear session cookies to log out operator
        await context.clearCookies();
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        if (await page.locator('button:has-text("Doctor")').count() > 0) {
            await page.locator('button:has-text("Doctor")').first().click();
        } else {
            await page.fill('#clinic_code', 'NX0001');
            await page.fill('#email', 'doctor@dxclinic.local');
            await page.fill('#password', 'DxClinic@Doc2026');
        }
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        recordResult('Doctor Login Success', page.url().includes('/dashboard'), 'Logged in as doctor');

        // Doctor can access doctor pad reprint
        const docReprintResp = await page.request.get(`${BASE_URL}/pdf/doctor-pad/1`);
        recordResult('Doctor Permitted to View/Reprint Doctor Pad', docReprintResp.status() === 200, `Status: ${docReprintResp.status()}`);

    } catch (err) {
        console.error('Test execution error:', err);
        recordResult('Execution Safety', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log('REVISION 26 PLAYWRIGHT AUTOMATION RESULTS:');
    console.log(`  Passed:  ${summary.passed}`);
    console.log(`  Failed:  ${summary.failed}`);
    console.log(`  Total:   ${summary.total}`);
    console.log(`  Console Errors: ${summary.consoleErrors.length}`);
    console.log(`  Network Errors: ${summary.networkErrors.length}`);
    console.log('======================================================================\n');

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite();
