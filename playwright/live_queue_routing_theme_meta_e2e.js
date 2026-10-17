import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.APP_URL || process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8088';
const SCREENSHOT_DIR = path.resolve('test-results/live_queue_theme_meta');

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

async function runLiveQueueRoutingThemeMetaSuite() {
    console.log('======================================================================');
    console.log('  DXCLINIC — LIVE QUEUE ROUTING, DAY/NIGHT THEME & META EMBEDDED E2E');
    console.log('  Verification: Issue A (Routing & 403), Issue B (Day/Night), Issue C (Meta UX)');
    console.log('  Developer: Kundan Kumar | Nexora Solutions, Varanasi, India');
    console.log('======================================================================\n');
    console.log(`Target: ${BASE_URL}\n`);

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    try {
        // ==================================================================
        // STEP 1: Login as Demo Clinic Administrator
        // ==================================================================
        console.log('▶ [AUTH 1] Logging in as Demo Clinic Administrator (demoadmin)...');
        const demoContext = await browser.newContext({
            viewport: { width: 1440, height: 900 },
            userAgent: 'DxClinic-DemoClinic-E2E/1.0'
        });
        const demoPage = await demoContext.newPage();

        await demoPage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await demoPage.fill('#clinic_code', 'democlinic');
        await demoPage.fill('#email', 'demoadmin@dxclinic.local');
        await demoPage.fill('#password', 'DemoAdmin@2026!');
        await demoPage.click('#btn_submit_login');
        await demoPage.waitForTimeout(2000);

        const currentUrl = demoPage.url();
        recordResult('Demo Clinic Admin authenticated successfully', !currentUrl.includes('/login'), `URL: ${currentUrl}`);

        // ==================================================================
        // STEP 2: Issue A - Verify Live Queue Launcher URL in App Layout
        // ==================================================================
        console.log('▶ [ISSUE A - LAUNCHER] Verifying Live Queue Launcher in header navigation...');
        const liveQueueNavBtn = demoPage.locator('#btn_open_live_queue_window');
        let onclickAttr = '';
        if (await liveQueueNavBtn.count() > 0) {
            onclickAttr = await liveQueueNavBtn.getAttribute('onclick') || '';
        }
        recordResult(
            'Navigation Live Queue launcher targets canonical Demo Clinic route',
            onclickAttr.includes('/clinic/democlinic/queue/live') && !onclickAttr.includes('sharmacare'),
            `Onclick: ${onclickAttr}`
        );

        // ==================================================================
        // STEP 3: Issue A - Navigate to Demo Clinic Live Queue
        // ==================================================================
        console.log('▶ [ISSUE A - DIRECT] Navigating to /clinic/democlinic/queue/live...');
        const queueResponse = await demoPage.goto(`${BASE_URL}/clinic/democlinic/queue/live`, { waitUntil: 'networkidle' });
        recordResult('Demo Clinic Live Queue loads with HTTP 200', queueResponse.status() === 200, `Status: ${queueResponse.status()}`);
        recordResult('Live Queue URL is exactly /clinic/democlinic/queue/live', demoPage.url().includes('/clinic/democlinic/queue/live'));

        const pageTitle = await demoPage.title();
        const pageContent = await demoPage.content();
        recordResult('Live Queue displays Demo Clinic branding', pageContent.includes('Demo Clinic') || pageContent.includes('Demo Healthcare'));

        // ==================================================================
        // STEP 4: Issue B - Verify Default Theme is LIGHT / DAY Mode
        // ==================================================================
        console.log('▶ [ISSUE B - THEME DEFAULT] Verifying default theme is DAY / LIGHT mode...');
        const themeBtn = demoPage.locator('#btn_live_queue_theme_toggle');
        recordResult('Theme Toggle button (#btn_live_queue_theme_toggle) is present', await themeBtn.count() > 0);

        const themeBtnText = await themeBtn.innerText();
        recordResult('Theme button displays "☀ Day" by default', themeBtnText.includes('Day') || themeBtnText.includes('☀'), `Text: ${themeBtnText.trim()}`);

        const bodyClass = await demoPage.evaluate(() => document.body.className);
        const htmlClass = await demoPage.evaluate(() => document.documentElement.className);
        const storedThemeInitial = await demoPage.evaluate(() => localStorage.getItem('dxclinic_livequeue_theme'));

        recordResult('Default theme is DAY mode (not forced dark)', !htmlClass.includes('dark') && (storedThemeInitial === null || storedThemeInitial === 'day'));
        await demoPage.screenshot({ path: path.join(SCREENSHOT_DIR, '01_democlinic_live_queue_day_default.png'), fullPage: true });

        // ==================================================================
        // STEP 5: Issue B - Switch Theme to NIGHT Mode and Verify
        // ==================================================================
        console.log('▶ [ISSUE B - THEME SWITCH] Switching to NIGHT mode...');
        await themeBtn.click();
        await demoPage.waitForTimeout(500);

        const nightBtnText = await themeBtn.innerText();
        const storedThemeAfterSwitch = await demoPage.evaluate(() => localStorage.getItem('dxclinic_livequeue_theme'));
        const htmlClassAfterSwitch = await demoPage.evaluate(() => document.documentElement.className);

        recordResult('Theme button switched to "☾ Night"', nightBtnText.includes('Night') || nightBtnText.includes('☾'), `Text: ${nightBtnText.trim()}`);
        recordResult('Theme in localStorage updated to "night"', storedThemeAfterSwitch === 'night');
        recordResult('HTML element has "dark" class applied', htmlClassAfterSwitch.includes('dark'));
        await demoPage.screenshot({ path: path.join(SCREENSHOT_DIR, '02_democlinic_live_queue_night_mode.png'), fullPage: true });

        // ==================================================================
        // STEP 6: Issue B - Reload Page and Verify NIGHT Mode Persistence
        // ==================================================================
        console.log('▶ [ISSUE B - PERSISTENCE] Reloading page to verify NIGHT mode persistence...');
        await demoPage.reload({ waitUntil: 'networkidle' });
        await demoPage.waitForTimeout(500);

        const reloadedThemeBtn = demoPage.locator('#btn_live_queue_theme_toggle');
        const reloadedBtnText = await reloadedThemeBtn.innerText();
        const reloadedStoredTheme = await demoPage.evaluate(() => localStorage.getItem('dxclinic_livequeue_theme'));
        const reloadedHtmlClass = await demoPage.evaluate(() => document.documentElement.className);

        recordResult('Persisted NIGHT theme on reload (button shows Night)', reloadedBtnText.includes('Night'));
        recordResult('Persisted NIGHT theme in localStorage', reloadedStoredTheme === 'night');
        recordResult('Persisted dark class on HTML element after reload', reloadedHtmlClass.includes('dark'));

        // ==================================================================
        // STEP 7: Issue B - Switch Back to DAY Mode and Verify Persistence
        // ==================================================================
        console.log('▶ [ISSUE B - RESTORE] Switching back to DAY mode...');
        await reloadedThemeBtn.click();
        await demoPage.waitForTimeout(500);

        const backToDayText = await reloadedThemeBtn.innerText();
        const backToDayStored = await demoPage.evaluate(() => localStorage.getItem('dxclinic_livequeue_theme'));
        recordResult('Theme switched back to "☀ Day"', backToDayText.includes('Day'));
        recordResult('localStorage updated back to "day"', backToDayStored === 'day');

        await demoPage.reload({ waitUntil: 'networkidle' });
        await demoPage.waitForTimeout(500);
        const afterReloadDayStored = await demoPage.evaluate(() => localStorage.getItem('dxclinic_livequeue_theme'));
        recordResult('DAY mode persists cleanly after reload', afterReloadDayStored === 'day');

        // Security check on localStorage
        const allLocalStorageKeys = await demoPage.evaluate(() => Object.keys(localStorage));
        const leakedKeys = allLocalStorageKeys.filter(k => k.includes('token') || k.includes('secret') || k.includes('password'));
        recordResult('Theme storage security: No tokens, passwords, or secrets in localStorage', leakedKeys.length === 0);

        // ==================================================================
        // STEP 8: Issue A - Strict Cross-Clinic Access Rejection (HTTP 403)
        // ==================================================================
        console.log('▶ [ISSUE A - TENANT SECURITY] Attempting unauthorized cross-clinic access to /clinic/sharmacare/queue/live...');
        const crossClinicResponse = await demoPage.goto(`${BASE_URL}/clinic/sharmacare/queue/live`, { waitUntil: 'networkidle' });
        recordResult(
            'Cross-clinic Live Queue access strictly returns HTTP 403 Forbidden',
            crossClinicResponse.status() === 403,
            `Status: ${crossClinicResponse.status()}`
        );

        const crossClinicContent = await demoPage.content();
        recordResult(
            'HTTP 403 error page clearly states unauthorized cross-clinic access',
            crossClinicContent.includes('403') && (crossClinicContent.includes('Unauthorized cross-clinic Live Queue access') || crossClinicContent.includes('Forbidden') || crossClinicContent.includes('Unauthorized'))
        );
        await demoPage.screenshot({ path: path.join(SCREENSHOT_DIR, '03_democlinic_cross_clinic_403.png') });

        // ==================================================================
        // STEP 9: Issue C - WhatsApp Settings & Meta Embedded Signup Flow
        // ==================================================================
        console.log('▶ [ISSUE C - META FLOW] Navigating to WhatsApp settings (/settings/whatsapp)...');
        await demoPage.goto(`${BASE_URL}/settings/whatsapp`, { waitUntil: 'networkidle' });

        // If already connected, disconnect first to test fresh signup UX
        const disconnectBtn = demoPage.locator('#btn_whatsapp_disconnect, #btn_whatsapp_disconnect_card');
        if (await disconnectBtn.count() > 0 && await disconnectBtn.first().isVisible()) {
            console.log('  [SETUP] Disconnecting existing WhatsApp connection for clean test...');
            demoPage.once('dialog', async d => await d.accept());
            await disconnectBtn.first().click();
            await demoPage.waitForTimeout(1500);
        }

        const connectMetaBtn = demoPage.locator('#btn_connect_meta_whatsapp, #btn_connect_meta_whatsapp_hero');
        recordResult('Connect Meta WhatsApp button is available', await connectMetaBtn.count() > 0);
        await connectMetaBtn.first().click();
        await demoPage.waitForTimeout(800);

        const modalHeading = demoPage.locator('h3:has-text("Connect Meta WhatsApp Cloud API")');
        recordResult('Meta Embedded Signup onboarding modal opened', await modalHeading.isVisible());

        // Verify Authoritative Configuration in modal
        const modalContent = await demoPage.content();
        recordResult('Authoritative Meta App ID field present in modal', modalContent.includes('App ID:'));
        recordResult('Authoritative Config ID field present in modal', modalContent.includes('Config ID:'));
        recordResult('Authoritative Version (v4) & whatsapp_business_app_onboarding present in modal', modalContent.includes('v4') && modalContent.includes('whatsapp_business_app_onboarding'));

        // Test Launch Meta Embedded Signup button - MUST NOT SILENTLY CLOSE
        console.log('▶ [ISSUE C - LAUNCH BUTTON] Clicking "Launch Meta Embedded Signup" and asserting non-silent UX...');
        const launchMetaBtn = demoPage.locator('#btn_launch_meta_signup');
        recordResult('Launch Meta Embedded Signup button is visible', await launchMetaBtn.count() > 0);

        await launchMetaBtn.click();
        await demoPage.waitForTimeout(1000);

        // Verify the modal is STILL OPEN (NO SILENT CLOSE)
        const isModalStillOpen = await modalHeading.isVisible();
        recordResult('Assertion: Modal did NOT silently close upon clicking Launch Meta Embedded Signup', isModalStillOpen);

        // Verify visible feedback banner is displayed with deterministic status
        const feedbackBanner = demoPage.locator('#meta_signup_feedback_banner');
        const isBannerVisible = await feedbackBanner.isVisible();
        recordResult('Visible status feedback banner (#meta_signup_feedback_banner) is rendered', isBannerVisible);

        const bannerText = await feedbackBanner.innerText();
        const hasValidStatus = bannerText.includes('CONFIG_ERROR') || 
                               bannerText.includes('SDK_UNAVAILABLE') || 
                               bannerText.includes('POPUP') || 
                               bannerText.includes('POPUP_BLOCKED') || 
                               bannerText.includes('OPENED') || 
                               bannerText.includes('LAUNCHING') || 
                               bannerText.includes('WINDOW_CLOSED_WITHOUT_RESULT') || 
                               bannerText.includes('OPENING_POPUP') || 
                               bannerText.includes('CANCELLED') || 
                               bannerText.includes('INITIALIZING');
        recordResult('Banner provides explicit deterministic status (no silent failure)', hasValidStatus, `Banner text: ${bannerText.replace(/\n/g, ' ')}`);
        await demoPage.screenshot({ path: path.join(SCREENSHOT_DIR, '04_meta_signup_modal_feedback.png') });

        // Simulate callback completion to verify backend onboarding & masked credentials
        console.log('▶ [ISSUE C - BACKEND ONBOARDING] Verifying backend callback processing...');
        const simulateCallbackBtn = demoPage.locator('#btn_simulate_meta_callback');
        recordResult('Verify Flow / Simulate Meta Callback button available', await simulateCallbackBtn.count() > 0);

        if (await simulateCallbackBtn.count() > 0) {
            await simulateCallbackBtn.click();
            await demoPage.waitForTimeout(2000);
        }

        const afterOnboardingContent = await demoPage.content();
        recordResult(
            'Connection transitions to "CONFIGURED — LIVE META VERIFICATION PENDING"',
            afterOnboardingContent.includes('CONFIGURED — LIVE META VERIFICATION PENDING')
        );

        recordResult('WhatsApp number is displayed with privacy masking', afterOnboardingContent.includes('WhatsApp Number') && afterOnboardingContent.includes('••'));
        recordResult('WABA ID is displayed with privacy masking', afterOnboardingContent.includes('WABA ID') && afterOnboardingContent.includes('••'));
        recordResult('Phone Number ID is displayed with privacy masking', afterOnboardingContent.includes('Phone Number ID') && afterOnboardingContent.includes('••'));
        recordResult('No plaintext access tokens or secrets leaked in DOM', !afterOnboardingContent.includes('EAAB_system_user_token'));
        await demoPage.screenshot({ path: path.join(SCREENSHOT_DIR, '05_meta_onboarding_active_state.png'), fullPage: true });

        // ==================================================================
        // STEP 10: Switch Clinic Context & Verify SharmaCare Isolation
        // ==================================================================
        console.log('▶ [AUTH 2] Logging in as Sharma Care Administrator (admin@dxclinic.local)...');
        const sharmaContext = await browser.newContext({
            viewport: { width: 1440, height: 900 },
            userAgent: 'DxClinic-SharmaCare-E2E/1.0'
        });
        const sharmaPage = await sharmaContext.newPage();

        await sharmaPage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await sharmaPage.fill('#clinic_code', 'NX0001');
        await sharmaPage.fill('#email', 'admin@dxclinic.local');
        await sharmaPage.fill('#password', 'DxClinic@Admin2026');
        await sharmaPage.click('#btn_submit_login');
        await sharmaPage.waitForTimeout(2000);

        recordResult('Sharma Care Admin authenticated successfully', !sharmaPage.url().includes('/login'));

        // Access Sharma Care Live Queue
        console.log('▶ [SHARMA CARE QUEUE] Navigating to /clinic/sharmacare/queue/live...');
        const sharmaQueueResponse = await sharmaPage.goto(`${BASE_URL}/clinic/sharmacare/queue/live`, { waitUntil: 'networkidle' });
        recordResult('Sharma Care Live Queue accessible for Sharma Care user (HTTP 200)', sharmaQueueResponse.status() === 200);

        // Attempt cross-clinic access to Demo Clinic from Sharma Care user
        console.log('▶ [SHARMA CARE IDOR] Attempting cross-clinic access to /clinic/democlinic/queue/live...');
        const crossDemoResponse = await sharmaPage.goto(`${BASE_URL}/clinic/democlinic/queue/live`, { waitUntil: 'networkidle' });
        recordResult(
            'Sharma Care user strictly forbidden from Demo Clinic Live Queue (HTTP 403)',
            crossDemoResponse.status() === 403,
            `Status: ${crossDemoResponse.status()}`
        );

        await demoContext.close();
        await sharmaContext.close();

    } catch (e) {
        console.error('Test Suite Fatal Error:', e);
        recordResult('Full E2E Suite Execution without uncaught exception', false, e.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  E2E TEST SUMMARY: Total: ${summary.total} | Passed: ${summary.passed} | Failed: ${summary.failed}`);
    console.log('======================================================================\n');

    process.exit(summary.failed > 0 ? 1 : 0);
}

runLiveQueueRoutingThemeMetaSuite().catch(err => {
    console.error(err);
    process.exit(1);
});
