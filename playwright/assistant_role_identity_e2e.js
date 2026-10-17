import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/assistant_role_identity');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function runAssistantRoleIdentitySuite() {
    console.log('======================================================================');
    console.log('  DXCLINIC — ROLE-AWARE ASSISTANT IDENTITY & SESSION GREETING E2E     ');
    console.log('  ADMIN / OPERATOR / DOCTOR / SINGLE GREETING PER OPERATIONAL SESSION ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({ headless: true });
    let passed = 0;
    let failed = 0;

    function record(name, condition, details = '') {
        if (condition) {
            console.log(`  [PASS] ${name} (${details})`);
            passed++;
        } else {
            console.error(`  [FAIL] ${name} (${details})`);
            failed++;
        }
    }

    try {
        // -------------------------------------------------------------
        // SUITE 1: ADMIN ROLE ASSISTANT
        // -------------------------------------------------------------
        console.log('[SUITE 1] Administrator Role Assistant (Demo Administrator)');
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        await adminPage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await adminPage.fill('#email', 'demoadmin@dxclinic.local');
        await adminPage.fill('#password', 'DemoAdmin@2026!');
        await adminPage.locator('#btn_submit_login').click();
        await adminPage.waitForURL('**/dashboard', { timeout: 10000 });
        record('Admin Login', adminPage.url().includes('/dashboard'), 'Logged in as Demo Administrator');

        // Check floating button label
        const adminFloatLabel = await adminPage.locator('#assistant_floating_label').textContent();
        record('Admin Floating Button Label', adminFloatLabel.trim() === 'Admin Assistant', `Label: "${adminFloatLabel.trim()}"`);

        // Open chat modal
        await adminPage.locator('#btn_toggle_rag_assistant').click();
        await adminPage.waitForSelector('#assistant_panel_title', { timeout: 5000 });
        const adminTitle = await adminPage.locator('#assistant_panel_title').textContent();
        record('Admin Panel Title', adminTitle.trim() === 'DxClinic Admin Assistant', `Title: "${adminTitle.trim()}"`);

        // Check greeting message
        await adminPage.waitForTimeout(500);
        await adminPage.screenshot({ path: path.join(SCREENSHOT_DIR, '01_admin_assistant_open.png') });
        const adminChatText = await adminPage.locator('#assistant_messages_stream').textContent();
        record('Admin Personalized Greeting', adminChatText.includes('Hello, Demo Administrator. How can I help you?'), 'Contains: "Hello, Demo Administrator. How can I help you?"');

        // Close and reopen in same session
        console.log('[TEST] Close and reopen assistant in same session...');
        await adminPage.locator('#btn_close_assistant_chat').click();
        await adminPage.waitForTimeout(300);
        await adminPage.locator('#btn_toggle_rag_assistant').click();
        await adminPage.waitForSelector('#assistant_panel_title', { timeout: 5000 });

        // Assert no repeated greeting
        const greetingsCount1 = (adminChatText.match(/Hello, Demo Administrator/g) || []).length;
        const recheckChatText = await adminPage.locator('#assistant_messages_stream').textContent();
        const greetingsCount2 = (recheckChatText.match(/Hello, Demo Administrator/g) || []).length;
        record('No Repeated Greeting on Reopening', greetingsCount2 === 1 && greetingsCount1 === 1, `Greeting count: ${greetingsCount2}`);

        // Navigate to another page and verify greeting is not repeated
        console.log('[TEST] Navigate to another route and return...');
        await adminPage.goto(`${BASE_URL}/settings/clinic-doctor`, { waitUntil: 'domcontentloaded' });
        await adminPage.waitForSelector('#btn_toggle_rag_assistant', { timeout: 5000 });
        await adminPage.locator('#btn_toggle_rag_assistant').click();
        await adminPage.waitForSelector('#assistant_panel_title', { timeout: 5000 });
        const settingsPageChatText = await adminPage.locator('#assistant_messages_stream').textContent();
        const greetingsCount3 = (settingsPageChatText.match(/Hello, Demo Administrator/g) || []).length;
        record('Greeting Maintained Across Page Navigation', greetingsCount3 === 1, `Greeting count: ${greetingsCount3}`);

        await adminContext.close();

        // -------------------------------------------------------------
        // SUITE 2: OPERATOR ROLE ASSISTANT
        // -------------------------------------------------------------
        console.log('\n[SUITE 2] Operator Role Assistant (Demo Frontdesk Operator)');
        const opContext = await browser.newContext();
        const opPage = await opContext.newPage();

        await opPage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await opPage.fill('#email', 'demooperator@dxclinic.local');
        await opPage.fill('#password', 'DemoOp@2026!');
        await opPage.locator('#btn_submit_login').click();
        await opPage.waitForURL('**/dashboard', { timeout: 10000 });
        record('Operator Login', opPage.url().includes('/dashboard'), 'Logged in as Demo Frontdesk Operator');

        const opFloatLabel = await opPage.locator('#assistant_floating_label').textContent();
        record('Operator Floating Button Label', opFloatLabel.trim() === 'Operator Assistant', `Label: "${opFloatLabel.trim()}"`);

        await opPage.locator('#btn_toggle_rag_assistant').click();
        await opPage.waitForSelector('#assistant_panel_title', { timeout: 5000 });
        const opTitle = await opPage.locator('#assistant_panel_title').textContent();
        record('Operator Panel Title', opTitle.trim() === 'DxClinic Operator Assistant', `Title: "${opTitle.trim()}"`);

        await opPage.waitForTimeout(500);
        await opPage.screenshot({ path: path.join(SCREENSHOT_DIR, '02_operator_assistant_open.png') });
        const opChatText = await opPage.locator('#assistant_messages_stream').textContent();
        record('Operator Personalized Greeting', opChatText.includes('Hello, Demo Frontdesk Operator. How can I help you?'), 'Contains: "Hello, Demo Frontdesk Operator. How can I help you?"');

        await opContext.close();

        // -------------------------------------------------------------
        // SUITE 3: DOCTOR ROLE ASSISTANT
        // -------------------------------------------------------------
        console.log('\n[SUITE 3] Doctor Role Assistant (Dr. Amit Verma)');
        const docContext = await browser.newContext();
        const docPage = await docContext.newPage();

        await docPage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await docPage.fill('#email', 'demodoctor@dxclinic.local');
        await docPage.fill('#password', 'DemoDoc01@2026!');
        await docPage.locator('#btn_submit_login').click();
        await docPage.waitForURL('**/dashboard', { timeout: 10000 });
        record('Doctor Login', docPage.url().includes('/dashboard'), 'Logged in as Dr. Amit Verma');

        const docFloatLabel = await docPage.locator('#assistant_floating_label').textContent();
        record('Doctor Floating Button Label', docFloatLabel.trim() === 'Doctor Assistant', `Label: "${docFloatLabel.trim()}"`);

        await docPage.locator('#btn_toggle_rag_assistant').click();
        await docPage.waitForSelector('#assistant_panel_title', { timeout: 5000 });
        const docTitle = await docPage.locator('#assistant_panel_title').textContent();
        record('Doctor Panel Title', docTitle.trim() === "DxClinic Dr. Amit Verma's Assistant", `Title: "${docTitle.trim()}"`);

        await docPage.waitForTimeout(500);
        await docPage.screenshot({ path: path.join(SCREENSHOT_DIR, '03_doctor_assistant_open.png') });
        const docChatText = await docPage.locator('#assistant_messages_stream').textContent();
        record('Doctor Personalized Greeting', docChatText.includes('Hello, Dr. Amit Verma. How can I help you?'), 'Contains: "Hello, Dr. Amit Verma. How can I help you?"');

        // Subtitle check for doctor clinical support
        const docSubtitle = await docPage.locator('#assistant_panel_subtitle').textContent();
        record('Doctor Subtitle Specialization', docSubtitle.includes('Clinical Decision Support'), `Subtitle: "${docSubtitle.trim()}"`);

        await docContext.close();

    } catch (err) {
        console.error('Test Suite Error:', err);
        record('Execution Clean', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  ASSISTANT SUITE SUMMARY: ${passed}/${passed + failed} PASSED (${failed} FAILED)`);
    console.log('======================================================================\n');

    if (failed > 0) {
        process.exit(1);
    }
}

runAssistantRoleIdentitySuite();
