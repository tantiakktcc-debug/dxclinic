import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/revision47');

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
    console.log('  DXCLINIC REVISION 47 — PUBLIC SLUG & TENANT ISOLATION E2E TEST SUITE ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Revision47-E2E/1.0 (Google-Chrome-Desktop)'
    });

    const page = await context.newPage();

    try {
        // ------------------------------------------------------------------
        // TEST 1: Generic /book without public_slug returns 404 (Section 19, 47)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 1] Testing GET /book returns 404 (No Generic Tenant Default)...');
        const respBook = await page.goto(`${BASE_URL}/book`);
        const statusBook = respBook.status();
        recordResult('GET /book returns 404', statusBook === 404, `status: ${statusBook}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_get_book_404.png') });

        // ------------------------------------------------------------------
        // TEST 2: Generic /b without public_slug returns 404
        // ------------------------------------------------------------------
        console.log('▶ [TEST 2] Testing GET /b returns 404...');
        const respB = await page.goto(`${BASE_URL}/b`);
        const statusB = respB.status();
        recordResult('GET /b returns 404', statusB === 404, `status: ${statusB}`);

        // ------------------------------------------------------------------
        // TEST 3: Old internal code /book/NX0001 returns 404 (Section 20)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 3] Testing GET /book/NX0001 returns 404 (Internal Code Prohibited)...');
        const respNx1 = await page.goto(`${BASE_URL}/book/NX0001`);
        const statusNx1 = respNx1.status();
        recordResult('GET /book/NX0001 returns 404', statusNx1 === 404, `status: ${statusNx1}`);

        // ------------------------------------------------------------------
        // TEST 4: Old internal code /book/1 returns 404
        // ------------------------------------------------------------------
        console.log('▶ [TEST 4] Testing GET /book/1 returns 404 (Numeric ID Prohibited)...');
        const respId1 = await page.goto(`${BASE_URL}/book/1`);
        const statusId1 = respId1.status();
        recordResult('GET /book/1 returns 404', statusId1 === 404, `status: ${statusId1}`);

        // ------------------------------------------------------------------
        // TEST 5: Short route /b/NX0001 returns 404
        // ------------------------------------------------------------------
        console.log('▶ [TEST 5] Testing GET /b/NX0001 returns 404...');
        const respShortNx1 = await page.goto(`${BASE_URL}/b/NX0001`);
        const statusShortNx1 = respShortNx1.status();
        recordResult('GET /b/NX0001 returns 404', statusShortNx1 === 404, `status: ${statusShortNx1}`);

        // ------------------------------------------------------------------
        // TEST 6: Canonical Public PWA /book/sharmacare returns 200 (Section 18, 47)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 6] Testing canonical GET /book/sharmacare...');
        const respSharma = await page.goto(`${BASE_URL}/book/sharmacare`);
        const statusSharma = respSharma.status();
        const contentSharma = await page.content();
        const hasSharmaName = contentSharma.includes('Sharma Care');
        recordResult('GET /book/sharmacare returns 200 with clinic branding', statusSharma === 200 && hasSharmaName, `status: ${statusSharma}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_book_sharmacare.png') });

        // ------------------------------------------------------------------
        // TEST 7: Short URL /b/sharmacare redirects to /book/sharmacare (Section 21)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 7] Testing short URL /b/sharmacare canonical redirect...');
        await page.goto(`${BASE_URL}/b/sharmacare`);
        const finalUrl = page.url();
        const isRedirectedToBook = finalUrl.includes('/book/sharmacare');
        recordResult('GET /b/sharmacare redirects to /book/sharmacare', isRedirectedToBook, `final URL: ${finalUrl}`);

        // ------------------------------------------------------------------
        // TEST 8: Demo Clinic /book/democlinic returns 200 (Section 31)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 8] Testing Demo Clinic public PWA /book/democlinic...');
        const respDemo = await page.goto(`${BASE_URL}/book/democlinic`);
        const statusDemo = respDemo.status();
        const contentDemo = await page.content();
        const hasDemoName = contentDemo.includes('Demo Clinic');
        recordResult('GET /book/democlinic returns 200', statusDemo === 200 && hasDemoName, `status: ${statusDemo}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_book_democlinic.png') });

        // ------------------------------------------------------------------
        // TEST 9: Cross-tenant query parameter tampering rejected with 403 (Section 22, 47)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 9] Testing query parameter tampering (?clinic_id=2)...');
        const respTamper = await page.goto(`${BASE_URL}/book/sharmacare?clinic_id=2`);
        const statusTamper = respTamper.status();
        recordResult('Query parameter clinic_id tampering returns 403', statusTamper === 403, `status: ${statusTamper}`);

        // ------------------------------------------------------------------
        // TEST 10: Generic /operator/queue returns 404 (Section 25, 47)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 10] Testing generic /operator/queue returns 404...');
        const respGenQueue = await page.goto(`${BASE_URL}/operator/queue`);
        const statusGenQueue = respGenQueue.status();
        recordResult('GET /operator/queue returns 404', statusGenQueue === 404, `status: ${statusGenQueue}`);

        // ------------------------------------------------------------------
        // TEST 11: Unauthenticated tenant operator queue redirects to /login (Section 26, 47)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 11] Testing unauthenticated /clinic/sharmacare/operator/queue redirects to login...');
        await page.goto(`${BASE_URL}/clinic/sharmacare/operator/queue`);
        const authRedirectUrl = page.url();
        const isLoginRedirect = authRedirectUrl.includes('/login');
        recordResult('Unauthenticated operator queue redirects to /login', isLoginRedirect, `redirected to: ${authRedirectUrl}`);

        // ------------------------------------------------------------------
        // TEST 12: Authenticated Operator Login and Tenant-Matched Queue Access
        // ------------------------------------------------------------------
        console.log('▶ [TEST 12] Logging in as NX0001 admin and verifying operator queue...');
        await page.goto(`${BASE_URL}/login`);
        const adminBtn = page.locator('button:has-text("Admin")');
        if (await adminBtn.count() > 0) {
            await adminBtn.first().click();
        } else {
            await page.fill('#clinic_code', 'NX0001');
            await page.fill('#email', 'ADM0001');
            await page.fill('#password', 'password');
        }
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1000);

        // Access same clinic queue
        const respAuthQueue = await page.goto(`${BASE_URL}/clinic/sharmacare/operator/queue`);
        const statusAuthQueue = respAuthQueue.status();
        const contentAuthQueue = await page.content();
        const hasQueueTable = contentAuthQueue.includes('table_arrival_queue') || contentAuthQueue.includes('Arrival Queue') || contentAuthQueue.includes('OPD Queue');
        recordResult('Authenticated operator accesses own clinic queue (200)', statusAuthQueue === 200 && hasQueueTable, `status: ${statusAuthQueue}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_operator_queue_sharmacare.png') });

        // ------------------------------------------------------------------
        // TEST 13: Authenticated Operator cross-clinic queue denied with 403 (Section 26, 27)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 13] Testing cross-tenant queue access (/clinic/arogya/operator/queue) is denied...');
        const respCrossQueue = await page.goto(`${BASE_URL}/clinic/arogya/operator/queue`);
        const statusCrossQueue = respCrossQueue.status();
        recordResult('Cross-clinic operator queue access denied (403)', statusCrossQueue === 403, `status: ${statusCrossQueue}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_cross_tenant_denied_403.png') });

        // ------------------------------------------------------------------
        // TEST 14: Authenticated Operator accessing /clinic/NX0001/operator/queue denied
        // ------------------------------------------------------------------
        console.log('▶ [TEST 14] Testing internal code queue access (/clinic/NX0001/operator/queue) is denied (404)...');
        const respCodeQueue = await page.goto(`${BASE_URL}/clinic/NX0001/operator/queue`);
        const statusCodeQueue = respCodeQueue.status();
        recordResult('Internal code route /clinic/NX0001/operator/queue denied (404)', statusCodeQueue === 404, `status: ${statusCodeQueue}`);

    } catch (err) {
        console.error('Fatal E2E error:', err);
        recordResult('Suite Execution without unhandled error', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  E2E TEST RUN COMPLETED: ${summary.passed}/${summary.total} PASSED (${summary.failed} FAILED)`);
    console.log('======================================================================\n');

    const reportPath = path.join(SCREENSHOT_DIR, 'revision47_e2e_report.json');
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
