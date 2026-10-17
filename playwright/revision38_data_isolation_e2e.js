import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/revision38');

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
    console.log('  DXCLINIC REVISION 38 — DATA ISOLATION & DOCTOR IDENTITY E2E ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    console.log(`[INIT] Automated Browser: Google Chrome v${browser.version()}`);
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Revision38-E2E/1.0 (Google-Chrome-Desktop)'
    });

    const page = await context.newPage();

    page.on('console', msg => {
        if (msg.type() === 'error') {
            summary.consoleErrors.push(`[Console Error] ${msg.text()}`);
        }
    });

    page.on('response', resp => {
        if (resp.status() >= 400 && resp.status() !== 401 && resp.status() !== 403 && resp.status() !== 404 && resp.status() !== 503) {
            summary.networkErrors.push(`[Network ${resp.status()}] ${resp.url()}`);
        }
    });

    try {
        // Step A: Open /book/democlinic
        console.log('\n--- Step A: Open /book/democlinic ---');
        await page.goto(`${BASE_URL}/book/democlinic`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_demo_clinic_pwa.png'), fullPage: true });

        // Step B: Verify Demo Clinic branding
        const pageContent1 = await page.content();
        recordResult('A & B. Demo Clinic PWA branding loaded', pageContent1.includes('Demo Clinic'), 'Found "Demo Clinic"');

        // Step C: Verify Demo doctor #1 and #2 in dropdown/options
        recordResult('C. Demo doctor #1 displayed', pageContent1.includes('Dr. Amit Verma'), 'Found "Dr. Amit Verma"');
        recordResult('C. Demo doctor #2 displayed', pageContent1.includes('Dr. Neha Kapoor'), 'Found "Dr. Neha Kapoor"');

        // Step D: Verify no Rajesh Sharma on Demo PWA
        recordResult('D. No Rajesh Sharma on Demo PWA', !pageContent1.includes('Rajesh Sharma'), 'Rajesh Sharma is absent');

        // Step E & F: Verify Doctor #1 Consultancy labels & fees (Amit Verma offers Clinic & Online)
        recordResult('E. Clinic Consultancy label present', pageContent1.includes('Clinic Consultancy'), 'Found "Clinic Consultancy"');
        recordResult('E. Clinic Consultation label ABSENT', !pageContent1.includes('Clinic Consultation'), '"Clinic Consultation" is absent');
        recordResult('F. Online Consultancy label present for Doctor #1', pageContent1.includes('Online Consultancy'), 'Found "Online Consultancy"');
        recordResult('H. Demo physical consultancy fee ₹500 present', pageContent1.includes('₹500'), 'Found ₹500');
        recordResult('H. Demo online consultancy fee ₹400 present', pageContent1.includes('₹400'), 'Found ₹400');

        // Step G & H: Switch to Doctor #2 (Dr. Neha Kapoor who offers Clinic & Visiting)
        console.log('\n--- Select Doctor #2 (Dr. Neha Kapoor) ---');
        const docSelect = page.locator('#pwa_select_doctor');
        if (await docSelect.count() > 0) {
            // Find option for Neha Kapoor
            const options = await page.$$eval('#pwa_select_doctor option', opts => opts.map(o => ({ value: o.value, text: o.text })));
            const nehaOpt = options.find(o => o.text.includes('Neha Kapoor'));
            if (nehaOpt) {
                await docSelect.selectOption(nehaOpt.value);
                await page.waitForTimeout(1000); // Wait for Livewire hydration
                await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01b_demo_doctor2_selected.png'), fullPage: true });

                const nehaContent = await page.content();
                recordResult('G. Visiting Consultancy label present for Doctor #2', nehaContent.includes('Visiting Consultancy'), 'Found "Visiting Consultancy"');
                recordResult('H. Demo visiting consultancy fee ₹750 present for Doctor #2', nehaContent.includes('₹750'), 'Found ₹750');
            }
        }

        // Step I: Open /book/NX0001
        console.log('\n--- Step I: Open /book/NX0001 ---');
        await page.goto(`${BASE_URL}/book/NX0001`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_nx0001_pwa.png'), fullPage: true });

        // Step J: Verify Sharma Care Clinic
        const pageContent2 = await page.content();
        recordResult('J. NX0001 Sharma Care Clinic loaded', pageContent2.includes('Sharma Care Clinic'), 'Found "Sharma Care Clinic"');

        // Step K: Verify NX0001 doctor
        recordResult('K. NX0001 doctor Rajesh Sharma loaded', pageContent2.includes('Dr. Rajesh Sharma'), 'Found "Dr. Rajesh Sharma"');
        recordResult('K. No double Dr. title on NX0001', !pageContent2.includes('Dr. Dr.'), '"Dr. Dr." is absent');

        // Step L: Verify NX0001 fees
        recordResult('L. NX0001 fee ₹600 loaded', pageContent2.includes('₹600'), 'Found ₹600');
        recordResult('L. NX0001 fee ₹700 loaded', pageContent2.includes('₹700'), 'Found ₹700');
        recordResult('L. Demo fee ₹500 NOT in NX0001', !pageContent2.includes('₹500'), '₹500 absent from NX0001');

        // Step M: Return to /book/democlinic (Third Visit)
        console.log('\n--- Step M: Return to /book/democlinic (Third Visit) ---');
        await page.goto(`${BASE_URL}/book/democlinic`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_demo_clinic_return.png'), fullPage: true });

        // Step N & O: Verify Demo doctors and fees unchanged
        const pageContent3 = await page.content();
        recordResult('N. Demo doctors still present on return', pageContent3.includes('Dr. Amit Verma') && pageContent3.includes('Dr. Neha Kapoor'), 'Both demo doctors intact');
        recordResult('N. No NX0001 doctor leaked to Demo', !pageContent3.includes('Rajesh Sharma'), 'Rajesh Sharma is absent');
        recordResult('O. Demo fees unchanged on return', pageContent3.includes('₹500') && pageContent3.includes('₹400'), 'Fees ₹500 and ₹400 intact');
        recordResult('O. NX0001 fee ₹600 did NOT leak to Demo', !pageContent3.includes('₹600'), '₹600 absent from Demo');

        // Step P: Open /book/NX0002
        console.log('\n--- Step P: Open /book/NX0002 ---');
        await page.goto(`${BASE_URL}/book/NX0002`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_nx0002_pwa.png'), fullPage: true });

        // Step Q: Verify NX0002 data
        const pageContent4 = await page.content();
        recordResult('Q. NX0002 clinic loaded', pageContent4.includes('Arogya Super Specialty Clinic') || pageContent4.includes('NX0002'), 'NX0002 tenant loaded');
        recordResult('Q. NX0002 doctor Rajesh Tripathi loaded', pageContent4.includes('Dr. Rajesh Tripathi'), 'Found "Dr. Rajesh Tripathi"');
        recordResult('Q. NX0002 fee ₹800 loaded', pageContent4.includes('₹800'), 'Found ₹800');

        // Step R: Return to /book/democlinic (Final Visit)
        console.log('\n--- Step R: Return to /book/democlinic (Final Visit) ---');
        await page.goto(`${BASE_URL}/book/democlinic`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_demo_clinic_final.png'), fullPage: true });

        // Step S: Verify Demo data still unchanged
        const pageContent5 = await page.content();
        recordResult('S. Demo Clinic isolated on final revisit', pageContent5.includes('Demo Clinic'), 'Found "Demo Clinic"');
        recordResult('S. Demo doctors preserved', pageContent5.includes('Dr. Amit Verma') && pageContent5.includes('Dr. Neha Kapoor'), 'Fictional demo doctors present');
        recordResult('S. Demo fees strictly intact', pageContent5.includes('₹500') && pageContent5.includes('₹400'), 'Fees remain ₹500 and ₹400');
        recordResult('S. NX0002 fee ₹800 did NOT leak to Demo', !pageContent5.includes('₹800'), '₹800 absent from Demo');

        // Negative Security Test: /book/democlinic?clinic_id=1 must return 403
        console.log('\n--- Step: Tamper Test /book/democlinic?clinic_id=1 ---');
        const tamperResponse = await page.goto(`${BASE_URL}/book/democlinic?clinic_id=1`, { waitUntil: 'networkidle' });
        recordResult('Negative Security: /book/democlinic?clinic_id=1 returns 403', tamperResponse.status() === 403, `HTTP ${tamperResponse.status()}`);

    } catch (err) {
        console.error('Fatal execution error:', err);
        recordResult('Playwright test execution', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  E2E RESULTS: Total: ${summary.total} | Passed: ${summary.passed} | Failed: ${summary.failed}`);
    console.log('======================================================================\n');

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite().catch(err => {
    console.error('Suite crashed:', err);
    process.exit(1);
});
