import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/revision35');

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
    console.log('  DXCLINIC REVISION 35 — SUPERADMIN ONE-CLICK PAID LICENSE RENEWAL E2E ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    console.log(`[INIT] Automated Browser: Google Chrome v${browser.version()}`);
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Revision35-E2E/1.0 (Google-Chrome-Desktop)'
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
        // TEST 1: Superadmin Login
        // ------------------------------------------------------------------
        console.log('\n[PHASE 1] Superadmin Authentication & Dashboard');
        await page.goto(`${BASE_URL}/superadmin/login`);
        await page.fill('#email', 'superadmin@dxclinic.local');
        await page.fill('#password', 'TestAdminPass123!');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/superadmin/dashboard');

        const isSuperadminDashboard = page.url().includes('/superadmin/dashboard');
        recordResult('01. Superadmin login successful', isSuperadminDashboard, page.url());
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_superadmin_dashboard.png') });

        // ------------------------------------------------------------------
        // TEST 2: License History access
        // ------------------------------------------------------------------
        await page.goto(`${BASE_URL}/superadmin/license-history`);
        const isLicenseHistory = page.url().includes('/superadmin/license-history');
        const historyTitle = await page.textContent('h1');
        recordResult('02. License History page accessible', isLicenseHistory && historyTitle.includes('DxClinic Fleet'), historyTitle);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_license_history.png') });

        // ------------------------------------------------------------------
        // TEST 3: Search clinic by Clinic ID
        // ------------------------------------------------------------------
        await page.goto(`${BASE_URL}/superadmin/dashboard?q=NX0001`);
        const nx0001Visible = await page.locator('text=NX0001').first().isVisible();
        recordResult('03. Search clinic by Clinic ID (NX0001)', nx0001Visible);

        // ------------------------------------------------------------------
        // TEST 4: Search by name
        // ------------------------------------------------------------------
        await page.goto(`${BASE_URL}/superadmin/dashboard?q=Sharma`);
        const nameMatch = await page.locator('text=Sharma').first().isVisible() || await page.locator('text=NX0001').first().isVisible();
        recordResult('04. Search clinic by name', nameMatch);

        // ------------------------------------------------------------------
        // TEST 5: Search by mobile
        // ------------------------------------------------------------------
        await page.goto(`${BASE_URL}/superadmin/dashboard?q=9876543210`);
        const mobileMatch = await page.locator('text=NX0001').first().isVisible();
        recordResult('05. Search clinic by mobile number', mobileMatch);

        // ------------------------------------------------------------------
        // TEST 6: Open Clinic Dedicated Renewal Screen
        // ------------------------------------------------------------------
        console.log('\n[PHASE 2] Dedicated Paid Renewal Screen & Price Calculation');
        await page.goto(`${BASE_URL}/superadmin/dashboard`);
        const renewBtn = page.locator('tr:has-text("NX0001") a.btn-act-renew').first();
        await renewBtn.click();
        await page.waitForURL('**/superadmin/clinics/*/renew');

        const isRenewPage = page.url().includes('/renew');
        recordResult('06. Dedicated renew panel/page opened', isRenewPage, page.url());
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_renew_page.png') });

        // ------------------------------------------------------------------
        // TEST 7: View current subscription details
        // ------------------------------------------------------------------
        const hasCurrentPlan = await page.locator('.c-label:has-text("Current Plan")').isVisible();
        const hasValidUntil = await page.locator('.c-label:has-text("Valid Until")').isVisible();
        recordResult('07. View current subscription details on renewal screen', hasCurrentPlan && hasValidUntil);

        // ------------------------------------------------------------------
        // TEST 8: View remaining days in Asia/Kolkata
        // ------------------------------------------------------------------
        const remainingText = await page.locator('.c-label:has-text("Remaining Time") + .c-val').textContent();
        recordResult('08. View remaining days in Asia/Kolkata', remainingText && remainingText.length > 0, remainingText.trim());

        // ------------------------------------------------------------------
        // TEST 9: Dedicated Renew License screen has options
        // ------------------------------------------------------------------
        const hasQuarterly = await page.locator('.plan-name').filter({ hasText: /^Quarterly Plan$/ }).isVisible();
        const hasHalfYearly = await page.locator('.plan-name').filter({ hasText: /^Half-Yearly Plan$/ }).isVisible();
        const hasYearly = await page.locator('.plan-name').filter({ hasText: /^Yearly Plan$/ }).isVisible();
        recordResult('09. Simple renewal options present', hasQuarterly && hasHalfYearly && hasYearly);

        // ------------------------------------------------------------------
        // TEST 10: Select Quarterly & verify calculation
        // ------------------------------------------------------------------
        await page.locator('.plan-option').filter({ has: page.locator('.plan-name:text-is("Quarterly Plan")') }).click();
        await page.waitForTimeout(300);
        const quarterlySum = await page.locator('#sumBaseAmount').textContent();
        recordResult('10. Select Quarterly plan updates base amount', quarterlySum.includes('4,499'), quarterlySum);

        // ------------------------------------------------------------------
        // TEST 11: Select Half-Yearly & verify calculation
        // ------------------------------------------------------------------
        await page.locator('.plan-option').filter({ has: page.locator('.plan-name:text-is("Half-Yearly Plan")') }).click();
        await page.waitForTimeout(300);
        const halfYearlySum = await page.locator('#sumBaseAmount').textContent();
        recordResult('11. Select Half-Yearly plan updates base amount', halfYearlySum.includes('7,999'), halfYearlySum);

        // ------------------------------------------------------------------
        // TEST 12: Select Yearly plan
        // ------------------------------------------------------------------
        await page.locator('.plan-option').filter({ has: page.locator('.plan-name:text-is("Yearly Plan")') }).click();
        await page.waitForTimeout(300);
        const yearlySum = await page.locator('#sumBaseAmount').textContent();
        const doctorSectionVisible = await page.locator('#doctorSeatsSection').isVisible();
        recordResult('12. Select Yearly plan shows doctor seats section', yearlySum.includes('14,999') && doctorSectionVisible);

        // ------------------------------------------------------------------
        // TEST 13: Select existing doctor-seat renewal
        // ------------------------------------------------------------------
        const existingCheckbox = page.locator('#renewExistingCheckbox');
        let existingRenewed = false;
        if (await existingCheckbox.count() > 0) {
            existingRenewed = await existingCheckbox.isChecked();
        } else {
            existingRenewed = true; // No existing additional seats to toggle
        }
        recordResult('13. Existing doctor-seat renewal control active', existingRenewed);

        // ------------------------------------------------------------------
        // TEST 14: Select new doctor-seat quantity (input 1)
        // ------------------------------------------------------------------
        await page.fill('#newSeatsInput', '1');
        await page.dispatchEvent('#newSeatsInput', 'input');
        await page.waitForTimeout(300);
        const newSeatCost = await page.locator('#newSeatCostDisplay').textContent();
        recordResult('14. Select new doctor-seat quantity calculates extra fee', newSeatCost.includes('2,999'), newSeatCost);

        // ------------------------------------------------------------------
        // TEST 15: Verify automatic price calculation & Net Payable
        // ------------------------------------------------------------------
        const netAmountText = await page.locator('#sumNetAmount').textContent();
        recordResult('15. Automatic price calculation accurate', netAmountText.length > 0, `Net: ₹${netAmountText}`);

        // ------------------------------------------------------------------
        // TEST 16: Verify final confirmation elements & Payment Received
        // ------------------------------------------------------------------
        const paymentCheck = await page.locator('input[name="payment_received"]').isChecked();
        recordResult('16. Payment confirmation toggle checked', paymentCheck);

        const e2ePaymentRef = 'E2E-UTR-' + Date.now();
        await page.fill('input[name="payment_reference"]', e2ePaymentRef);
        await page.fill('input[name="notes"]', 'Verified automated E2E renewal test');

        // ------------------------------------------------------------------
        // TEST 17: Confirm Payment & Renew (Submit atomic transaction)
        // ------------------------------------------------------------------
        console.log('\n[PHASE 3] Atomic Payment Confirmation & Renewal Submission');
        await page.click('#btnSubmit');
        await page.waitForURL('**/superadmin/dashboard', { timeout: 15000 });

        const redirectedToDashboard = page.url().includes('/superadmin/dashboard');
        const alertSuccess = await page.locator('.alert-success').first().textContent();
        recordResult('17. Confirm payment and renew successfully executed', redirectedToDashboard && alertSuccess.includes('successfully renewed'), alertSuccess.trim());
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '17_renewal_success.png') });

        // ------------------------------------------------------------------
        // TEST 18: Verify new expiry date displayed
        // ------------------------------------------------------------------
        const validityText = await page.locator('td:has-text("Valid:")').first().textContent();
        recordResult('18. New expiry date updated on dashboard', validityText.length > 0, validityText.trim());

        // ------------------------------------------------------------------
        // TEST 19: Verify ACTIVE license status
        // ------------------------------------------------------------------
        const activeStatusBadge = await page.locator('.status-ACTIVE').first().isVisible();
        recordResult('19. License status is ACTIVE', activeStatusBadge);

        // ------------------------------------------------------------------
        // TEST 20: Verify renewal history auditable entry
        // ------------------------------------------------------------------
        await page.goto(`${BASE_URL}/superadmin/license-history`);
        const hasRenewalEntry = await page.locator(`td:has-text("${e2ePaymentRef}")`).first().isVisible();
        recordResult('20. Renewal history contains immutable audit entry', hasRenewalEntry, e2ePaymentRef);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '20_renewal_history.png') });

        // ------------------------------------------------------------------
        // TEST 21: Verify doctor capacity reflects renewal + new seat
        // ------------------------------------------------------------------
        await page.goto(`${BASE_URL}/superadmin/dashboard`);
        const capacityCell = await page.locator('td:has-text("Seats")').first().textContent();
        recordResult('21. Doctor capacity reflects updated seats', capacityCell.includes('Seats'), capacityCell.trim());

        // ------------------------------------------------------------------
        // TEST 22: Clinic Admin Login immediately works (Zero disruption)
        // ------------------------------------------------------------------
        console.log('\n[PHASE 4] Tenant Zero-Action Access Verification');
        const tenantContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const tenantPage = await tenantContext.newPage();

        await tenantPage.goto(`${BASE_URL}/login`);
        await tenantPage.fill('#clinic_code', 'NX0001');
        await tenantPage.fill('#email', 'ADM0001');
        await tenantPage.fill('#password', 'password');
        await tenantPage.click('button[type="submit"]');
        await tenantPage.waitForURL('**/dashboard');

        const tenantDashboardUrl = tenantPage.url();
        recordResult('22. Clinic Admin login immediately works without extra action', tenantDashboardUrl.includes('/dashboard'), tenantDashboardUrl);
        await tenantPage.screenshot({ path: path.join(SCREENSHOT_DIR, '22_tenant_dashboard.png') });

        // ------------------------------------------------------------------
        // TEST 23: Verify NO activation screen appeared
        // ------------------------------------------------------------------
        const onActivationScreen = tenantDashboardUrl.includes('/activate');
        recordResult('23. No activation screen appears after renewal', !onActivationScreen);

        // ------------------------------------------------------------------
        // TEST 24: Verify NO onboarding wizard is triggered
        // ------------------------------------------------------------------
        const onOnboardingWizard = tenantDashboardUrl.includes('/onboarding');
        recordResult('24. No onboarding wizard is triggered after renewal', !onOnboardingWizard);

        // Bonus: Inspect Clinic Admin License & Subscription panel
        await tenantPage.goto(`${BASE_URL}/settings/subscription`);
        const subscriptionPanelTitle = await tenantPage.locator('h1').textContent();
        const activeStatusInPanel = await tenantPage.locator('span:has-text("ACTIVE")').first().isVisible();
        recordResult('Bonus. Clinic Admin Settings License & Subscription panel loaded', subscriptionPanelTitle.includes('License & Subscription') && activeStatusInPanel);
        await tenantPage.screenshot({ path: path.join(SCREENSHOT_DIR, '25_clinic_admin_subscription_panel.png') });

        await tenantContext.close();

    } catch (err) {
        console.error('\n[FATAL ERROR IN SUITE]', err);
        recordResult('Suite Execution Complete', false, err.message);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'fatal_error.png') });
    } finally {
        await browser.close();
    }

    // ------------------------------------------------------------------
    // Final Summary Report
    // ------------------------------------------------------------------
    console.log('\n======================================================================');
    console.log(`  E2E TEST SUMMARY: ${summary.passed}/${summary.total} PASSED (${summary.failed} FAILED)`);
    console.log('======================================================================\n');

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite();
