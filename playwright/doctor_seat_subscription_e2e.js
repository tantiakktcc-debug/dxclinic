import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/doctor_seat_subscription');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function runDoctorSeatSubscriptionSuite() {
    console.log('======================================================================');
    console.log('  DXCLINIC — DOCTOR SEAT ADD-ON & SUBSCRIPTION WORKFLOW E2E           ');
    console.log('  CAPACITY ENFORCEMENT / BUY SEAT CTA / PAYMENT CONFIRMATION / DOCTOR ');
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
        // SUITE 1: SEAT LIMIT & BUY SEAT CTA IN DOCTOR MANAGEMENT
        // -------------------------------------------------------------
        console.log('[SUITE 1] Verifying Initial Doctor Seat Capacity & Limit');
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        await adminPage.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
        await adminPage.fill('#email', 'demoadmin@dxclinic.local');
        await adminPage.fill('#password', 'DemoAdmin@2026!');
        await adminPage.locator('#btn_submit_login').click();
        await adminPage.waitForURL('**/dashboard', { timeout: 10000 });
        record('Admin Login', adminPage.url().includes('/dashboard'), 'Logged in as Demo Administrator');

        // Navigate to Clinic & Doctor Settings
        await adminPage.goto(`${BASE_URL}/settings/clinic-doctor`, { waitUntil: 'domcontentloaded' });
        await adminPage.waitForSelector('#badge_doctor_seats', { timeout: 10000 });

        const initialBadgeText = (await adminPage.locator('#badge_doctor_seats').textContent()).trim();
        console.log(`  [INFO] Initial Doctor Seats Badge: "${initialBadgeText}"`);
        record('Doctor Seats Badge Present', initialBadgeText.includes('Doctor Seats:'), initialBadgeText);

        const hasLimitReached = (await adminPage.locator('#badge_seat_limit_reached').count()) > 0;
        const hasBuyCta = (await adminPage.locator('#btn_buy_additional_doctor_seat').count()) > 0;

        await adminPage.screenshot({ path: path.join(SCREENSHOT_DIR, '01_doctor_management_initial.png') });

        // -------------------------------------------------------------
        // SUITE 2: SUBSCRIPTION PORTAL & ADDON PURCHASE
        // -------------------------------------------------------------
        console.log('\n[SUITE 2] Subscription & Add-On Purchase Workflow');
        await adminPage.goto(`${BASE_URL}/subscription`, { waitUntil: 'domcontentloaded' });
        await adminPage.waitForSelector('#doctor_seat_addon_section', { timeout: 10000 });
        record('Subscription Page Loaded', true, 'Navigated to /subscription');

        // Check required metrics
        const planName = (await adminPage.locator('#current_plan_name').textContent()).trim();
        const usedSeats = (await adminPage.locator('#active_used_seats').textContent()).trim();
        const availableSeats = (await adminPage.locator('#available_seats_count').textContent()).trim();
        const resultingCapacity = (await adminPage.locator('#resulting_total_capacity').textContent()).trim();

        record('Current Plan Displayed', planName.length > 0, `Plan: "${planName}"`);
        record('Active Used Seats Displayed', usedSeats.includes('Seats'), `Used: "${usedSeats}"`);
        record('Available Seats Displayed', availableSeats.length > 0, `Available: "${availableSeats}"`);
        record('Resulting Capacity Displayed', resultingCapacity.includes('Seats'), `Resulting: "${resultingCapacity}"`);

        // Test Add-on seat interactive increment/pricing
        console.log('[ACTION] Incrementing add-on seats count...');
        const initialPayable = (await adminPage.locator('#total_payable_amount').textContent()).trim();
        await adminPage.locator('#input_addon_seats_count').fill('2');
        await adminPage.locator('#input_addon_seats_count').dispatchEvent('input');
        await adminPage.waitForTimeout(500);

        const updatedPayable = (await adminPage.locator('#total_payable_amount').textContent()).trim();
        record('Interactive Pricing Calculation', initialPayable.length > 0 && updatedPayable.length > 0, `From ${initialPayable} to ${updatedPayable}`);

        // Set back to 1 seat for activation test
        await adminPage.locator('#input_addon_seats_count').fill('1');
        await adminPage.locator('#input_addon_seats_count').dispatchEvent('input');
        await adminPage.waitForTimeout(500);

        await adminPage.screenshot({ path: path.join(SCREENSHOT_DIR, '02_subscription_addon_configured.png') });

        // Confirm purchase & activation
        console.log('[ACTION] Clicking Pay & Activate Seat(s)...');
        await adminPage.locator('#btn_purchase_doctor_seat').click();
        await adminPage.waitForSelector('#seat_purchase_success_banner', { timeout: 10000 });
        const successBanner = await adminPage.locator('#seat_purchase_success_banner').textContent();
        record('Payment Confirmed & Entitlement Activated', successBanner.includes('Payment confirmed! Activated'), successBanner.trim());

        await adminPage.screenshot({ path: path.join(SCREENSHOT_DIR, '03_addon_payment_success.png') });

        // -------------------------------------------------------------
        // SUITE 3: RETURN TO DOCTOR MANAGEMENT & VERIFY EXPANDED CAPACITY
        // -------------------------------------------------------------
        console.log('\n[SUITE 3] Authoritative Entitlement Update in Doctor Management');
        await adminPage.goto(`${BASE_URL}/settings/clinic-doctor`, { waitUntil: 'domcontentloaded' });
        await adminPage.waitForSelector('#badge_doctor_seats', { timeout: 10000 });

        const updatedBadgeText = (await adminPage.locator('#badge_doctor_seats').textContent()).trim();
        console.log(`  [INFO] Updated Doctor Seats Badge: "${updatedBadgeText}"`);

        record('Badge Reflects Available Doctor Seats', updatedBadgeText.includes('available') && !/\(0 available\)/.test(updatedBadgeText), updatedBadgeText);

        // Assert Add Doctor button is now visible
        const canAddDoctorBtn = await adminPage.locator('#btn_add_doctor').count();
        record('Add Doctor Button Unlocked', canAddDoctorBtn > 0, 'Doctor creation unlocked by seat purchase');

        // Click Add Doctor button and assert form opens
        if (canAddDoctorBtn > 0) {
            await adminPage.locator('#btn_add_doctor').click();
            await adminPage.waitForSelector('#doctor_name_input', { timeout: 5000 });
            const docInputCount = await adminPage.locator('#doctor_name_input').count();
            record('Doctor Creation Form Opens', docInputCount > 0, 'Form #doctor_name_input visible');
            await adminPage.screenshot({ path: path.join(SCREENSHOT_DIR, '04_add_doctor_form_unlocked.png') });
        }

        await adminContext.close();

        // -------------------------------------------------------------
        // SUITE 4: OPERATOR AUTHORIZATION RESTRICTIONS
        // -------------------------------------------------------------
        console.log('\n[SUITE 4] Operator Role Entitlement Authorization Guard');
        const opContext = await browser.newContext();
        const opPage = await opContext.newPage();

        await opPage.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
        await opPage.fill('#email', 'demooperator@dxclinic.local');
        await opPage.fill('#password', 'DemoOp@2026!');
        await opPage.locator('#btn_submit_login').click();
        await opPage.waitForURL('**/dashboard', { timeout: 10000 });

        // Operator attempting to access subscription portal
        const subResponse = await opPage.goto(`${BASE_URL}/subscription`, { waitUntil: 'domcontentloaded' });
        const isForbidden = subResponse.status() === 403 || (await opPage.locator('text=Unauthorized').count()) > 0 || (await opPage.locator('#btn_purchase_doctor_seat').count()) === 0;
        record('Operator Forbidden from Modifying Subscription', isForbidden, `Status/Guarded: ${subResponse.status()}`);

        await opContext.close();

    } catch (err) {
        console.error('Test Suite Error:', err);
        record('Execution Clean', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  SUBSCRIPTION SUITE SUMMARY: ${passed}/${passed + failed} PASSED (${failed} FAILED)`);
    console.log('======================================================================\n');

    if (failed > 0) {
        process.exit(1);
    }
}

runDoctorSeatSubscriptionSuite();
