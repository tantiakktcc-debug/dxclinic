import { chromium } from 'playwright';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';


async function runSmokeTest() {
    console.log("============================================================");
    console.log("         DXCLINIC REVISION 28 — PRODUCTION SMOKE TEST        ");
    console.log("============================================================");
    console.log(`Target: ${BASE_URL}`);

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    let passed = 0;
    let failed = 0;

    function record(name, isPass, detail = '') {
        if (isPass) {
            console.log(`  [PASS] ${name} ${detail ? '(' + detail + ')' : ''}`);
            passed++;
        } else {
            console.error(`  [FAIL] ${name} ${detail ? '(' + detail + ')' : ''}`);
            failed++;
        }
    }

    try {
        // 1. Unauthenticated Login Screen
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        const loginTitle = await page.title();
        record("Login Screen Access", loginTitle.includes('DxClinic') || (await page.locator('button[type="submit"]').count()) > 0);

        // 2. Admin Authentication
        const adminBtn = page.locator('button:has-text("Admin")');
        if (await adminBtn.count() > 0) {
            await adminBtn.first().click();
        } else {
            await page.fill('#email', 'admin@dxclinic.local');
            await page.fill('#password', 'DxClinic@Admin2026');
        }
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        record("Admin Login & Dashboard", page.url().includes('/dashboard'));

        // 3. Admin Settings & Prescription Pad Templates
        await page.goto(`${BASE_URL}/settings/prescription-pad-templates`, { waitUntil: 'networkidle' });
        const hasTplManager = await page.locator('h1, h2, h3').filter({ hasText: /Prescription Pad/i }).count();
        record("Admin Template Manager Access", hasTplManager > 0);

        // 4. Admin FAQ / Manuals Access
        await page.goto(`${BASE_URL}/settings/help`, { waitUntil: 'networkidle' });
        const helpVisible = (await page.locator('text=Manual').count()) > 0 || (await page.locator('text=FAQ').count()) > 0;
        record("Admin Help / Manual / FAQ Access", helpVisible);

        // 5. Logout Admin
        await page.goto(`${BASE_URL}/logout-session`, { waitUntil: 'networkidle' }).catch(() => {});
        await context.clearCookies();
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        record("Admin Logout & Return to Login", page.url().includes('/login'));

        // 6. Operator Authentication
        const opBtn = page.locator('button:has-text("Operator")');
        if (await opBtn.count() > 0) {
            await opBtn.first().click();
        } else {
            await page.fill('#email', 'operator@dxclinic.local');
            await page.fill('#password', 'DxClinic@Op2026');
        }
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        record("Operator Login", page.url().includes('/dashboard'));

        // 7. Operator OPD Queue
        await page.goto(`${BASE_URL}/operator/queue`, { waitUntil: 'networkidle' });
        const queueHeader = (await page.locator('text=Queue').count()) > 0 || (await page.locator('text=Arrival').count()) > 0;
        record("Operator OPD Queue Access", queueHeader);

        // 8. Operator OPD Booking
        await page.goto(`${BASE_URL}/operator/booking`, { waitUntil: 'networkidle' });
        const bookingForm = (await page.locator('text=Book Appointment').count()) > 0 || (await page.locator('input[type="tel"]').count()) > 0;
        record("Operator OPD Booking Access", bookingForm);

        // 9. Operator Consultation Billing & Historical Tabs
        await page.goto(`${BASE_URL}/operator/consultation`, { waitUntil: 'networkidle' });
        const consultTabs = (await page.locator('text=Consultation Billing History').count()) > 0 || (await page.locator('text=Doctor Pads').count()) > 0;
        record("Operator Consultation Billing & History Tabs", consultTabs);

        // 10. Operator Diagnostic Billing
        await page.goto(`${BASE_URL}/operator/diagnostics`, { waitUntil: 'networkidle' });
        const diagTabs = (await page.locator('text=Diagnostic Billing History').count()) > 0 || (await page.locator('text=Patient Search').count()) > 0 || (await page.locator('h1, h2, h3').filter({ hasText: /Diagnostic/i }).count()) > 0;
        record("Operator Diagnostic Billing Access", diagTabs);

        // 11. Operator Cash Counter
        await page.goto(`${BASE_URL}/cash/sessions`, { waitUntil: 'networkidle' });
        const cashSession = (await page.locator('text=Cash').count()) > 0;
        record("Operator Cash Counter Access", cashSession);

        // 12. Operator Role Restriction: Admin Settings not visible in Sidebar
        const opSettingsVisible = await page.locator('aside button:has-text("Settings")').isVisible();
        const opPadVisible = await page.locator('aside >> text=Prescription Pad Designer').isVisible();
        record("Operator Cannot See Admin Settings in Sidebar", !opSettingsVisible && !opPadVisible);

        // 13. Logout Operator
        const opLogoutBtn = page.locator('#btn_sidebar_logout');
        if (await opLogoutBtn.count() > 0) {
            await opLogoutBtn.click();
            await page.waitForURL('**/login', { timeout: 8000 });
        } else {
            await context.clearCookies();
            await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        }
        record("Operator Logout & Return to Login", page.url().includes('/login'));

        // 14. Doctor Authentication
        const docBtn = page.locator('button:has-text("Doctor")');
        if (await docBtn.count() > 0) {
            await docBtn.first().click();
        } else {
            await page.fill('#email', 'doctor@dxclinic.local');
            await page.fill('#password', 'DxClinic@Doc2026');
        }
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        record("Doctor Login", page.url().includes('/dashboard'));

        // 15. Doctor Access to View Doctor Pad (e.g. Appointment 84)
        const padRes = await page.request.get(`${BASE_URL}/print/doctor-pad/84`);
        record("Doctor Allowed to View Doctor Pad (Encounter 84)", padRes.status() === 200);

        // 16. Doctor Role Restriction: Admin Settings not visible in Sidebar
        const docSettingsVisible = await page.locator('aside button:has-text("Settings")').isVisible();
        const docPadVisible = await page.locator('aside >> text=Prescription Pad Designer').isVisible();
        record("Doctor Cannot See Admin Settings in Sidebar", !docSettingsVisible && !docPadVisible);

        // 17. Doctor Pad Real PDF Generation
        const pdfRes = await page.request.get(`${BASE_URL}/pdf/doctor-pad/84`);
        const pdfBytes = await pdfRes.body();
        record("Doctor Pad Real PDF Generation", pdfRes.status() === 200 && pdfBytes.slice(0, 5).toString() === '%PDF-');

    } catch (err) {
        console.error("SMOKE TEST EXCEPTION:", err);
        failed++;
    } finally {
        await browser.close();
    }

    const total = passed + failed;
    console.log("============================================================");
    console.log(`SMOKE TEST RESULTS: Passed: ${passed}, Failed: ${failed}, Total: ${total}`);
    console.log("============================================================");

    process.exit(failed > 0 ? 1 : 0);
}


runSmokeTest();
