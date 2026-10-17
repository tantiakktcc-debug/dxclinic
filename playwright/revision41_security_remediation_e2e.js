import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/revision41');

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
    console.log('  DXCLINIC REVISION 41 — SECURITY REMEDIATION & TENANT ISOLATION E2E  ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    console.log(`[INIT] Automated Browser: Google Chrome v${browser.version()}`);

    try {
        // ==================================================================
        // TEST 1: NX0001 Login & Dashboard Verification
        // ==================================================================
        console.log('\n--- 1. NX0001 Login ---');
        const nx1Context = await browser.newContext({
            viewport: { width: 1440, height: 900 },
            userAgent: 'DxClinic-Revision41-E2E-NX1/1.0'
        });
        const nx1Page = await nx1Context.newPage();

        await nx1Page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await nx1Page.fill('#clinic_code', 'NX0001');
        await nx1Page.fill('#email', 'admin@dxclinic.com');
        await nx1Page.fill('#password', 'password');
        await nx1Page.click('button[type="submit"]');
        await nx1Page.waitForURL('**/dashboard');
        await nx1Page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_nx0001_dashboard.png'), fullPage: true });

        const nx1DashContent = await nx1Page.content();
        recordResult('1. NX0001 login successful', nx1DashContent.includes('Dr. Rajesh Sharma') || nx1DashContent.includes('Clinic Admin') || nx1DashContent.includes("Today's Patients"), 'NX0001 dashboard loaded');

        // ==================================================================
        // TEST 2: NX0001 Queue Access
        // ==================================================================
        console.log('\n--- 2. NX0001 Queue ---');
        await nx1Page.goto(`${BASE_URL}/operator/queue`, { waitUntil: 'networkidle' });
        await nx1Page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_nx0001_queue.png'), fullPage: true });
        const nx1QueueContent = await nx1Page.content();
        recordResult('2. NX0001 queue accessible', nx1QueueContent.includes('Queue') || nx1QueueContent.includes('Arrival'), 'Queue view rendered');

        // ==================================================================
        // TEST 3: NX0001 Reports Access
        // ==================================================================
        console.log('\n--- 3. NX0001 Reports ---');
        await nx1Page.goto(`${BASE_URL}/reports/daily-cash`, { waitUntil: 'networkidle' });
        await nx1Page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_nx0001_reports.png'), fullPage: true });
        const nx1ReportContent = await nx1Page.content();
        recordResult('3. NX0001 reports accessible', nx1ReportContent.includes('Daily') || nx1ReportContent.includes('Cash') || nx1ReportContent.includes('Reconciliation') || nx1ReportContent.includes('Report'), 'Reports view rendered');

        // ==================================================================
        // TEST 4: NX0001 Consultation Receipt Print
        // ==================================================================
        console.log('\n--- 4. NX0001 Print Receipt ---');
        const receiptResp = await nx1Page.goto(`${BASE_URL}/print/consultation-receipt/1`, { waitUntil: 'networkidle' });
        recordResult('4. NX0001 receipt print accessible (200 OK)', receiptResp.status() === 200, `Status: ${receiptResp.status()}`);
        await nx1Page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_nx0001_receipt_print.png'), fullPage: true });

        // ==================================================================
        // TEST 5: NX0001 Doctor Pad Print
        // ==================================================================
        console.log('\n--- 5. NX0001 Doctor Pad ---');
        const docPadResp = await nx1Page.goto(`${BASE_URL}/print/doctor-pad/1`, { waitUntil: 'networkidle' });
        recordResult('5. NX0001 doctor pad accessible (200 OK)', docPadResp.status() === 200, `Status: ${docPadResp.status()}`);
        await nx1Page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_nx0001_doctor_pad.png'), fullPage: true });

        // ==================================================================
        // TEST 6: NX0002 Login & Dashboard Verification
        // ==================================================================
        console.log('\n--- 6. NX0002 Login ---');
        const nx2Context = await browser.newContext({
            viewport: { width: 1440, height: 900 },
            userAgent: 'DxClinic-Revision41-E2E-NX2/1.0'
        });
        const nx2Page = await nx2Context.newPage();

        await nx2Page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await nx2Page.fill('#clinic_code', 'NX0002');
        await nx2Page.fill('#email', 'tripathi@arogya.local');
        await nx2Page.fill('#password', 'ArogyaAdmin@2026!');
        await nx2Page.click('button[type="submit"]');
        await nx2Page.waitForURL('**/dashboard');
        await nx2Page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_nx0002_dashboard.png'), fullPage: true });

        const nx2DashContent = await nx2Page.content();
        recordResult('6. NX0002 login successful', nx2DashContent.includes('Dr. Rajesh Tripathi') || nx2DashContent.includes("Today's Patients"), 'Arogya clinic dashboard loaded');

        // ==================================================================
        // TEST 7: NX0002 Queue Access & Isolation
        // ==================================================================
        console.log('\n--- 7. NX0002 Queue ---');
        await nx2Page.goto(`${BASE_URL}/operator/queue`, { waitUntil: 'networkidle' });
        await nx2Page.screenshot({ path: path.join(SCREENSHOT_DIR, '07_nx0002_queue.png'), fullPage: true });
        const nx2QueueContent = await nx2Page.content();
        recordResult('7. NX0002 queue loaded', nx2QueueContent.includes('Queue') || nx2QueueContent.includes('Arrival'), 'Queue rendered');
        recordResult('7. NX0001 doctor/clinic data absent from NX0002 queue', !nx2QueueContent.includes('Sharma Care Clinic') && !nx2QueueContent.includes('Dr. Rajesh Sharma'), 'NX0001 data absent');

        // ==================================================================
        // TEST 8: Attempt NX0001 Resource IDs from NX0002 Session (Cross-Tenant IDOR)
        // ==================================================================
        console.log('\n--- 8. Attempt NX0001 Resource IDs from NX0002 (Expect 403) ---');
        const nx2AttemptReceipt = await nx2Context.request.get(`${BASE_URL}/print/consultation-receipt/1`);
        recordResult('8. NX0002 accessing NX0001 receipt blocked (HTTP 403)', nx2AttemptReceipt.status() === 403, `Status: ${nx2AttemptReceipt.status()}`);

        const nx2AttemptDocPad = await nx2Context.request.get(`${BASE_URL}/pdf/doctor-pad/1`);
        recordResult('8. NX0002 accessing NX0001 doctor pad blocked (HTTP 403)', nx2AttemptDocPad.status() === 403, `Status: ${nx2AttemptDocPad.status()}`);

        const nx2AttemptPatientHistory = await nx2Context.request.get(`${BASE_URL}/pdf/patient-history/1`);
        recordResult('8. NX0002 accessing NX0001 patient history blocked (HTTP 403)', nx2AttemptPatientHistory.status() === 403, `Status: ${nx2AttemptPatientHistory.status()}`);

        const nx2AttemptTemplate = await nx2Context.request.get(`${BASE_URL}/pdf/prescription-template/3`);
        recordResult('8. NX0002 accessing NX0001 prescription template blocked (HTTP 403)', nx2AttemptTemplate.status() === 403, `Status: ${nx2AttemptTemplate.status()}`);

        // ==================================================================
        // TEST 9: Attempt Print/PDF IDOR from Unauthenticated Caller
        // ==================================================================
        console.log('\n--- 9. Attempt Print/PDF IDOR Unauthenticated (Expect 401 or Redirect to Login) ---');
        const unauthContext = await browser.newContext();
        
        const unauthReceipt = await unauthContext.request.get(`${BASE_URL}/print/consultation-receipt/1`, { maxRedirects: 0 });
        recordResult('9. Unauth receipt print blocked (302 redirect or 401/403)', [302, 401, 403].includes(unauthReceipt.status()), `Status: ${unauthReceipt.status()}`);

        const unauthDocPad = await unauthContext.request.get(`${BASE_URL}/pdf/doctor-pad/1`, { maxRedirects: 0 });
        recordResult('9. Unauth doctor pad PDF blocked (302 redirect or 401/403)', [302, 401, 403].includes(unauthDocPad.status()), `Status: ${unauthDocPad.status()}`);

        const unauthTemplate = await unauthContext.request.get(`${BASE_URL}/pdf/prescription-template/3`, { maxRedirects: 0 });
        recordResult('9. Unauth prescription template PDF blocked (302 redirect or 401/403)', [302, 401, 403].includes(unauthTemplate.status()), `Status: ${unauthTemplate.status()}`);

        // ==================================================================
        // TEST 10: Demo PWA
        // ==================================================================
        console.log('\n--- 10. Demo PWA ---');
        const demoContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const demoPage = await demoContext.newPage();
        await demoPage.goto(`${BASE_URL}/book/democlinic`, { waitUntil: 'networkidle' });
        await demoPage.screenshot({ path: path.join(SCREENSHOT_DIR, '10_demo_clinic_pwa.png'), fullPage: true });

        const demoContent = await demoPage.content();
        recordResult('10. Demo Clinic branding loaded', demoContent.includes('Demo Clinic'), 'Demo Clinic tenant resolved');
        recordResult('10. Demo doctor Dr. Amit Verma present', demoContent.includes('Dr. Amit Verma'), 'Found Dr. Amit Verma');
        recordResult('10. Demo doctor Dr. Neha Kapoor present', demoContent.includes('Dr. Neha Kapoor'), 'Found Dr. Neha Kapoor');

        // ==================================================================
        // TEST 11: Demo PWA Isolation
        // ==================================================================
        console.log('\n--- 11. Demo PWA Isolation ---');
        // Visit NX0001
        await demoPage.goto(`${BASE_URL}/book/NX0001`, { waitUntil: 'networkidle' });
        const pwaNx1Content = await demoPage.content();
        recordResult('11. NX0001 PWA has Dr. Rajesh Sharma', pwaNx1Content.includes('Dr. Rajesh Sharma'), 'Found Dr. Rajesh Sharma on NX0001');

        // Return to Demo Clinic
        await demoPage.goto(`${BASE_URL}/book/democlinic`, { waitUntil: 'networkidle' });
        const pwaDemoReturn = await demoPage.content();
        recordResult('11. Demo PWA preserves Amit Verma & Neha Kapoor', pwaDemoReturn.includes('Dr. Amit Verma') && pwaDemoReturn.includes('Dr. Neha Kapoor'), 'Demo doctors intact');
        recordResult('11. NX0001 Dr. Rajesh Sharma does NOT leak to Demo', !pwaDemoReturn.includes('Rajesh Sharma'), 'Rajesh Sharma is absent from Demo');
        recordResult('11. Demo fees ₹500 & ₹400 intact', pwaDemoReturn.includes('₹500') && pwaDemoReturn.includes('₹400'), 'Demo fees intact');
        recordResult('11. NX0001 fee ₹600 does NOT leak to Demo', !pwaDemoReturn.includes('₹600'), '₹600 absent from Demo');

        // Test cross-tenant query param tampering (/book/democlinic?clinic=NX0001)
        await demoPage.goto(`${BASE_URL}/book/democlinic?clinic=NX0001`, { waitUntil: 'networkidle' });
        const tamperedContent = await demoPage.content();
        recordResult('11. Query param tampering does not override tenant route', tamperedContent.includes('Demo Clinic') && !tamperedContent.includes('Rajesh Sharma'), 'Tenant isolation strictly preserved against query param override');

        // ==================================================================
        // TEST 12: Public API Behavior & Sanitization
        // ==================================================================
        console.log('\n--- 12. Public API Behavior & Sanitization ---');
        
        // 12a: Unauthenticated /api/queue/today -> 401
        const unauthQueue = await unauthContext.request.get(`${BASE_URL}/api/queue/today`);
        recordResult('12. Unauthenticated /api/queue/today returns 401 Unauthorized', unauthQueue.status() === 401, `Status: ${unauthQueue.status()}`);
        const unauthQueueBody = await unauthQueue.text();
        recordResult('12. Unauth queue response does not leak patient PII', !unauthQueueBody.includes('Ramesh') && !unauthQueueBody.includes('appointment'), 'No patient PII leaked');

        // 12b: Unauthenticated /api/reports/daily-summary -> 401
        const unauthReports = await unauthContext.request.get(`${BASE_URL}/api/reports/daily-summary`);
        recordResult('12. Unauthenticated /api/reports/daily-summary returns 401 Unauthorized', unauthReports.status() === 401, `Status: ${unauthReports.status()}`);
        const unauthReportsBody = await unauthReports.text();
        recordResult('12. Unauth reports response does not leak financial figures', !unauthReportsBody.includes('cash_opening') && !unauthReportsBody.includes('collections'), 'No financial balances leaked');

        // 12c: /api/health -> Sanitized public platform health
        const healthResp = await unauthContext.request.get(`${BASE_URL}/api/health`);
        recordResult('12. /api/health returns 200 OK', healthResp.status() === 200, `Status: ${healthResp.status()}`);
        const healthData = await healthResp.json();
        recordResult('12. /api/health contains platform status ok', healthData.status === 'ok' && healthData.database === 'connected', JSON.stringify(healthData));
        recordResult('12. /api/health does NOT leak clinic_name', !healthData.clinic_name && !healthData.clinic_code, 'clinic_name & clinic_code absent');
        recordResult('12. /api/health does NOT leak onboarding state or cash session', healthData.is_onboarded === undefined && healthData.expected_cash === undefined, 'onboarding & cash balances absent');

        // 12d: Cryptographically signed /api/queue/today URL works
        const signedUrlCmd = `php -r "require 'vendor/autoload.php'; (require 'bootstrap/app.php')->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo Illuminate\\Support\\Facades\\URL::signedRoute('api.queue.today', ['clinic_code' => 'NX0001']);"`;
        const signedUrl = execSync(signedUrlCmd, { cwd: path.resolve('.') }).toString().trim();
        const signedQueueResp = await unauthContext.request.get(signedUrl);
        recordResult('12. Signed /api/queue/today URL returns 200 OK', signedQueueResp.status() === 200, `Status: ${signedQueueResp.status()}`);
        const signedQueueData = await signedQueueResp.json();
        recordResult('12. Signed queue URL scopes strictly to authorized clinic', signedQueueData.clinic_code === 'NX0001' && signedQueueData.clinic_name === 'Sharma Care Clinic', `Clinic: ${signedQueueData.clinic_name}`);

        // 12e: Tampered signed URL rejected with 401
        const tamperedSignedResp = await unauthContext.request.get(`${BASE_URL}/api/queue/today?clinic_code=NX0001&signature=invalid_tampered_sig`);
        recordResult('12. Tampered signature on queue URL returns 401 Unauthorized', tamperedSignedResp.status() === 401, `Status: ${tamperedSignedResp.status()}`);

        // ==================================================================
        // TEST 13: Prescription Data Fields (M1 & M2)
        // ==================================================================
        console.log('\n--- 13. Prescription Data Fields (M1 & M2) ---');
        const mapperOut = JSON.parse(execSync('php scratch/test_prescription_fields.php', { cwd: path.resolve('.') }).toString().trim());

        recordResult('13. M1: patient.full_address_with_city_name produces "Address, City"', mapperOut.addr1 === 'Kabir Nagar Colony, Varanasi', `Output: ${mapperOut.addr1}`);
        recordResult('13. M1: Empty address uses city only without leading comma', mapperOut.addr2 === 'Varanasi', `Output: ${mapperOut.addr2}`);
        recordResult('13. M1: Empty city uses address only without trailing comma', mapperOut.addr3 === 'Village Rampur', `Output: ${mapperOut.addr3}`);
        recordResult('13. M2: patient.age_sex format is "42 Yrs / F"', mapperOut.age_sex1 === '42 Yrs / F', `Output: ${mapperOut.age_sex1}`);
        recordResult('13. M2: Infant age 0 formatted as "0 Yrs / M"', mapperOut.age_sex2 === '0 Yrs / M', `Output: ${mapperOut.age_sex2}`);
        recordResult('13. M2: Nullable sex formatted without error', mapperOut.age_sex3.includes('65 Yrs'), `Output: ${mapperOut.age_sex3}`);

    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  E2E SUMMARY: ${summary.passed}/${summary.total} PASSED (${summary.failed} FAILED)`);
    console.log('======================================================================\n');

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite().catch(err => {
    console.error('Fatal test error:', err);
    process.exit(1);
});
