import { chromium } from 'playwright';
import { execSync } from 'child_process';
import path from 'path';
import fs from 'fs';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.join(process.cwd(), 'playwright-screenshots', 'multiclinic');

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

async function runMultiClinicTenantQueueSuite() {
    console.log('======================================================================');
    console.log('  DXCLINIC — MULTI-CLINIC TENANT ISOLATION & QUEUE REGRESSION E2E      ');
    console.log('  ONBOARDING IDENTITY / QUEUE VISIBILITY / TOKEN SCOPING / IDOR BLOCKS ');
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

    try {
        // -------------------------------------------------------------
        // CASE 1: Care Health Onboarding Identity
        // -------------------------------------------------------------
        console.log('[CASE 1] Care Health First Login & Onboarding Identity Verification');
        const careContext = await browser.newContext({ viewport: { width: 1400, height: 900 } });
        const carePage = await careContext.newPage();

        await carePage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await carePage.fill('#email', 'dr.binod@example.test');
        await carePage.fill('#password', 'Password123!');
        await carePage.click('#btn_submit_login');
        await carePage.waitForTimeout(2000);

        await carePage.goto(`${BASE_URL}/onboarding`, { waitUntil: 'networkidle' });
        await carePage.screenshot({ path: path.join(SCREENSHOT_DIR, '01_care_health_onboarding.png') });

        const careOnboardingBody = await carePage.innerText('body');
        recordResult('Care Health Onboarding Shows "Care Health"', careOnboardingBody.includes('Care Health'));
        recordResult('Care Health Onboarding Does NOT Show "Sharma"', !careOnboardingBody.includes('Sharma Clinic') && !careOnboardingBody.includes('Sharma Care'));

        // -------------------------------------------------------------
        // CASE 2 & 3: Book Patient in Care Health & Verify in Care Health Queue
        // -------------------------------------------------------------
        console.log('\n[CASE 2 & 3] Seed Booking in Care Health & Verify in Care Health Queue');
        const careSeed = runPhpHelper('seed_care_health_appointment');
        recordResult('Care Health Booking Created', careSeed.success, `ID: ${careSeed.appointment_id}, Token: ${careSeed.token}, Clinic: ${careSeed.clinic_name}`);

        await carePage.goto(`${BASE_URL}/clinic/carehealth/queue/live`, { waitUntil: 'networkidle' });
        await carePage.screenshot({ path: path.join(SCREENSHOT_DIR, '02_care_health_live_queue.png') });

        const careQueueBody = await carePage.innerText('body');
        recordResult('Care Health Booking Visible in Care Health Queue', careQueueBody.includes(careSeed.token) || careQueueBody.includes(careSeed.patient_name), `Token ${careSeed.token} found in Care Health queue`);

        // Also check Operator Arrival Queue
        await carePage.goto(`${BASE_URL}/clinic/carehealth/operator/queue`, { waitUntil: 'networkidle' });
        const careArrivalBody = await carePage.innerText('body');
        recordResult('Care Health Booking Visible in Operator Queue', careArrivalBody.includes(careSeed.token) || careArrivalBody.includes(careSeed.patient_name));

        // -------------------------------------------------------------
        // CASE 4: Login Sharma Clinic & Verify Care Health Booking NOT Visible
        // -------------------------------------------------------------
        console.log('\n[CASE 4] Login Sharma Clinic & Verify Care Health Booking NOT Visible');
        const sharmaContext = await browser.newContext({ viewport: { width: 1400, height: 900 } });
        const sharmaPage = await sharmaContext.newPage();

        await sharmaPage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await sharmaPage.fill('#email', 'admin@dxclinic.com');
        await sharmaPage.fill('#password', 'Password123!');
        await sharmaPage.click('#btn_submit_login');
        await sharmaPage.waitForTimeout(2000);

        // Verify Sharma Onboarding shows Sharma
        await sharmaPage.goto(`${BASE_URL}/onboarding`, { waitUntil: 'networkidle' });
        const sharmaOnboardingBody = await sharmaPage.innerText('body');
        recordResult('Sharma Clinic Onboarding Shows "Sharma"', sharmaOnboardingBody.includes('Sharma'));
        recordResult('Sharma Clinic Onboarding Does NOT Show "Care Health"', !sharmaOnboardingBody.includes('Care Health'));

        // Check Sharma Live Queue
        await sharmaPage.goto(`${BASE_URL}/clinic/sharmacare/queue/live`, { waitUntil: 'networkidle' });
        await sharmaPage.screenshot({ path: path.join(SCREENSHOT_DIR, '03_sharma_live_queue.png') });
        const sharmaQueueBody = await sharmaPage.innerText('body');

        recordResult('Care Health Patient NOT Leaked in Sharma Queue', !sharmaQueueBody.includes(careSeed.patient_name), `Patient ${careSeed.patient_name} not in Sharma queue`);

        // -------------------------------------------------------------
        // CASE 5: Book Patient in Sharma Clinic & Verify in Sharma Queue
        // -------------------------------------------------------------
        console.log('\n[CASE 5] Book Patient in Sharma Clinic & Verify in Sharma Queue');
        const sharmaSeed = runPhpHelper('seed_sharma_clinic_appointment');
        recordResult('Sharma Clinic Booking Created', sharmaSeed.success, `ID: ${sharmaSeed.appointment_id}, Token: ${sharmaSeed.token}`);

        await sharmaPage.reload({ waitUntil: 'networkidle' });
        const sharmaReloadedBody = await sharmaPage.innerText('body');
        recordResult('Sharma Booking Visible in Sharma Queue', sharmaReloadedBody.includes(sharmaSeed.patient_name), `Patient ${sharmaSeed.patient_name} visible`);

        // -------------------------------------------------------------
        // CASE 6: Verify Strict Clinic ID Separation in Database
        // -------------------------------------------------------------
        console.log('\n[CASE 6] Database Scope Verification (clinic_id integrity)');
        recordResult('Distinct Clinic IDs', careSeed.clinic_id !== sharmaSeed.clinic_id, `Care Health: ${careSeed.clinic_id}, Sharma: ${sharmaSeed.clinic_id}`);

        // -------------------------------------------------------------
        // CASE 7: Same Token Number in Two Clinics Allowed
        // -------------------------------------------------------------
        console.log('\n[CASE 7] Token Scoping: Same Token Number (555) in Both Clinics');
        recordResult('Identical Token Numbers Allowed Across Clinics', careSeed.token === sharmaSeed.token, `Both allocated token ${careSeed.token} scoped by clinic`);

        // -------------------------------------------------------------
        // CASE 8: Care Health Admin Cannot Access Sharma Appointment by ID
        // -------------------------------------------------------------
        console.log('\n[CASE 8] Cross-Tenant IDOR: Care Health Admin Accessing Sharma Appointment');
        const careCrossAccess = await carePage.goto(`${BASE_URL}/appointments/${sharmaSeed.appointment_id}`, { waitUntil: 'networkidle' });
        recordResult('Care Health Admin Forbidden on Sharma Appointment (HTTP 403)', careCrossAccess.status() === 403, `Status: ${careCrossAccess.status()}`);

        // -------------------------------------------------------------
        // CASE 9: Sharma Admin Cannot Access Care Health Appointment by ID
        // -------------------------------------------------------------
        console.log('\n[CASE 9] Cross-Tenant IDOR: Sharma Admin Accessing Care Health Appointment');
        const sharmaCrossAccess = await sharmaPage.goto(`${BASE_URL}/appointments/${careSeed.appointment_id}`, { waitUntil: 'networkidle' });
        recordResult('Sharma Admin Forbidden on Care Health Appointment (HTTP 403)', sharmaCrossAccess.status() === 403, `Status: ${sharmaCrossAccess.status()}`);

        await careContext.close();
        await sharmaContext.close();

    } catch (err) {
        console.error('Fatal error during multi-clinic tenant isolation test:', err);
        recordResult('Multi-Clinic Tenant E2E Execution', false, err.message);
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

runMultiClinicTenantQueueSuite();
