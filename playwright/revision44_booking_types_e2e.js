import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/revision44');

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
    console.log('  DXCLINIC REVISION 44 — EXTENSIBLE BOOKING TYPES & TEMPLATES E2E     ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Revision44-E2E/1.0 (Google-Chrome-Desktop)'
    });

    const page = await context.newPage();

    try {
        // ------------------------------------------------------------------
        // PHASE 1: Admin Login
        // ------------------------------------------------------------------
        console.log('[PHASE 1] Admin Authentication');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        const adminBtn = page.locator('button:has-text("Admin")');
        if (await adminBtn.count() > 0) {
            await adminBtn.first().click();
        } else {
            await page.fill('#email', 'ADM0001');
            await page.fill('#password', 'password');
        }
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/dashboard', { timeout: 15000 });

        const isLoggedIn = page.url().includes('/dashboard') || page.url().includes('/settings');
        recordResult('1. Admin Login Success', isLoggedIn, `URL: ${page.url()}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_admin_dashboard.png') });

        // ------------------------------------------------------------------
        // PHASE 2: Sidebar Navigation
        // ------------------------------------------------------------------
        console.log('\n[PHASE 2] Sidebar Navigation to Booking Types');
        const settingsBtn = page.locator('button:has-text("Settings")');
        if (await settingsBtn.isVisible()) {
            await settingsBtn.click();
            await page.waitForTimeout(500);
        }

        const bookingTypesLink = page.locator('a[href*="/settings/booking-types"]');
        recordResult('2. Booking Types Sidebar Link Exists', await bookingTypesLink.count() > 0, 'Found sidebar link');

        await page.goto(`${BASE_URL}/settings/booking-types`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_booking_types_manager.png') });

        const pageHeading = await page.textContent('h1');
        recordResult('2. Booking Types Manager Heading', pageHeading.includes('Booking Types'), `Heading: ${pageHeading.trim()}`);

        // ------------------------------------------------------------------
        // PHASE 3: System Booking Types Verification
        // ------------------------------------------------------------------
        console.log('\n[PHASE 3] System Booking Types Verification');
        const pageText = await page.content();
        recordResult('3. Clinic Consultancy System Type Present', pageText.includes('Clinic Consultancy'), 'Found Clinic Consultancy');
        recordResult('3. Online Consultancy System Type Present', pageText.includes('Online Consultancy'), 'Found Online Consultancy');
        recordResult('3. Emergency Consultancy System Type Present', pageText.includes('Emergency Consultancy'), 'Found Emergency Consultancy');

        // Verify System badges
        const systemBadges = page.locator('span:has-text("System")');
        recordResult('3. System Badges Rendered', await systemBadges.count() >= 3, `Count: ${await systemBadges.count()}`);

        // ------------------------------------------------------------------
        // PHASE 4: Doctor Availability Matrix
        // ------------------------------------------------------------------
        console.log('\n[PHASE 4] Doctor Availability Matrix');
        const availTable = page.locator('h2:has-text("Doctor Availability per Booking Type")');
        recordResult('4. Doctor Availability Section Present', await availTable.count() > 0, 'Found Doctor Availability table');

        const manageButtons = page.locator('button:has-text("Manage")');
        recordResult('4. Doctor Manage Availability Button Present', await manageButtons.count() > 0, `Found ${await manageButtons.count()} manage buttons`);

        // ------------------------------------------------------------------
        // PHASE 5: Add Custom Booking Type via Modal
        // ------------------------------------------------------------------
        console.log('\n[PHASE 5] Add Custom Booking Type Modal');
        const addBtn = page.locator('#btn_add_booking_type');
        if (await addBtn.isVisible()) {
            await addBtn.click();
            await page.waitForTimeout(500);

            const modalTitle = page.locator('h3:has-text("Add Custom Booking Type")');
            recordResult('5. Create Modal Opened', await modalTitle.isVisible(), 'Modal visible');

            await page.fill('#input_new_bt_code', 'HOME_CARE');
            await page.fill('#input_new_bt_name', 'Home Healthcare Visit');
            await page.selectOption('#select_new_bt_mode', 'VISITING_CONSULTANCY');

            const submitBtn = page.locator('#btn_confirm_create_bt');
            await submitBtn.click();
            await page.waitForTimeout(1000);

            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_custom_type_created.png') });
            const updatedContent = await page.content();
            recordResult('5. Custom Booking Type Created', updatedContent.includes('Home Healthcare Visit'), 'Found Home Healthcare Visit');
        } else {
            recordResult('5. Add Booking Type Button Visible', false, 'Add button not visible');
        }

        // ------------------------------------------------------------------
        // PHASE 6: Operator Desk Booking Flow
        // ------------------------------------------------------------------
        console.log('\n[PHASE 6] Operator Desk Booking Flow');
        await page.goto(`${BASE_URL}/operator/booking`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_operator_booking.png') });

        const opContent = await page.content();
        recordResult('6. Operator Desk Canonical Clinic Consultancy', opContent.includes('Clinic Consultancy'), 'Found Clinic Consultancy');
        recordResult('6. Operator Desk Online Consultancy Option', opContent.includes('Online Consultancy'), 'Found Online Consultancy');
        recordResult('6. Operator Desk Emergency Option', opContent.includes('Emergency Consultancy'), 'Found Emergency Consultancy');

        // Check booking type radio buttons
        const btRadios = page.locator('input[name="selected_booking_type_id"]');
        recordResult('6. Booking Type Radio Buttons Present', await btRadios.count() >= 3, `Count: ${await btRadios.count()}`);

        // ------------------------------------------------------------------
        // PHASE 7: Public PWA Booking Flow
        // ------------------------------------------------------------------
        console.log('\n[PHASE 7] Public PWA Booking Flow');
        await page.goto(`${BASE_URL}/book`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_patient_pwa_booking.png') });

        const pwaContent = await page.content();
        recordResult('7. PWA Canonical Clinic Consultancy', pwaContent.includes('Clinic Consultancy'), 'Found Clinic Consultancy');
        recordResult('7. PWA Online Consultancy Option', pwaContent.includes('Online Consultancy'), 'Found Online Consultancy');
        recordResult('7. PWA Excludes Emergency Consultancy', !pwaContent.includes('Emergency Consultancy'), 'Emergency Consultancy safely hidden from public PWA');

        // ------------------------------------------------------------------
        // PHASE 8: Prescription Pad Templates Manager Navigation
        // ------------------------------------------------------------------
        console.log('\n[PHASE 8] Prescription Pad Templates Manager');
        await page.goto(`${BASE_URL}/settings/prescription-pad-templates`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_prescription_pad_templates.png') });

        const templateManagerHeading = await page.textContent('h1');
        recordResult('8. Prescription Pad Templates Route Functional', templateManagerHeading.includes('Prescription Pad Templates'), `Heading: ${templateManagerHeading.trim()}`);

    } catch (err) {
        console.error('[CRITICAL ERROR]', err);
        recordResult('Suite Execution Exception', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  REVISION 44 E2E SUMMARY: Total: ${summary.total} | Passed: ${summary.passed} | Failed: ${summary.failed}`);
    console.log('======================================================================\n');

    fs.writeFileSync(
        path.join(SCREENSHOT_DIR, 'summary.json'),
        JSON.stringify(summary, null, 2)
    );

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite();
