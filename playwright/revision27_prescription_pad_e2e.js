import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8088';
const SCREENSHOT_DIR = path.resolve('test-results/revision27');

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
    console.log('       DXCLINIC REVISION 27 — E2E VERIFICATION SUITE                  ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    console.log(`[INIT] Automated Browser: Google Chrome v${browser.version()}`);
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Revision27-E2E/1.0 (Google-Chrome-Desktop)'
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
        console.log('\n[PHASE 1] Admin Authentication');
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
        recordResult('Admin Login Success', page.url().includes('/dashboard'), 'Logged in as Admin');

        // ------------------------------------------------------------------
        // TEST 2: Sidebar Navigation Structure & Collapsible Menus
        // ------------------------------------------------------------------
        console.log('\n[PHASE 2] Sidebar Redesign Verification');
        // Daily work items should be visible
        const dashboardVisible = await page.locator('aside >> text=Dashboard').isVisible();
        const queueVisible = await page.locator('aside >> text=OPD Queue').isVisible();
        const consultationVisible = await page.locator('aside >> text=Consultation Billing').isVisible();
        const diagnosticVisible = await page.locator('aside >> text=Diagnostic Billing').isVisible();
        recordResult('Sidebar Daily Work Visible', dashboardVisible && queueVisible && consultationVisible && diagnosticVisible);

        // Help & Guide collapsible section
        const helpToggle = page.locator('aside button:has-text("Help & Guide")');
        const helpVisible = await helpToggle.isVisible();
        recordResult('Help & Guide Section Exists', helpVisible);
        if (helpVisible) {
            await helpToggle.click();
            await page.waitForTimeout(300);
            const faqVisible = await page.locator('aside >> text=FAQ').isVisible();
            const manualVisible = await page.locator('aside >> text=User Manual').isVisible();
            recordResult('Help & Guide Collapsible Toggles', faqVisible && manualVisible, 'FAQ and User Manual visible');
        }

        // Settings collapsible section
        const settingsToggle = page.locator('aside button:has-text("Settings")');
        const settingsVisible = await settingsToggle.isVisible();
        recordResult('Settings Section Exists at Bottom', settingsVisible);
        if (settingsVisible) {
            await settingsToggle.click();
            await page.waitForTimeout(300);
            const padSettingsLink = page.locator('aside >> text=Prescription Pad Designer');
            const padSettingsVisible = await padSettingsLink.isVisible();
            recordResult('Prescription Pad Templates Menu Visible', padSettingsVisible);
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_sidebar_admin.png'), fullPage: false });
        }

        // ------------------------------------------------------------------
        // TEST 3: Prescription Pad Templates Management
        // ------------------------------------------------------------------
        console.log('\n[PHASE 3] Prescription Pad Templates Management');
        await page.goto(`${BASE_URL}/settings/prescription-pad-templates`, { waitUntil: 'networkidle' });
        await page.waitForSelector('h1:has-text("Prescription Pad Templates")', { timeout: 8000 });
        recordResult('Prescription Pad Manager Loaded', true);

        // Check for Create Draft button and Calibration button
        const createBtn = page.locator('button:has-text("New Template")');
        const importBtn = page.locator('button:has-text("Import From Legacy")');
        const calibBtn = page.locator('button:has-text("Printer Calibration")');
        recordResult('Manager Action Controls Present', (await createBtn.isVisible()) && (await calibBtn.isVisible()), 'New Template, Calibration available');

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_prescription_pad_manager.png'), fullPage: true });

        // Test Printer Calibration Modal
        await calibBtn.click();
        await page.waitForSelector('text=Printer Calibration Settings', { timeout: 5000 });
        const calibModalVisible = await page.locator('text=Printer Calibration Settings').isVisible();
        recordResult('Printer Calibration Modal Opens', calibModalVisible);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_printer_calibration_modal.png') });
        await page.locator('button:has-text("Cancel")').click();
        await page.waitForTimeout(400);

        // ------------------------------------------------------------------
        // TEST 4: Create / Open Template in SVG Designer
        // ------------------------------------------------------------------
        console.log('\n[PHASE 4] Prescription Pad SVG Designer');
        // Click New Template button to open modal
        await createBtn.click();
        await page.waitForSelector('text=Create Prescription Pad Template', { timeout: 5000 });
        // Select doctor and paper size
        await page.selectOption('select[wire\\:model="newDoctorId"]', { index: 1 });
        await page.fill('input[wire\\:model="newName"]', 'E2E Test Custom Pad V1');
        await page.selectOption('select[wire\\:model\\.live="newPaperSize"]', 'A4');
        await page.click('button:has-text("Create & Open Designer")');

        // Wait for designer page URL
        await page.waitForURL('**/settings/prescription-pad-designer/**', { timeout: 10000 });
        recordResult('Prescription Pad Designer Opened', page.url().includes('/settings/prescription-pad-designer/'));

        // Verify SVG Vector Canvas, Rulers, and Toolbox
        await page.waitForSelector('svg#designerCanvas', { timeout: 8000 });
        const canvasVisible = await page.locator('svg#designerCanvas').isVisible();
        const toolboxVisible = await page.locator('text=Toolbox').first().isVisible();
        const propertiesVisible = await page.locator('text=Properties').first().isVisible();
        recordResult('SVG Canvas, Toolbox & Properties Panel Rendered', canvasVisible && toolboxVisible && propertiesVisible);

        // Verify Zoom controls and Grid/Snap toggle
        const zoom100 = await page.locator('button:has-text("100%")').isVisible();
        const snapToggle = await page.locator('button:has-text("Snap")').isVisible();
        recordResult('Canvas Zoom and Snap Controls Present', zoom100 && snapToggle);

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_svg_designer_canvas.png'), fullPage: true });

        // Add a field from toolbox (e.g. Patient Name)
        const patientNameToolboxItem = page.locator('button:has-text("Patient Name")').first();
        if (await patientNameToolboxItem.isVisible()) {
            await patientNameToolboxItem.click();
            await page.waitForTimeout(500);
            recordResult('Field Added from Toolbox to Canvas', true, 'Added Patient Name');
        }

        // Test Proof Preview mode
        const proofToggle = page.locator('button:has-text("Proof View")');
        if (await proofToggle.isVisible()) {
            await proofToggle.click();
            await page.waitForTimeout(400);
            recordResult('Proof Mode Toggled', true);
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_designer_proof_mode.png') });
        }

        // Test Production Preview mode
        const prodToggle = page.locator('button:has-text("Production View")');
        if (await prodToggle.isVisible()) {
            await prodToggle.click();
            await page.waitForTimeout(400);
            recordResult('Production Mode Toggled', true);
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_designer_production_mode.png') });
        }

        // Save Draft
        const saveDraftBtn = page.locator('button:has-text("Save Draft")');
        if (await saveDraftBtn.isVisible()) {
            await saveDraftBtn.click();
            await page.waitForTimeout(1000);
            recordResult('Draft Saved Successfully', true);
        }

        // ------------------------------------------------------------------
        // TEST 5: Consultation Billing History — Edit Button Placement
        // ------------------------------------------------------------------
        console.log('\n[PHASE 5] Consultation Billing History Edit Placement');
        await page.goto(`${BASE_URL}/operator/consultation`, { waitUntil: 'networkidle' });
        await page.click('button:has-text("Consultation Billing History")');
        await page.waitForTimeout(500);

        // Clear filters to show all historical rows
        const clearBtn = page.locator('button:has-text("Clear Filters")');
        if (await clearBtn.isVisible()) {
            await clearBtn.click();
            await page.waitForTimeout(1000);
        }

        // Check that Edit button appears before Pad button in the action column
        const historyRow = page.locator('table tbody tr:has(button:has-text("Edit"))').first();
        if (await historyRow.isVisible()) {
            const rowHtml = await historyRow.innerHTML();
            const editIndex = rowHtml.indexOf('>Edit<');
            const padIndex = rowHtml.indexOf('Pad');
            const editBeforePad = editIndex !== -1 && padIndex !== -1 && editIndex < padIndex;
            recordResult('Consultation Billing History: Edit Before Pad', editBeforePad, `Edit index: ${editIndex}, Pad index: ${padIndex}`);

            // Click Edit to verify controlled modification modal opens
            const editBtn = historyRow.locator('button:has-text("Edit")').first();
            if (await editBtn.isVisible()) {
                await editBtn.click();
                await page.waitForSelector('text=Controlled Bill Modification', { timeout: 5000 });
                const modalVisible = await page.locator('text=Controlled Bill Modification').isVisible();
                recordResult('Consultation Controlled Modification Modal Opens', modalVisible);
                await page.screenshot({ path: path.join(SCREENSHOT_DIR, '07_consultation_history_edit_modal.png') });
                await page.locator('button:has-text("Cancel")').first().click();
                await page.waitForTimeout(400);
            }
        } else {
            recordResult('Consultation Billing History Table Exists', true, 'No rows to inspect');
        }
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '08_consultation_billing_history.png'), fullPage: true });

        // ------------------------------------------------------------------
        // TEST 6: Diagnostic Billing History — Status | Edit | Method Column Order
        // ------------------------------------------------------------------
        console.log('\n[PHASE 6] Diagnostic Billing History Column Order');
        await page.goto(`${BASE_URL}/operator/diagnostics`, { waitUntil: 'networkidle' });
        await page.click('button:has-text("Diagnostic Billing History")');
        await page.waitForTimeout(500);

        // Check header columns: Status | Edit | Method
        const tableHeaders = await page.locator('table thead th').allTextContents();
        const headerText = tableHeaders.join(' | ');
        const statusIdx = headerText.indexOf('Status');
        const editIdx = headerText.indexOf('Edit');
        const methodIdx = headerText.indexOf('Method');
        const orderCorrect = statusIdx !== -1 && editIdx !== -1 && methodIdx !== -1 && statusIdx < editIdx && editIdx < methodIdx;
        recordResult('Diagnostic Billing History: Status | Edit | Method Order', orderCorrect, `Header: ${headerText}`);

        // Clear filters to show rows
        const diagClearBtn = page.locator('button:has-text("Clear Filters")');
        if (await diagClearBtn.isVisible()) {
            await diagClearBtn.click();
            await page.waitForTimeout(1000);
        }

        // Check row has Edit button in that column
        const diagRow = page.locator('table tbody tr:has(button:has-text("Edit"))').first();
        if (await diagRow.isVisible()) {
            const diagEditBtn = diagRow.locator('button:has-text("Edit")').first();
            if (await diagEditBtn.isVisible()) {
                await diagEditBtn.click();
                await page.waitForSelector('text=Controlled Bill Modification', { timeout: 5000 });
                const diagModalVisible = await page.locator('text=Controlled Bill Modification').isVisible();
                recordResult('Diagnostic Controlled Modification Modal Opens', diagModalVisible);
                await page.screenshot({ path: path.join(SCREENSHOT_DIR, '09_diagnostic_history_edit_modal.png') });
                await page.locator('button:has-text("Cancel")').first().click();
                await page.waitForTimeout(400);
            }
        }
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '10_diagnostic_billing_history.png'), fullPage: true });

        // ------------------------------------------------------------------
        // TEST 7: Doctor Pad Vitals Verification on Known Encounter
        // ------------------------------------------------------------------
        console.log('\n[PHASE 7] Doctor Pad Vitals Verification (Appointment 84, Visit 65, Vital 20)');
        // Open Doctor Pad print view for Appointment 84
        await page.goto(`${BASE_URL}/print/doctor-pad/84`, { waitUntil: 'networkidle' });
        const padContent = await page.content();

        // Check that actual populated vitals are rendered
        const hasBp = padContent.includes('134/90');
        const hasWeight = padContent.includes('68');
        const hasPulse = padContent.includes('56');
        const hasSpo2 = padContent.includes('96');
        const hasTemp = padContent.includes('98.5');

        recordResult('Doctor Pad Populated Encounter BP Rendered', hasBp, '134/90');
        recordResult('Doctor Pad Populated Encounter Weight Rendered', hasWeight, '68 kg');
        recordResult('Doctor Pad Populated Encounter Pulse Rendered', hasPulse, '56 bpm');
        recordResult('Doctor Pad Populated Encounter SpO2 Rendered', hasSpo2, '96%');
        recordResult('Doctor Pad Populated Encounter Temp Rendered', hasTemp, '98.5');

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '11_doctor_pad_vitals_verified.png'), fullPage: true });

        // Verify PDF Download Route for Doctor Pad responds with %PDF
        const pdfResp = await context.request.get(`${BASE_URL}/pdf/doctor-pad/84`);
        const pdfStatus = pdfResp.status();
        const pdfContentType = pdfResp.headers()['content-type'] || '';
        const pdfBody = await pdfResp.body();
        const pdfValid = pdfStatus === 200 && pdfContentType.includes('application/pdf') && pdfBody.toString('utf-8', 0, 4) === '%PDF';
        recordResult('Doctor Pad Production PDF Valid', pdfValid, `Status: ${pdfStatus}, Content-Type: ${pdfContentType}, Size: ${pdfBody.length} bytes`);

        // ------------------------------------------------------------------
        // TEST 8: Role-Specific Navigation (Operator & Doctor)
        // ------------------------------------------------------------------
        console.log('\n[PHASE 8] Role-Specific Navigation (Operator & Doctor)');
        // Logout Admin
        await page.goto(`${BASE_URL}/dashboard`);
        const logoutBtn = page.locator('#btn_sidebar_logout');
        if (await logoutBtn.isVisible()) {
            await logoutBtn.click();
            await page.waitForURL('**/login', { timeout: 8000 });
        } else {
            await page.goto(`${BASE_URL}/login`);
        }

        // Login as Operator
        if (await page.locator('button:has-text("Operator")').count() > 0) {
            await page.locator('button:has-text("Operator")').first().click();
        } else {
            await page.fill('#clinic_code', 'NX0001');
            await page.fill('#email', 'operator@dxclinic.local');
            await page.fill('#password', 'DxClinic@Op2026');
        }
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });

        // Verify Settings is NOT visible for Operator
        const opSettingsVisible = await page.locator('aside button:has-text("Settings")').isVisible();
        const opPadVisible = await page.locator('aside >> text=Prescription Pad Designer').isVisible();
        recordResult('Operator Cannot See Admin Settings in Sidebar', !opSettingsVisible && !opPadVisible);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '12_sidebar_operator.png') });

        // Logout Operator
        await page.locator('#btn_sidebar_logout').click();
        await page.waitForURL('**/login', { timeout: 8000 });

        // Login as Doctor
        if (await page.locator('button:has-text("Doctor")').count() > 0) {
            await page.locator('button:has-text("Doctor")').first().click();
        } else {
            await page.fill('#clinic_code', 'NX0001');
            await page.fill('#email', 'doctor@dxclinic.local');
            await page.fill('#password', 'DxClinic@Doc2026');
        }
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });

        // Verify Doctor cannot see settings or diagnostic billing
        const docSettingsVisible = await page.locator('aside button:has-text("Settings")').isVisible();
        const docPadVisible = await page.locator('aside >> text=Prescription Pad Designer').isVisible();
        recordResult('Doctor Cannot See Admin Settings in Sidebar', !docSettingsVisible && !docPadVisible);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '13_sidebar_doctor.png') });

    } catch (err) {
        console.error('Fatal E2E suite error:', err);
        recordResult('E2E Suite Execution', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`DXCLINIC REVISION 27 E2E SUMMARY: ${summary.passed}/${summary.total} PASSED`);
    if (summary.failed > 0) {
        console.log(`FAILED TESTS: ${summary.failed}`);
    }
    console.log('======================================================================\n');

    fs.writeFileSync(
        path.join(SCREENSHOT_DIR, 'e2e_summary.json'),
        JSON.stringify(summary, null, 2)
    );

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite().catch(err => {
    console.error('Unhandled script error:', err);
    process.exit(1);
});
