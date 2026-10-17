import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8088';
const SCREENSHOT_DIR = path.resolve('test-results/revision28');

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
    console.log('  DXCLINIC REVISION 28 — HISTORICAL TEMPLATE IMMUTABILITY E2E SUITE   ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        headless: true,
    });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
    });
    const page = await context.newPage();

    try {
        // Phase 1: Authentication
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
        recordResult('Admin Login', page.url().includes('/dashboard'), 'Logged in as Admin');

        // Phase 2: Historical Legacy Appointment Fallback
        console.log('\n[PHASE 2] Historical Legacy Record Fallback (/print/doctor-pad/1)');
        await page.goto(`${BASE_URL}/print/doctor-pad/1`, { waitUntil: 'networkidle' });
        const padContent = await page.content();
        const isLegacy = padContent.includes('class="preprinted-mode"') && padContent.includes('field-patient-name');
        recordResult('Legacy Appointment 1 Uses Legacy Blade View', isLegacy, 'Renders print.doctor-pad with preprinted-mode');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_legacy_pad_fallback.png') });

        // Phase 3: Legacy Doctor Pad PDF Generation
        console.log('\n[PHASE 3] Legacy Doctor Pad PDF Generation (/pdf/doctor-pad/1)');
        const pdfResp = await page.request.get(`${BASE_URL}/pdf/doctor-pad/1`);
        const pdfBuffer = await pdfResp.body();
        const isPdf = pdfBuffer.slice(0, 5).toString('ascii') === '%PDF-';
        recordResult('Legacy Appointment 1 Generates Valid PDF', pdfResp.status() === 200 && isPdf, `HTTP ${pdfResp.status()}, Magic: %PDF- (${pdfBuffer.length} bytes)`);

        // Phase 4: Prescription Pad Template Manager Access
        console.log('\n[PHASE 4] Prescription Pad Template Manager');
        await page.goto(`${BASE_URL}/settings/prescription-pad-templates`, { waitUntil: 'networkidle' });
        const managerContent = await page.content();
        recordResult('Template Manager Navigation', managerContent.includes('Prescription Pad Templates') && managerContent.includes('New Template'), 'Template Manager active');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_template_manager.png') });

        // Phase 5: Populated Encounter 84 Verification
        console.log('\n[PHASE 5] Populated Encounter 84 Doctor Pad (/pdf/doctor-pad/84)');
        const enc84Resp = await page.request.get(`${BASE_URL}/pdf/doctor-pad/84`);
        const enc84Buffer = await enc84Resp.body();
        const enc84Pdf = enc84Buffer.slice(0, 5).toString('ascii') === '%PDF-';
        recordResult('Encounter 84 Doctor Pad PDF Generation', enc84Resp.status() === 200 && enc84Pdf, `HTTP ${enc84Resp.status()}, Magic: %PDF- (${enc84Buffer.length} bytes)`);
        
    } catch (err) {
        console.error('Fatal Test Exception:', err);
        recordResult('Suite Fatal Exception', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log('REVISION 28 E2E TEST SUMMARY:');
    console.log(`  Passed:  ${summary.passed}`);
    console.log(`  Failed:  ${summary.failed}`);
    console.log(`  Total:   ${summary.total}`);
    console.log('======================================================================\n');

    process.exit(summary.failed > 0 ? 1 : 0);
}

runSuite();
