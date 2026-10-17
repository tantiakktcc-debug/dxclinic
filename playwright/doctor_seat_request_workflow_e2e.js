import { chromium } from 'playwright';
import { execSync } from 'child_process';
import path from 'path';
import fs from 'fs';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.join(process.cwd(), 'playwright-screenshots', 'doctor_seat_request');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

function runPhpHelper(action, ...args) {
    const escapedArgs = args.map(a => `"${a}"`).join(' ');
    const cmd = `php playwright/e2e_test_helper.php ${action} ${escapedArgs}`;
    const output = execSync(cmd, { cwd: process.cwd() }).toString();
    try {
        return JSON.parse(output);
    } catch (e) {
        console.error('Failed to parse PHP helper output:', output);
        throw e;
    }
}

async function runDoctorSeatRequestWorkflowSuite() {
    console.log('======================================================================');
    console.log('  DXCLINIC — DOCTOR SEAT EXPANSION REQUEST & SUPERADMIN WORKFLOW E2E  ');
    console.log('  SEAT LIMIT -> REQUEST -> CONTACT DEV INFO -> SUPERADMIN FULFILLMENT  ');
    console.log('======================================================================\n');

    const summary = { total: 0, passed: 0, failed: 0 };
    function recordResult(name, passed, details = '') {
        summary.total++;
        if (passed) {
            summary.passed++;
            console.log(`  [PASS] ${name} ${details ? '(' + details + ')' : ''}`);
        } else {
            summary.failed++;
            console.log(`  [FAIL] ${name} ${details ? '(' + details + ')' : ''}`);
        }
    }

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1400, height: 900 } });
    const page = await context.newPage();

    try {
        // Step 1: Initialize New Life Clinic at Seat Limit (Capacity 1, Used 1, Available 0)
        console.log('[STEP 1] Setting up New Life Clinic at Seat Limit (1/1 seats used)');
        const setupData = runPhpHelper('setup_seat_limit_clinic');
        recordResult('Seat Limit Setup', setupData.success, `Capacity: ${setupData.capacity}, Used: ${setupData.used}, Available: ${setupData.available}`);

        // Step 2: Login as New Life Clinic Admin
        console.log('[STEP 2] Authenticating as New Life Clinic Admin');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
        await page.fill('#email', 'newlifeadmin@dxclinic.local');
        await page.fill('#password', 'Password123!');
        await page.click('#btn_submit_login');
        await page.waitForURL(url => url.pathname.includes('/dashboard') || url.pathname.includes('/onboarding'), { timeout: 10000 });
        recordResult('Clinic Admin Login', page.url().includes('/dashboard') || page.url().includes('/onboarding'));

        // Step 3: Open Doctor Management & Verify Limit Reached
        console.log('[STEP 3] Opening Doctor Management');
        await page.goto(`${BASE_URL}/settings/clinic-doctor`, { waitUntil: 'domcontentloaded' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_doctor_management_limit.png') });

        const seatBadge = page.locator('#badge_doctor_seats');
        const badgeText = await seatBadge.innerText();
        recordResult('Doctor Seats 1/1 Used', badgeText.includes('1 / 1 used') && badgeText.includes('0 available'), badgeText);

        const limitBadge = page.locator('#badge_seat_limit_reached');
        recordResult('Seat Limit Reached Badge Visible', await limitBadge.isVisible());

        const requestSeatBtn = page.locator('#btn_buy_additional_doctor_seat');
        recordResult('Request Additional Doctor Seat CTA Visible', await requestSeatBtn.isVisible());

        // Step 4: Click CTA to navigate to Subscription Portal
        console.log('[STEP 4] Navigating to Subscription Portal');
        await requestSeatBtn.click();
        await page.waitForURL('**/subscription', { timeout: 10000 });
        recordResult('Subscription Portal Opened', page.url().includes('/subscription'));
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_subscription_page.png') });

        // Step 5: Verify Request Additional Doctor Seats Section
        console.log('[STEP 5] Verifying Request Additional Doctor Seats Section');
        const addonSection = page.locator('#doctor_seat_addon_section');
        recordResult('Seat Expansion Section Present', await addonSection.isVisible());

        const initialCapacity = await page.locator('#current_total_capacity').innerText();
        recordResult('Initial Capacity Shows 1 Seat', initialCapacity.includes('1'), initialCapacity);

        // Step 6: Submit Doctor Seat Request (+1 seat)
        console.log('[STEP 6] Submitting Doctor Seat Request');
        const submitBtn = page.locator('#btn_purchase_doctor_seat');
        const submitBtnText = await submitBtn.innerText();
        recordResult('Button Says Request Additional Doctor Seat (No Fake Pay & Activate)', submitBtnText.includes('Request Additional Doctor Seat'), submitBtnText);

        await submitBtn.click();
        await page.waitForTimeout(2000);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_after_request_submitted.png') });

        // Step 7: Verify User Message and Developer Contact Info
        console.log('[STEP 7] Verifying Submission Message and Developer Info');
        const successBanner = page.locator('#seat_purchase_success_banner');
        const bannerText = await successBanner.innerText();
        recordResult('Submission Message Shown', bannerText.includes('Additional doctor seat request submitted'), bannerText);
        recordResult('Developer Contact Instruction Shown', bannerText.includes('Please contact the application developer'), bannerText);
        recordResult('No Fake Payment Confirmed Message', !bannerText.includes('Payment confirmed!'), 'Verified no fake payment confirmation');
        recordResult('Developer Kundan Kumar Listed', bannerText.includes('Kundan Kumar'), 'Developer Kundan Kumar listed');
        recordResult('Nexora Solutions Listed', bannerText.includes('Nexora Solutions'), 'Nexora Solutions listed');

        // Step 8: Verify Capacity remains unchanged before fulfillment
        console.log('[STEP 8] Verifying Capacity Remains 1 Before Fulfillment');
        const currentCapAfterReq = await page.locator('#current_total_capacity').innerText();
        recordResult('Capacity Unchanged Before Fulfillment (Still 1)', currentCapAfterReq.includes('1'), currentCapAfterReq);

        // Step 9: Verify Pending Request Banner on Subscription Page
        console.log('[STEP 9] Verifying Pending Request Banner');
        await page.reload({ waitUntil: 'domcontentloaded' });
        const pendingBanner = page.locator('#seat_pending_request_banner');
        recordResult('Pending Request Banner Visible', await pendingBanner.isVisible());

        // Step 10: Verify SuperAdmin License Requests Visibility
        console.log('[STEP 10] Checking SuperAdmin License Requests Visibility');
        const superContext = await browser.newContext({ viewport: { width: 1400, height: 900 } });
        const superPage = await superContext.newPage();
        await superPage.goto(`${BASE_URL}/superadmin/login`, { waitUntil: 'domcontentloaded' });
        await superPage.fill('#email', 'superadmin@dxclinic.local');
        await superPage.fill('#password', 'TestAdminPass123!');
        await superPage.locator('.btn-submit').click();
        await superPage.waitForURL('**/superadmin/dashboard', { timeout: 20000 });

        await superPage.goto(`${BASE_URL}/superadmin/license-requests`, { waitUntil: 'domcontentloaded' });
        await superPage.screenshot({ path: path.join(SCREENSHOT_DIR, '04_superadmin_license_requests.png') });

        const superBody = await superPage.innerText('body');
        recordResult('SuperAdmin Sees Request', superBody.includes('New Life') || superBody.includes('NX0004'), 'Request visible in SuperAdmin list');
        recordResult('SuperAdmin Shows Doctor Seat Add-on Type', superBody.includes('Doctor Seat Add-on') || superBody.includes('DOCTOR_SEAT_ADDON'));

        // Step 11: Fulfill Request via SuperAdmin Authority
        console.log('[STEP 11] Fulfilling Seat Request via SuperAdmin Authority');
        const fulfillData = runPhpHelper('fulfill_latest_seat_request');
        recordResult('SuperAdmin Fulfillment', fulfillData.success, `New Status: ${fulfillData.new_status}, New Capacity: ${fulfillData.new_capacity}`);

        // Step 12: Verify Clinic Capacity Incremented After Fulfillment
        console.log('[STEP 12] Verifying Clinic Capacity Incremented to 2 After Fulfillment');
        await page.goto(`${BASE_URL}/subscription`, { waitUntil: 'domcontentloaded' });
        const capacityAfterFulfill = await page.locator('#current_total_capacity').innerText();
        recordResult('Capacity Incremented to 2 Seats', capacityAfterFulfill.includes('2'), capacityAfterFulfill);

        const availableAfterFulfill = await page.locator('#available_seats_count').innerText();
        recordResult('Available Seats Incremented to 1', availableAfterFulfill.includes('1'), availableAfterFulfill);

        // Step 13: Verify Doctor Creation is Now Available in Doctor Management
        console.log('[STEP 13] Verifying Doctor Creation Available in Doctor Management');
        await page.goto(`${BASE_URL}/settings/clinic-doctor`, { waitUntil: 'domcontentloaded' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_doctor_management_after_fulfill.png') });

        const addDoctorBtn = page.locator('#btn_add_doctor');
        recordResult('Add Doctor Button Available', await addDoctorBtn.isVisible(), 'Can register new doctor');

        await superContext.close();

    } catch (err) {
        console.error('Fatal error during doctor seat request workflow test:', err);
        recordResult('Doctor Seat Request E2E Execution', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n----------------------------------------------------------------------');
    console.log(`TOTAL: ${summary.total} | PASSED: ${summary.passed} | FAILED: ${summary.failed}`);
    console.log('----------------------------------------------------------------------\n');

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runDoctorSeatRequestWorkflowSuite();
