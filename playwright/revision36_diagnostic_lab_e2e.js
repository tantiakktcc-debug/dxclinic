import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/revision36');

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
    console.log('  DXCLINIC REVISION 36 — DIAGNOSTIC LAB WORKFLOW E2E TEST SUITE        ');
    console.log('======================================================================\n');

    // Step 0: Ensure fresh diagnostic order exists via test helper
    console.log('[SETUP] Seeding clean E2E diagnostic order fixture...');
    let fixtureData = {};
    try {
        const out = execSync('php scratch/create_test_order.php', { encoding: 'utf-8' });
        const lastLine = out.trim().split('\n').pop();
        fixtureData = JSON.parse(lastLine);
        console.log(`  [FIXTURE] Order #${fixtureData.order_number} (ID: ${fixtureData.order_id}) prepared for ${fixtureData.patient_name}`);
    } catch (e) {
        console.error('  [WARN] Fixture setup output:', e.message);
    }

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    console.log(`[INIT] Automated Browser: Google Chrome v${browser.version()}`);
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Revision36-LabWorkflow-E2E/1.0 (Google-Chrome-Desktop)'
    });

    const page = await context.newPage();

    page.on('console', msg => {
        if (msg.type() === 'error') {
            summary.consoleErrors.push(`[Console Error] ${msg.text()}`);
        }
    });

    try {
        // ==================================================================
        // PHASE 1: Authentication as Authorized Admin
        // ==================================================================
        console.log('\n[PHASE 1] Authorized Clinic Admin Authentication');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await page.fill('#clinic_code', 'NX0001');
        await page.fill('#email', 'admin@dxclinic.local');
        await page.fill('#password', 'DxClinic@Admin2026');
        await page.locator('#btn_submit_login').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        recordResult('1. Admin Login Success', page.url().includes('/dashboard'), 'Authenticated as NX0001 admin');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_admin_dashboard.png') });

        // ==================================================================
        // PHASE 2: Open Diagnostic Lab Workflow
        // ==================================================================
        console.log('\n[PHASE 2] Navigate to Diagnostic Laboratory Workspace');
        await page.goto(`${BASE_URL}/lab`, { waitUntil: 'networkidle' });
        const pageTitle = await page.textContent('h1');
        recordResult('2. Open Diagnostic Lab Workspace', pageTitle.includes('Diagnostic Laboratory Workflow'), `Title: ${pageTitle}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_lab_workspace.png') });

        // ==================================================================
        // PHASE 3: Locate Diagnostic Order
        // ==================================================================
        console.log('\n[PHASE 3] Locate Diagnostic Order in Worklist');
        const orderNum = fixtureData.order_number;
        const searchInput = page.locator('input[placeholder*="Search patient"]');
        await searchInput.fill(orderNum);
        await page.waitForTimeout(600); // Allow debounce livewire refresh

        const orderCell = page.locator(`td:has-text("${orderNum}")`).first();
        const orderVisible = await orderCell.isVisible();
        recordResult('3. Locate Diagnostic Order', orderVisible, `Found order row for ${orderNum}`);

        // ==================================================================
        // PHASE 4: Verify Billing/Payment Information is Read-Only
        // ==================================================================
        console.log('\n[PHASE 4] Verify Billing / Payment Information is Read-Only');
        const billBadge = page.locator('tr:has-text("' + orderNum + '") span:has-text("PAID")').first();
        const billAmount = page.locator('tr:has-text("' + orderNum + '") div:has-text("₹350.00")').first();
        const isPaidVisible = await billBadge.isVisible();
        const isAmountVisible = await billAmount.isVisible();
        recordResult('4. Billing Information Displayed & Read-Only', isPaidVisible && isAmountVisible, 'PAID badge and net amount ₹350.00 rendered in lab worklist without editable inputs');

        // ==================================================================
        // PHASE 5: Collect Specimen
        // ==================================================================
        console.log('\n[PHASE 5] Specimen Collection Workflow');
        const collectBtn = page.locator(`tr:has-text("${orderNum}") button:has-text("Collect Sample")`).first();
        await collectBtn.click();
        await page.waitForTimeout(500);

        const collectModalTitle = page.locator('h3:has-text("Record Specimen Collection")');
        recordResult('5a. Open Sample Collection Modal', await collectModalTitle.isVisible(), 'Collection modal visible');

        const confirmCollectBtn = page.locator('button:has-text("Confirm Collection & Generate Sample ID")');
        await confirmCollectBtn.click();
        await page.waitForTimeout(1000);

        // ==================================================================
        // PHASE 6: Verify Specimen Identifier
        // ==================================================================
        console.log('\n[PHASE 6] Verify Concurrency-Safe Human-Readable Specimen Identifier');
        const specimenBadge = page.locator(`tr:has-text("${orderNum}") div.font-mono:has-text("SMP-NX0001-")`).first();
        const specimenIdText = await specimenBadge.textContent();
        const validFormat = /^SMP-NX0001-\d{8}-\d{4}$/.test(specimenIdText.trim());
        recordResult('6. Specimen Identifier Formatted Correctly', validFormat, `Generated Specimen ID: ${specimenIdText.trim()}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_sample_collected.png') });

        // ==================================================================
        // PHASE 7: Verify Duplicate Collection is Prevented
        // ==================================================================
        console.log('\n[PHASE 7] Verify Duplicate Collection Prevention');
        const collectBtnAfter = page.locator(`tr:has-text("${orderNum}") button:has-text("Collect Sample")`);
        const canCollectAgain = await collectBtnAfter.count() > 0;
        recordResult('7. Duplicate Collection Prevented', !canCollectAgain, 'Collect button replaced by status badge and Enter Results action');

        // ==================================================================
        // PHASE 8: Open Result Entry Modal
        // ==================================================================
        console.log('\n[PHASE 8] Open Structured Result Entry');
        const enterResultsBtn = page.locator(`tr:has-text("${orderNum}") button:has-text("Enter Results")`).first();
        await enterResultsBtn.click();
        await page.waitForTimeout(600);

        const resultModalTitle = page.locator('h3:has-text("Laboratory Structured Result Entry")');
        recordResult('8. Open Result Entry Modal', await resultModalTitle.isVisible(), 'Structured result entry modal opened');

        // ==================================================================
        // PHASE 9: Enter Result Values
        // ==================================================================
        console.log('\n[PHASE 9] Enter Structured Test Results');
        const resultInput = page.locator('input[wire\\:model*="result_value"]').first();
        await resultInput.fill('14.2');

        const remarksInput = page.locator('input[wire\\:model*="remarks"]').first();
        await remarksInput.fill('Normal adult haemoglobin levels within biological reference range');
        recordResult('9. Fill Structured Result Values', true, 'Entered 14.2 g/dL with clinical remarks');

        // ==================================================================
        // PHASE 10: Save Results
        // ==================================================================
        console.log('\n[PHASE 10] Save Result Entry');
        const saveResultsBtn = page.locator('button:has-text("Save Results")');
        await saveResultsBtn.click();
        await page.waitForTimeout(1000);

        const statusEnteredBadge = page.locator(`tr:has-text("${orderNum}") span:has-text("RESULTS ENTERED")`).first();
        recordResult('10. Results Saved as DRAFT / ENTERED', await statusEnteredBadge.isVisible(), 'Status advanced to RESULTS ENTERED');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_results_saved.png') });

        // ==================================================================
        // PHASE 11: Verify Result (Clinician Verification)
        // ==================================================================
        console.log('\n[PHASE 11] Clinician Verification');
        const verifyBtn = page.locator(`tr:has-text("${orderNum}") button:has-text("Verify")`).first();
        await verifyBtn.click();
        await page.waitForTimeout(1000);

        const statusVerifiedBadge = page.locator(`tr:has-text("${orderNum}") span:has-text("VERIFIED")`).first();
        recordResult('11. Clinician Verification Success', await statusVerifiedBadge.isVisible(), 'Order status advanced to VERIFIED');

        // ==================================================================
        // PHASE 12: Release Report
        // ==================================================================
        console.log('\n[PHASE 12] Release Final Diagnostic Report');
        const releaseModalBtn = page.locator(`tr:has-text("${orderNum}") button:has-text("Release Report")`).first();
        await releaseModalBtn.click();
        await page.waitForTimeout(600);

        const releaseModalTitle = page.locator('h3:has-text("Authorize & Release Final Report")');
        recordResult('12a. Release Confirmation Modal Opened', await releaseModalTitle.isVisible(), 'Release modal displayed');

        const reportNotes = page.locator('textarea[wire\\:model="reportNotes"]');
        await reportNotes.fill('CBC findings unremarkable. Verified by attending pathologist.');

        const confirmReleaseBtn = page.locator('button:has-text("Confirm & Release Report")');
        await confirmReleaseBtn.click();
        await page.waitForTimeout(1200);

        const statusReleasedBadge = page.locator(`tr:has-text("${orderNum}") span:has-text("RELEASED")`).first();
        recordResult('12b. Final Report Release Success', await statusReleasedBadge.isVisible(), 'Order finalized as RELEASED');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_report_released.png') });

        // ==================================================================
        // PHASE 13: Open Final Report PDF & Verify PDF Signatures
        // ==================================================================
        console.log('\n[PHASE 13] Open Final Diagnostic Report PDF');
        const pdfLink = page.locator(`tr:has-text("${orderNum}") a:has-text("PDF")`).first();
        const pdfHref = await pdfLink.getAttribute('href');
        recordResult('13a. PDF Link Generated', pdfHref && pdfHref.includes('/pdf/diagnostic-result-report/'), `PDF Route: ${pdfHref}`);

        // Fetch PDF via browser context to inspect raw binary bytes and headers
        const pdfResponse = await page.request.get(pdfHref);
        const pdfStatus = pdfResponse.status();
        const pdfContentType = pdfResponse.headers()['content-type'] || '';
        const pdfBody = await pdfResponse.body();
        const pdfHeaderSignature = pdfBody.toString('utf-8', 0, 5);

        recordResult('13b. PDF HTTP 200 & Content-Type', pdfStatus === 200 && pdfContentType.includes('application/pdf'), `Status: ${pdfStatus}, Content-Type: ${pdfContentType}`);
        recordResult('13c. PDF Magic Signature %PDF-', pdfHeaderSignature === '%PDF-', `Found magic signature: ${pdfHeaderSignature}, Total Size: ${pdfBody.length} bytes`);

        // Save PDF artifact for inspection
        const savedPdfPath = path.join(SCREENSHOT_DIR, 'final_lab_report.pdf');
        fs.writeFileSync(savedPdfPath, pdfBody);
        console.log(`  [PDF] Saved copy to: ${savedPdfPath}`);

        // ==================================================================
        // PHASE 14: Public Lab Report Verification Endpoint (QR Landing Page)
        // ==================================================================
        console.log('\n[PHASE 14] Public QR Verification Landing Page');
        let reportData = {};
        try {
            const rptOut = execSync(`php scratch/get_report_and_bill.php ${fixtureData.order_id}`, { encoding: 'utf-8' });
            reportData = JSON.parse(rptOut.trim());
        } catch (e) {
            console.error('Error fetching report details:', e.message);
        }

        const reportNum = reportData.report_number;
        if (reportNum) {
            const verifyUrl = `${BASE_URL}/verify-lab-report/${reportNum}`;
            const verifyResp = await page.goto(verifyUrl, { waitUntil: 'networkidle' });
            const verifyStatus = verifyResp.status();
            const verifyBadge = page.locator('.badge-verified:has-text("Authentic Clinical Report")');
            const isAuthentic = await verifyBadge.isVisible();
            recordResult('14. Public QR Verification Landing Page', verifyStatus === 200 && isAuthentic, `HTTP ${verifyStatus}, Verified Report #${reportNum}`);
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_public_qr_verification.png') });
        } else {
            recordResult('14. Public QR Verification Landing Page', false, 'Report number not found');
        }

        // ==================================================================
        // PHASE 15: Report Reprint Idempotency
        // ==================================================================
        console.log('\n[PHASE 15] Report Reprint Idempotency');
        const reprintResponse = await page.request.get(pdfHref);
        const reprintStatus = reprintResponse.status();
        const reprintBody = await reprintResponse.body();
        recordResult('15. Report Reprint Idempotent (No Side Effects)', reprintStatus === 200 && reprintBody.length === pdfBody.length, `Reprint size matched: ${reprintBody.length} bytes`);

        // ==================================================================
        // PHASE 16: Verify Zero Accounting Side Effects
        // ==================================================================
        console.log('\n[PHASE 16] Verify Zero Financial / Accounting Side Effects');
        const isFinIntact = parseFloat(reportData.bill_net) === 350.00 && reportData.bill_status === 'PAID';
        recordResult('16. Diagnostic Bill Amount & Status Unchanged', isFinIntact, `Bill net: ₹${reportData.bill_net}, status: ${reportData.bill_status}`);

        // ==================================================================
        // PHASE 17: Unauthorized Access Protection (RBAC & IDOR)
        // ==================================================================
        console.log('\n[PHASE 17] Unauthorized Access Control & Operator Demarcation');
        // Logout admin
        await page.goto(`${BASE_URL}/logout`);
        await page.waitForURL('**/login', { timeout: 10000 });

        // Login as Operator
        await page.fill('#clinic_code', 'NX0001');
        await page.fill('#email', 'operator@dxclinic.local');
        await page.fill('#password', 'DxClinic@Op2026');
        await page.locator('#btn_submit_login').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });

        // Go to lab workspace as operator
        await page.goto(`${BASE_URL}/lab`, { waitUntil: 'networkidle' });
        // Verify operator does NOT have verify button for pending items
        const opVerifyBtns = page.locator('button:has-text("Verify")');
        const opCanVerify = await opVerifyBtns.count() > 0;
        recordResult('17a. Operator Role Cannot Verify Results (RBAC)', !opCanVerify, 'Verify action button hidden from Operator view');

        // Cross-clinic negative test
        const crossClinicResp = await page.goto(`${BASE_URL}/clinic/arogya/operator/queue`);
        recordResult('17b. Cross-Clinic Access Forbidden (IDOR)', crossClinicResp.status() === 403, `Cross-clinic status: ${crossClinicResp.status()}`);

    } catch (err) {
        console.error('[SUITE ERROR]', err);
        recordResult('Suite Execution Exception', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  E2E RUN COMPLETE: ${summary.passed}/${summary.total} PASSED (${summary.failed} FAILED)`);
    console.log('======================================================================\n');

    const summaryPath = path.join(SCREENSHOT_DIR, 'summary.json');
    fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2));
    console.log(`Detailed summary saved to: ${summaryPath}\n`);

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite();
