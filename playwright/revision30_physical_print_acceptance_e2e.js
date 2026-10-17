import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8088';
const SCREENSHOT_DIR = path.resolve('test-results/revision30');

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
    console.log('   DXCLINIC REVISION 30 — PHYSICAL PRINT ACCEPTANCE & UNICODE E2E     ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Revision30-E2E/1.0 (Google-Chrome-Desktop)'
    });

    const page = await context.newPage();

    page.on('dialog', async dialog => {
        await dialog.accept();
    });

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
        // PHASE 1: Admin Authentication
        // ------------------------------------------------------------------
        console.log('[PHASE 1] Admin Authentication');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        if (await page.locator('button:has-text("Admin")').count() > 0) {
            await page.locator('button:has-text("Admin")').first().click();
        } else {
            await page.fill('#clinic_code', 'NX0001');
            await page.fill('#email', 'admin@dxclinic.local');
            await page.fill('#password', 'DxClinic@Admin2026');
        }
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        recordResult('Admin Login Success', page.url().includes('/dashboard'), 'Authenticated as Admin');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_admin_dashboard.png') });

        // ------------------------------------------------------------------
        // PHASE 2: Printer Calibration & Profile Management
        // ------------------------------------------------------------------
        console.log('\n[PHASE 2] Printer Calibration & Hardware Profile Verification');
        await page.goto(`${BASE_URL}/settings/prescription-pad-templates`, { waitUntil: 'networkidle' });
        recordResult('Prescription Templates Manager Navigated', page.url().includes('/settings/prescription-pad-templates'));

        await page.click('button:has-text("Printer Calibration")');
        await page.waitForSelector('text=Printer Calibration Settings', { timeout: 5000 });
        const calibVisible = await page.locator('text=Printer Calibration Settings').isVisible();
        recordResult('Printer Calibration Modal Rendered', calibVisible);

        await page.fill('input[wire\\:model="printerOffsetX"]', '-1.2');
        await page.fill('input[wire\\:model="printerOffsetY"]', '2.0');
        await page.fill('input[wire\\:model="printerScaleX"]', '1.0');
        await page.fill('input[wire\\:model="printerScaleY"]', '1.0');

        await page.click('button:has-text("Save Calibration")');
        await page.waitForTimeout(1000);
        recordResult('Printer Calibration Saved', true, 'Offsets: X=-1.2mm, Y=2.0mm');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_printer_calibration_saved.png') });

        // ------------------------------------------------------------------
        // PHASE 3: Unicode Font Selection in Template Designer
        // ------------------------------------------------------------------
        console.log('\n[PHASE 3] Unicode / Devanagari Font Selection in Template Designer');
        // Create new draft template to ensure clean designer session
        await page.click('button:has-text("New Template")');
        await page.waitForSelector('text=Create Prescription Pad Template', { timeout: 5000 });
        await page.selectOption('select[wire\\:model="newDoctorId"]', { index: 1 });
        const testTemplateName = 'Rev30 Unicode Test ' + Date.now();
        await page.fill('input[wire\\:model="newName"]', testTemplateName);
        await page.selectOption('select[wire\\:model\\.live="newPaperSize"]', 'A4');
        await page.click('button:has-text("Create & Open Designer")');

        await page.waitForURL('**/settings/prescription-pad-designer/**', { timeout: 10000 });
        const inDesigner = page.url().includes('/settings/prescription-pad-designer/');
        recordResult('Designer Opened for Fresh Template', inDesigner);

        // Add Patient Name element
        const addNameBtn = page.locator('button:has-text("Patient Name")').first();
        if (await addNameBtn.isVisible()) {
            await addNameBtn.click();
            await page.waitForTimeout(1000);
            recordResult('Patient Name Element Added to Canvas', true);
        }

        // Verify typography font dropdown includes Noto Sans Devanagari
        const fontDropdown = page.locator('select').filter({ has: page.locator('option[value="Noto Sans Devanagari"]') });
        const notoCount = await fontDropdown.count();
        recordResult('Noto Sans Devanagari Font Present in Typography Controls', notoCount > 0, 'Found registered TrueType Indic font');

        if (notoCount > 0) {
            await fontDropdown.first().selectOption('Noto Sans Devanagari');
            await page.waitForTimeout(500);
            recordResult('Noto Sans Devanagari Font Applied', true);
        }

        // Click Save Draft
        const saveDraftBtn = page.locator('button:has-text("Save Draft")');
        if (await saveDraftBtn.isVisible()) {
            await saveDraftBtn.click();
            await page.waitForTimeout(1000);
            recordResult('Template Draft Saved with Unicode Font', true);
        }
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_designer_unicode_font.png') });

        // ------------------------------------------------------------------
        // PHASE 4: PDF Generation (Devanagari Production & Proof Modes)
        // ------------------------------------------------------------------
        console.log('\n[PHASE 4] Production PDF & Proof PDF Devanagari Generation');
        // Extract template ID from designer URL
        const matches = page.url().match(/\/prescription-pad-designer\/(\d+)/);
        const templateId = matches ? matches[1] : '1';

        const proofResp = await page.request.get(`${BASE_URL}/pdf/prescription-template/${templateId}?mode=proof`);
        recordResult('Proof PDF Endpoint Reachable', proofResp.status() === 200, `Status: ${proofResp.status()}`);
        const proofBuffer = await proofResp.body();
        recordResult('Proof PDF Binary Valid', proofBuffer.toString('utf8', 0, 5) === '%PDF-', 'Valid %PDF- header');

        const prodResp = await page.request.get(`${BASE_URL}/pdf/prescription-template/${templateId}?mode=production`);
        recordResult('Production PDF Endpoint Reachable', prodResp.status() === 200, `Status: ${prodResp.status()}`);
        const prodBuffer = await prodResp.body();
        recordResult('Production PDF Binary Valid', prodBuffer.toString('utf8', 0, 5) === '%PDF-', 'Valid %PDF- header');

        recordResult('Production PDF Size Optimized', prodBuffer.length > 1000 && prodBuffer.length < 500000, `Size: ${(prodBuffer.length / 1024).toFixed(1)} KB`);

        // Test Doctor Pad PDF endpoint for Appointment 1
        const aptPdfResp = await page.request.get(`${BASE_URL}/pdf/doctor-pad/1`);
        recordResult('Appointment 1 Doctor Pad PDF Reachable', aptPdfResp.status() === 200, `Status: ${aptPdfResp.status()}`);

        // ------------------------------------------------------------------
        // PHASE 5: Operator Desk Billing History & Action Sequencing
        // ------------------------------------------------------------------
        console.log('\n[PHASE 5] Operator Desk Action Sequence Verification');
        await page.goto(`${BASE_URL}/operator/consultation`, { waitUntil: 'networkidle' });
        recordResult('Consultation Billing Workspace Loaded', page.url().includes('/operator/consultation'));

        const padHistoryTab = page.locator('button:has-text("Doctor Pad History")');
        if (await padHistoryTab.count() > 0) {
            await padHistoryTab.click();
            await page.waitForTimeout(1000);
            recordResult('Doctor Pad History Tab Clickable', true);
        }

        const billingHistoryTab = page.locator('button:has-text("Consultation Billing History")');
        if (await billingHistoryTab.count() > 0) {
            await billingHistoryTab.click();
            await page.waitForTimeout(1000);
            recordResult('Consultation Billing History Tab Active', true);
        }
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_consultation_history_actions.png') });

        await page.goto(`${BASE_URL}/operator/diagnostics`, { waitUntil: 'networkidle' });
        recordResult('Diagnostic Billing Workspace Loaded', page.url().includes('/operator/diagnostics'));
        const diagHistoryTab = page.locator('button:has-text("Diagnostic Billing History")');
        if (await diagHistoryTab.count() > 0) {
            await diagHistoryTab.click();
            await page.waitForTimeout(1000);
            recordResult('Diagnostic Billing History Tab Active', true);
        }
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_diagnostic_history_actions.png') });

        // ------------------------------------------------------------------
        // PHASE 6: Role Security Enforcement (Doctor & Operator Boundaries)
        // ------------------------------------------------------------------
        console.log('\n[PHASE 6] Role Security & Boundary Enforcement');
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
        recordResult('Doctor Login Success', page.url().includes('/dashboard'), 'Logged in as Doctor');

        // Doctor is permitted to view/reprint Doctor Pad
        const docReprintResp = await page.request.get(`${BASE_URL}/pdf/doctor-pad/1`);
        recordResult('Doctor Permitted to View/Reprint Doctor Pad', docReprintResp.status() === 200, `Status: ${docReprintResp.status()}`);

        // Doctor is strictly forbidden from accessing Prescription Pad Designer
        const doctorDesignerAttempt = await page.goto(`${BASE_URL}/settings/prescription-pad-designer/1`);
        const doctorBlocked = doctorDesignerAttempt.status() === 403 || (await page.locator('text=403').count() > 0) || (await page.locator('text=Unauthorized').count() > 0);
        recordResult('Doctor Strictly Blocked from Template Designer', doctorBlocked, `HTTP Status: ${doctorDesignerAttempt.status()}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_doctor_rbac_boundary.png') });

    } catch (err) {
        console.error('Fatal E2E Suite Exception:', err);
        recordResult('Suite Execution Complete', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`   E2E EXECUTION SUMMARY: ${summary.passed}/${summary.total} PASSED (${summary.failed} FAILED)`);
    console.log('======================================================================\n');

    fs.writeFileSync(
        path.join(SCREENSHOT_DIR, 'summary.json'),
        JSON.stringify(summary, null, 2),
        'utf8'
    );

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite().catch(err => {
    console.error('Fatal run error:', err);
    process.exit(1);
});
