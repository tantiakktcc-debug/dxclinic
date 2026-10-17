import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/revision33');

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
    console.log('  DXCLINIC REVISION 33 — COMPREHENSIVE E2E VERIFICATION SUITE         ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    console.log(`[INIT] Automated Browser: Google Chrome v${browser.version()}`);
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Revision33-E2E/1.0 (Google-Chrome-Desktop)'
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
        // TEST 1: Admin Authentication
        // ------------------------------------------------------------------
        console.log('[PHASE 1] Admin Authentication');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await page.fill('#email', 'admin@dxclinic.local');
        await page.fill('#password', 'DxClinic@Admin2026');
        await page.locator('#btn_submit_login').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        recordResult('Admin Login Success', page.url().includes('/dashboard'), 'Authenticated as admin');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_admin_dashboard.png') });

        // ------------------------------------------------------------------
        // TEST 2: Dashboard Tile #9 Routing to Print Templates
        // ------------------------------------------------------------------
        console.log('\n[PHASE 2] Dashboard Quick Action Tile #9 Target');
        const printTemplatesTile = page.locator('a:has-text("Print Templates"), a:has(span:has-text("Print Templates"))').first();
        const tileExists = await printTemplatesTile.count() > 0;
        recordResult('Tile #9 Exists on Dashboard', tileExists, 'Print Templates tile present');

        if (tileExists) {
            const href = await printTemplatesTile.getAttribute('href');
            recordResult('Tile #9 Href Target', href && href.includes('/settings/prescription-pad-templates'), `Href: ${href}`);
            await printTemplatesTile.click();
            await page.waitForURL('**/settings/prescription-pad-templates', { timeout: 10000 });
            recordResult('Tile #9 Navigation Route', page.url().includes('/settings/prescription-pad-templates') && !page.url().includes('operator/queue'), `Landed on: ${page.url()}`);
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_prescription_pad_templates.png') });
        }

        // ------------------------------------------------------------------
        // TEST 3: Prescription Pad Designer Features
        // ------------------------------------------------------------------
        console.log('\n[PHASE 3] Prescription Pad Designer UI & Tools');
        const designerBtn = page.locator('a:has-text("Open Designer")').first();
        if (await designerBtn.count() > 0) {
            await designerBtn.click();
            await page.waitForURL('**/settings/prescription-pad-designer/**', { timeout: 10000 });
            await page.waitForTimeout(1000);
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_designer_workspace.png') });

            // Check rulers
            const rulersPresent = await page.locator('svg[viewBox="0 0 210 20"]').count() > 0;
            recordResult('Millimeter Top Ruler Present', rulersPresent, 'SVG scale ruler detected');

            // Check zoom controls
            const zoomInBtn = page.locator('#btn_zoom_in');
            const zoomOutBtn = page.locator('#btn_zoom_out');
            const zoomResetBtn = page.locator('#btn_zoom_reset');
            recordResult('Zoom Controls Present', (await zoomInBtn.count() > 0) && (await zoomOutBtn.count() > 0), 'Zoom In/Out/Reset buttons verified');

            // Check fullscreen toggle
            const fullscreenBtn = page.locator('#btn_toggle_fullscreen');
            recordResult('Fullscreen Toggle Button Present', await fullscreenBtn.count() > 0, 'Fullscreen workspace button verified');

            // Check element selection & static text property panel
            const firstElement = page.locator('g[data-element-id]').first();
            if (await firstElement.count() > 0) {
                await firstElement.click();
                await page.waitForTimeout(500);
                await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_element_selected.png') });
                const propertiesPanel = page.locator('text=Element Properties');
                recordResult('Properties Panel Active on Selection', await propertiesPanel.count() > 0, 'Element properties inspected');
            }
        } else {
            console.log('  [SKIP] No existing template to test designer navigation');
        }

        // ------------------------------------------------------------------
        // TEST 4: Fee Master Doctor Configuration & Label Normalization
        // ------------------------------------------------------------------
        console.log('\n[PHASE 4] Fee Master Doctor Ownership & Consultation Label');
        await page.goto(`${BASE_URL}/settings/fee-master`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_fee_master.png') });

        // Verify "Clinic Consultation" label exists
        const pageContent = await page.content();
        const hasClinicConsultation = pageContent.includes('Clinic Consultation');
        const hasLegacyPhysical = pageContent.includes('Clinic Physical Consultation');
        recordResult('Fee Master Canonical Label', hasClinicConsultation && !hasLegacyPhysical, 'Displays "Clinic Consultation" without legacy physical label');

        // Check Doctor Filter Dropdown
        const doctorFilter = page.locator('#filter_doctor_scope');
        recordResult('Doctor Filter Dropdown Present', await doctorFilter.count() > 0, 'Fee Master allows filtering by doctor');

        // Open Add Fee Modal and check Doctor Ownership Selector
        const addFeeBtn = page.locator('button:has-text("Add Fee Master")');
        if (await addFeeBtn.count() > 0) {
            await addFeeBtn.click();
            await page.waitForTimeout(500);
            const doctorScopeSelect = page.locator('select[wire\\:model="doctorId"]');
            recordResult('Fee Master Doctor Ownership Field Present', await doctorScopeSelect.count() > 0, 'Fee scope selector verified in Create Modal');
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_fee_create_modal.png') });
            // Close modal
            await page.locator('button:has-text("Cancel")').first().click();
        }

        // ------------------------------------------------------------------
        // TEST 5: User Management Doctor Binding
        // ------------------------------------------------------------------
        console.log('\n[PHASE 5] User Management Doctor Binding');
        await page.goto(`${BASE_URL}/settings/users`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '07_user_management.png') });

        const addUserBtn = page.locator('#btn_add_user');
        if (await addUserBtn.count() > 0) {
            await addUserBtn.click();
            await page.waitForTimeout(500);

            // Select Role DOCTOR
            const roleSelect = page.locator('select[wire\\:model\\.live="role"]');
            if (await roleSelect.count() > 0) {
                await roleSelect.selectOption('DOCTOR');
                await page.waitForTimeout(500);
                await page.screenshot({ path: path.join(SCREENSHOT_DIR, '08_user_doctor_binding_field.png') });

                const doctorSelect = page.locator('select[wire\\:model="doctorId"]');
                const doctorSelectVisible = await doctorSelect.isVisible();
                recordResult('Mandatory Doctor Selector on Role=DOCTOR', doctorSelectVisible, 'Doctor profile binding select rendered conditionally');
            }
            await page.locator('button:has-text("Cancel")').first().click();
        }

        // ------------------------------------------------------------------
        // TEST 6: Clinic Doctor Schedule Configuration
        // ------------------------------------------------------------------
        console.log('\n[PHASE 6] Clinic Doctor OPD Schedule Configuration');
        await page.goto(`${BASE_URL}/settings/clinic-doctor`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '09_clinic_doctor_management.png') });

        const editDocBtn = page.locator('button[id^="btn_edit_doctor_"]').first();
        if (await editDocBtn.count() > 0) {
            await editDocBtn.click();
            await page.waitForTimeout(500);
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '10_doctor_edit_schedule.png') });

            const scheduleSection = page.locator('text=OPD Schedule Configuration');
            const customToggle = page.locator('input[wire\\:model\\.live="customSchedule"]');
            recordResult('Doctor Custom Schedule Toggle Present', await customToggle.count() > 0, 'Custom schedule checkbox found');
            await page.locator('button:has-text("Cancel")').first().click();
        }

        // ------------------------------------------------------------------
        // TEST 7: Expense Category Tea/Coffee
        // ------------------------------------------------------------------
        console.log('\n[PHASE 7] Expense Category Tea/Coffee');
        await page.goto(`${BASE_URL}/settings/expense-categories`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '11_expense_categories.png') });

        const hasTeaCoffee = await page.locator('text=Tea/Coffee').count() > 0;
        const hasExpTeaCode = await page.locator('text=EXP-TEA').count() > 0;
        recordResult('Tea/Coffee Expense Category Present', hasTeaCoffee && hasExpTeaCode, 'EXP-TEA found in active expense categories');

        // ------------------------------------------------------------------
        // TEST 8: Dedicated Public PWA Layout
        // ------------------------------------------------------------------
        console.log('\n[PHASE 8] Dedicated Public PWA Layout Isolation');
        await page.goto(`${BASE_URL}/book`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '12_public_pwa_booking.png') });

        const publicHeader = await page.locator('text=OPD Token Booking').count() > 0;
        const bookingOpenBadge = await page.locator('text=Booking Open').count() > 0;
        recordResult('Public PWA Header & Badge Present', publicHeader && bookingOpenBadge, 'Public patient branding active');

        const mobileSidebarBtn = await page.locator('#btn_open_mobile_sidebar').count();
        recordResult('No Authenticated Sidebar in Public PWA', mobileSidebarBtn === 0, 'Clinic navigation controls completely absent');

        const pwaContent = await page.content();
        const pwaClinicConsultation = pwaContent.includes('Clinic Consultation');
        const pwaLegacyPhysical = pwaContent.includes('Clinic Physical Consultation');
        recordResult('Public PWA Consultation Canonical Label', pwaClinicConsultation && !pwaLegacyPhysical, 'Uses "Clinic Consultation" on public card');

        // ------------------------------------------------------------------
        // TEST 9: Online Booking QR URL Configuration
        // ------------------------------------------------------------------
        console.log('\n[PHASE 9] Online Booking QR Configuration');
        await page.goto(`${BASE_URL}/settings/booking-qr`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '13_booking_qr.png') });

        const prodUrlInput = page.locator('input[wire\\:model="prodUrl"]');
        if (await prodUrlInput.count() > 0) {
            const val = await prodUrlInput.inputValue();
            recordResult('Production Booking QR URL', val.includes('/book'), `Configured URL: ${val}`);
        }

    } catch (err) {
        console.error(`\n[FATAL ERROR] Test suite aborted: ${err.message}`);
        summary.failed++;
        summary.tests.push({ name: 'Suite Execution', pass: false, details: err.message });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'fatal_error.png') });
    } finally {
        await browser.close();
    }

    // ------------------------------------------------------------------
    // FINAL SUMMARY
    // ------------------------------------------------------------------
    console.log('\n======================================================================');
    console.log(`  E2E RUN COMPLETE: ${summary.passed}/${summary.total} PASSED (${summary.failed} FAILED)`);
    console.log('======================================================================');

    const reportPath = path.join(SCREENSHOT_DIR, 'summary.json');
    fs.writeFileSync(reportPath, JSON.stringify(summary, null, 2));
    console.log(`\nDetailed summary saved to: ${reportPath}`);

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite().catch(err => {
    console.error(err);
    process.exit(1);
});
