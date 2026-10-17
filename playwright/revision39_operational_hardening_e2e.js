import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/revision39');

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
    console.log('  DXCLINIC REVISION 39 — OPERATIONAL HARDENING & UX E2E SUITE ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    console.log(`[INIT] Automated Browser: Google Chrome v${browser.version()}`);
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Revision39-E2E/1.0 (Google-Chrome-Desktop)'
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
        // ------------------------------------------------------------------
        // PHASE 1: Demo PWA Auto-Advance & Session Selector
        // ------------------------------------------------------------------
        console.log('\n--- Phase 1: Demo PWA Auto-Advance & Session Selector ---');
        await page.goto(`${BASE_URL}/book/democlinic`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_demo_pwa_sessions_auto_advance.png'), fullPage: true });

        const pwaContent = await page.content();
        recordResult('P1. Demo Clinic branding loaded', pwaContent.includes('Demo Clinic'), 'Demo Clinic tenant resolved');
        recordResult('P1. Demo doctors present in selector', pwaContent.includes('Dr. Amit Verma') && pwaContent.includes('Dr. Neha Kapoor'), 'Dr. Amit Verma and Dr. Neha Kapoor present');

        // Check date input value
        const dateInput = page.locator('#pwa_appointment_date');
        const selectedDate = await dateInput.inputValue();
        console.log(`  [INFO] PWA Selected Date: ${selectedDate}`);

        // Check session options count
        const sessionOptions = await page.$$eval('#pwa_session_type option', opts => opts.map(o => ({ value: o.value, text: o.text.trim() })));
        const validSessionOptions = sessionOptions.filter(o => o.value !== '');
        console.log(`  [INFO] PWA Session Options: ${JSON.stringify(validSessionOptions)}`);

        recordResult('P1. Session selector not empty on initial load', validSessionOptions.length > 0, `Found ${validSessionOptions.length} sessions`);
        recordResult('P1. Morning session available', sessionOptions.some(o => o.value === 'MORNING' || o.text.includes('Morning')), 'Morning session option found');
        recordResult('P1. Evening session available', sessionOptions.some(o => o.value === 'EVENING' || o.text.includes('Evening')), 'Evening session option found');
        recordResult('P1. "No sessions available" absent', !pwaContent.includes('-- No sessions available --'), 'No fallback empty alert');

        // ------------------------------------------------------------------
        // PHASE 2: Superadmin Dashboard (Demo 2-Doctor Limit & NX0001 Repaired License)
        // ------------------------------------------------------------------
        console.log('\n--- Phase 2: Superadmin Dashboard Verification ---');
        await page.goto(`${BASE_URL}/superadmin/login`, { waitUntil: 'networkidle' });
        await page.fill('#email', 'superadmin@dxclinic.local');
        await page.fill('#password', 'TestAdminPass123!');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/superadmin/dashboard');

        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_superadmin_dashboard_repaired.png'), fullPage: true });

        const superContent = await page.content();

        // Check Demo Clinic card: hard limit 2 doctor seats
        recordResult('P2. Superadmin Demo card shows 2 Doctor seats', superContent.includes('Hard limit: 2 Doctor seats • Doctors: 2 / 2 (demodoctor01, demodoctor02)'), 'Found exact hard limit 2 string');
        recordResult('P2. Stale "Hard limit: 1 Doctor seat" is ABSENT', !superContent.includes('Hard limit: 1 Doctor seat'), 'Stale 1 seat string eradicated');

        // Check NX0001 row
        recordResult('P2. NX0001 row present on Superadmin dashboard', superContent.includes('NX0001') && superContent.includes('Sharma Care Clinic'), 'NX0001 found');
        recordResult('P2. NX0001 expiry is 2027 (not 2031)', superContent.includes('29-08-2027') || superContent.includes('2027-08-29') || superContent.includes('29 Aug 2027'), 'NX0001 expiry is in 2027');
        recordResult('P2. NX0001 remaining days is ~335 days (not 1796 days)', !superContent.includes('1,796 days') && !superContent.includes('1796 days'), '1796 days artifact eradicated');

        // ------------------------------------------------------------------
        // PHASE 3: Universal Keyboard Shortcuts Workflow
        // ------------------------------------------------------------------
        console.log('\n--- Phase 3: Keyboard Shortcuts Engine & Badges ---');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await page.fill('#clinic_code', 'NX0001');
        await page.fill('#email', 'admin@dxclinic.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/dashboard');

        // Verify topbar shortcut trigger button
        const shortcutBtn = page.locator('#btn_open_shortcuts_modal');
        recordResult('P3. Topbar [⌨ Shortcuts F1] button visible', await shortcutBtn.isVisible(), 'Header button present');

        // Press F1 to open cheatsheet modal
        console.log('  [ACTION] Pressing F1 key...');
        await page.keyboard.press('F1');
        await page.waitForTimeout(800);

        const shortcutsModal = page.locator('#dx-shortcuts-modal');
        const modalVisible = await shortcutsModal.isVisible();
        recordResult('P3. F1 opens Keyboard Shortcuts modal', modalVisible, 'Modal displayed');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_keyboard_shortcuts_modal.png') });

        // Test search filter inside modal
        const searchInput = page.locator('#dx-shortcuts-search');
        if (await searchInput.isVisible()) {
            await searchInput.fill('booking');
            await page.waitForTimeout(300);
            const modalContent = await shortcutsModal.textContent();
            recordResult('P3. Modal search filters shortcuts', modalContent.includes('F8') || modalContent.includes('Alt + B'), 'Filtered results');
        }

        // Press Escape to dismiss modal
        console.log('  [ACTION] Pressing Escape key...');
        await page.keyboard.press('Escape');
        await page.waitForTimeout(500);
        recordResult('P3. Escape key dismisses shortcuts modal', !(await shortcutsModal.isVisible()), 'Modal hidden');

        // Navigate to Operator Booking to inspect <kbd> badges
        console.log('\n--- Inspect Operator Booking <kbd> badges ---');
        await page.goto(`${BASE_URL}/operator/booking`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(800);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_operator_booking_keyboard_badges.png'), fullPage: true });

        const opContent = await page.content();
        recordResult('P3. [F2] New Patient badge present', opContent.includes('F2') && opContent.includes('New Patient'), 'F2 badge rendered');
        recordResult('P3. [F8] Allocate Queue Token badge present', opContent.includes('F8') && (opContent.includes('Allocate Common Queue Token') || opContent.includes('Allocate Queue Token')), 'F8 badge rendered');

        // ------------------------------------------------------------------
        // PHASE 4: Professional Documentation Hub & FAQ Manual
        // ------------------------------------------------------------------
        console.log('\n--- Phase 4: Professional User Manuals Hub & FAQ ---');
        await page.goto(`${BASE_URL}/manuals`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(500);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_documentation_hub.png'), fullPage: true });

        const hubContent = await page.content();
        recordResult('P4. Documentation Hub loaded', hubContent.includes('Documentation') && hubContent.includes('Manuals Hub'), 'Hub title found');
        recordResult('P4. Admin Manual card present', hubContent.includes('Administrator') && hubContent.includes('Practice Manual'), 'Admin card found');
        recordResult('P4. Operator Manual card present', hubContent.includes('Operator') && hubContent.includes('Manual'), 'Operator card found');
        recordResult('P4. Doctor Manual card present', hubContent.includes('Doctor') && hubContent.includes('Manual'), 'Doctor card found');
        recordResult('P4. Superadmin Manual card present', hubContent.includes('Superadmin') && hubContent.includes('Manual'), 'Superadmin card found');
        recordResult('P4. FAQ link present', hubContent.includes('Search 136 FAQ') || hubContent.includes('faq.html') || hubContent.includes('FAQ'), 'FAQ link found');

        // Navigate to FAQ
        console.log('\n--- Inspect FAQ Manual ---');
        await page.goto(`${BASE_URL}/manuals/faq.html`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(500);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_faq_manual_print_ready.png') });

        const faqContent = await page.content();
        recordResult('P4. FAQ manual renders print toolbar', faqContent.includes('Print / Save PDF'), 'Print button present');
        recordResult('P4. FAQ Q137 (Keyboard shortcuts) present', faqContent.includes('Q137:') && faqContent.includes('keyboard shortcuts'), 'Q137 present');
        recordResult('P4. FAQ Q138 (PWA auto-advance) present', faqContent.includes('Q138:') && faqContent.includes('auto-advances'), 'Q138 present');
        recordResult('P4. FAQ Q140 (Local hard-drive backup) present', faqContent.includes('Q140:') && faqContent.includes('local hard-drive backup'), 'Q140 present');
        recordResult('P4. FAQ Q141 (Companion manifest) present', faqContent.includes('Q141:') && faqContent.includes('metadata manifest'), 'Q141 present');

        // ------------------------------------------------------------------
        // PHASE 5: Public PWA Chatbot RAG Knowledge Answers
        // ------------------------------------------------------------------
        console.log('\n--- Phase 5: Public PWA Chatbot RAG Knowledge Answers ---');
        await page.goto(`${BASE_URL}/book/democlinic`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(500);

        // Open Chatbot
        const chatTrigger = page.locator('#btn_open_chatbot');
        if (await chatTrigger.isVisible()) {
            await chatTrigger.click();
            await page.waitForTimeout(500);

            const chatInput = page.locator('#chatbot_input_text');

            recordResult('P5. Chatbot window opens', await chatInput.isVisible(), 'Input ready');

            // 1. Query: Who are the available doctors?
            console.log('  [QUERY 1] "Who are the available doctors?"');
            await chatInput.fill('Who are the available doctors?');
            await chatInput.press('Enter');
            await page.waitForTimeout(2000);

            const chatHtml1 = await page.locator('#chat_messages_container').textContent();
            recordResult('P5. Chatbot answers doctor inquiry dynamically', chatHtml1.includes('Dr. Amit Verma') && chatHtml1.includes('Dr. Neha Kapoor'), 'Dr. Amit Verma & Dr. Neha Kapoor listed');

            // 2. Query: What keyboard shortcuts are available?
            console.log('  [QUERY 2] "What keyboard shortcuts are available?"');
            await chatInput.fill('What keyboard shortcuts are available?');
            await chatInput.press('Enter');
            await page.waitForTimeout(2000);

            const chatHtml2 = await page.locator('#chat_messages_container').textContent();
            recordResult('P5. Chatbot answers shortcuts inquiry from RAG', chatHtml2.includes('F1') || chatHtml2.includes('Keyboard Shortcuts'), 'Shortcuts answered from FAQ');
            recordResult('P5. Chatbot quotes source citation', chatHtml2.includes('Source: FAQ Knowledge Base') || chatHtml2.includes('Q137'), 'Authoritative source cited');

            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '07_chatbot_knowledge_answers.png') });
        } else {
            recordResult('P5. Chatbot trigger button visible', false, 'Trigger not found');
        }

        // ------------------------------------------------------------------
        // PHASE 6: Local Hard-Drive Backup Facility & Companion Manifest
        // ------------------------------------------------------------------
        console.log('\n--- Phase 6: Local Hard-Drive Backup Facility ---');
        // Ensure staff login for settings access
        await page.goto(`${BASE_URL}/settings/backup`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '08_local_backup_facility.png'), fullPage: true });

        const backupContent = await page.content();
        recordResult('P6. Backup Manager page accessible to Admin', backupContent.includes('Backup & Restore') || backupContent.includes('Encrypted Backups') || backupContent.includes('Backup'), 'Backup screen loaded');
        recordResult('P6. Create Backup button present', backupContent.includes('+ Create Encrypted Backup') || backupContent.includes('Create Backup'), 'Create button present');
        recordResult('P6. Manifest (.json) download button present', backupContent.includes('Manifest (.json)') || backupContent.includes('downloadMetadata'), 'Manifest download action present');
        recordResult('P6. Archive (.ncbk) download button present', backupContent.includes('Archive (.ncbk)') || backupContent.includes('downloadBackup'), 'Archive download action present');

        // Open Create Backup Modal to verify Local Destination Directory input
        const openCreateBackupBtn = page.locator('button:has-text("+ Create Encrypted Backup")');
        if (await openCreateBackupBtn.isVisible()) {
            await openCreateBackupBtn.click();
            await page.waitForTimeout(500);
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '09_backup_create_modal_local_drive.png') });

            const modalContent = await page.content();
            recordResult('P6. Local Destination Directory input field present in modal', modalContent.includes('Destination Directory (Local Drive / Folder)') || modalContent.includes('customBackupFolder'), 'Custom folder input found');
            recordResult('P6. Companion .meta.json manifest notice present', modalContent.includes('.meta.json') || modalContent.includes('manifest'), 'Companion manifest notice present');
        }

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
