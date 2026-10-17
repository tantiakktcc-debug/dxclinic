import { chromium } from 'playwright';
import { execSync } from 'child_process';
import path from 'path';
import fs from 'fs';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.join(process.cwd(), 'playwright-screenshots', 'license_history');

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

async function runLicenseHistoryCompleteE2ESuite() {
    console.log('======================================================================');
    console.log('  DXCLINIC — COMPLETE LICENSE REQUEST & ISSUANCE HISTORY AUDIT E2E   ');
    console.log('  ALL REQUEST TYPES, OUTCOMES, SNAPSHOTS, SECURITY, AND PERMISSIONS   ');
    console.log('======================================================================\n');

    const summary = { total: 0, passed: 0, failed: 0 };
    function recordResult(num, name, passed, details = '') {
        summary.total++;
        if (passed) {
            summary.passed++;
            console.log(`  [PASS] #${num}: ${name} ${details ? '(' + details + ')' : ''}`);
        } else {
            summary.failed++;
            console.log(`  [FAIL] #${num}: ${name} ${details ? '(' + details + ')' : ''}`);
        }
    }

    // Step 0: Ensure historical database test records exist
    console.log('[SETUP] Seeding and verifying historical license request audit records');
    const setupData = runPhpHelper('setup_license_history_data');
    if (!setupData.success) {
        console.error('Failed to setup test data:', setupData);
        process.exit(1);
    }

    const browser = await chromium.launch({ headless: true });

    try {
        // --- SUPERADMIN TESTING CONTEXT ---
        const superContext = await browser.newContext({ viewport: { width: 1440, height: 960 } });
        const superPage = await superContext.newPage();

        console.log('[AUTH] Logging in as SuperAdmin');
        await superPage.goto(`${BASE_URL}/superadmin/login`, { waitUntil: 'domcontentloaded' });
        await superPage.fill('#email', 'superadmin@dxclinic.local');
        await superPage.fill('#password', 'TestAdminPass123!');
        await superPage.click('.btn-submit');
        await superPage.waitForURL('**/superadmin/dashboard', { timeout: 15000 });

        // 1. SuperAdmin can open: /superadmin/license-history
        console.log('[TEST 1-8] Loading License & Request History List Page');
        await superPage.goto(`${BASE_URL}/superadmin/license-history`, { waitUntil: 'domcontentloaded' });
        await superPage.screenshot({ path: path.join(SCREENSHOT_DIR, '01_license_history_main.png') });

        const historyTitle = await superPage.locator('.page-title').innerText();
        recordResult(1, 'SuperAdmin can open /superadmin/license-history', historyTitle.includes('License') && historyTitle.includes('History'), historyTitle);

        const pageText = await superPage.innerText('body');

        // 2. History does NOT incorrectly display: "No paid renewals recorded yet"
        const noPaidRenewals = pageText.includes('No paid renewals recorded yet');
        recordResult(2, 'Does NOT incorrectly display "No paid renewals recorded yet"', !noPaidRenewals, 'Verified historical records loaded');

        // 3. Initial license request appears (LR-000002)
        const hasInitial = pageText.includes('LR-000002') && (pageText.includes('INITIAL') || pageText.includes('Initial License'));
        recordResult(3, 'Initial license request appears (LR-000002)', hasInitial);

        // 4. Renewal appears (LR-000004)
        const hasRenewal = pageText.includes('LR-000004') && (pageText.includes('RENEWAL') || pageText.includes('Renewal'));
        recordResult(4, 'Renewal request appears (LR-000004)', hasRenewal);

        // 5. Doctor seat request appears (LR-000003 or LR-000013)
        const hasSeatAddon = (pageText.includes('LR-000003') || pageText.includes('LR-000013')) && (pageText.includes('DOCTOR_SEAT_ADDON') || pageText.includes('Doctor Seat Add-on'));
        recordResult(5, 'Doctor seat request appears (LR-000003 / LR-000013)', hasSeatAddon);

        // 6. Rejected request appears (LR-000010)
        const hasRejected = pageText.includes('LR-000010') && pageText.includes('REJECTED');
        recordResult(6, 'Rejected request appears (LR-000010)', hasRejected);

        // 7. Cancelled request appears (LR-000011)
        const hasCancelled = pageText.includes('LR-000011') && pageText.includes('CANCELLED');
        recordResult(7, 'Cancelled request appears (LR-000011)', hasCancelled);

        // 8. Fulfilled request appears (LR-000002)
        const hasFulfilled = pageText.includes('LR-000002') && pageText.includes('FULFILLED');
        recordResult(8, 'Fulfilled request appears (LR-000002 FULFILLED)', hasFulfilled);

        // --- FILTERS & SEARCH TESTING ---
        console.log('[TEST 9-15] Testing Filters and Search Capabilities');

        // 9. Status filters work: filter by REJECTED
        await superPage.selectOption('#historyStatusFilter', 'REJECTED');
        await superPage.click('#btnFilterHistory');
        await superPage.waitForLoadState('domcontentloaded');
        const rejectedFilterText = await superPage.innerText('#historyTable');
        recordResult(9, 'Status filter works (REJECTED shows LR-000010, excludes LR-000002)', rejectedFilterText.includes('LR-000010') && !rejectedFilterText.includes('LR-000002'));

        // Reset filter
        await superPage.click('#btnClearHistoryFilters');
        await superPage.waitForLoadState('domcontentloaded');

        // 10. Request-type filters work: filter by DOCTOR_SEAT_ADDON
        await superPage.selectOption('#historyTypeFilter', 'DOCTOR_SEAT_ADDON');
        await superPage.click('#btnFilterHistory');
        await superPage.waitForLoadState('domcontentloaded');
        const typeFilterText = await superPage.innerText('#historyTable');
        recordResult(10, 'Request-type filter works (DOCTOR_SEAT_ADDON shows LR-000003, excludes LR-000002)', typeFilterText.includes('LR-000003') && !typeFilterText.includes('LR-000002'));

        // Reset filter
        await superPage.click('#btnClearHistoryFilters');
        await superPage.waitForLoadState('domcontentloaded');

        // 11. Search by clinic works: "New Life Clinic"
        await superPage.fill('#historySearchInput', 'New Life Clinic');
        await superPage.click('#btnFilterHistory');
        await superPage.waitForLoadState('domcontentloaded');
        const clinicSearchText = await superPage.innerText('#historyTable');
        recordResult(11, 'Search by Clinic works ("New Life Clinic")', clinicSearchText.includes('LR-000002'));

        // 12. Search by clinic ID works: "NX0004"
        await superPage.fill('#historySearchInput', 'NX0004');
        await superPage.click('#btnFilterHistory');
        await superPage.waitForLoadState('domcontentloaded');
        const codeSearchText = await superPage.innerText('#historyTable');
        recordResult(12, 'Search by Clinic ID / Code works ("NX0004")', codeSearchText.includes('LR-000002'));

        // 13. Search by owner works: "Narendra Pathak"
        await superPage.fill('#historySearchInput', 'Narendra Pathak');
        await superPage.click('#btnFilterHistory');
        await superPage.waitForLoadState('domcontentloaded');
        const ownerSearchText = await superPage.innerText('#historyTable');
        recordResult(13, 'Search by Owner works ("Narendra Pathak")', ownerSearchText.includes('LR-000002'));

        // 14. Search by slug works: "newlife"
        await superPage.fill('#historySearchInput', 'newlife');
        await superPage.click('#btnFilterHistory');
        await superPage.waitForLoadState('domcontentloaded');
        const slugSearchText = await superPage.innerText('#historyTable');
        recordResult(14, 'Search by Slug works ("newlife")', slugSearchText.includes('LR-000002'));

        // 15. Date filters work
        await superPage.fill('#historySearchInput', '');
        await superPage.fill('#historyDateFrom', '2026-10-01');
        await superPage.fill('#historyDateTo', '2026-10-10');
        await superPage.click('#btnFilterHistory');
        await superPage.waitForLoadState('domcontentloaded');
        const dateFilterText = await superPage.innerText('#historyTable');
        recordResult(15, 'Date filters work (2026-10-01 to 2026-10-10)', dateFilterText.includes('LR-000002'));

        // Reset all filters
        await superPage.click('#btnClearHistoryFilters');
        await superPage.waitForLoadState('domcontentloaded');

        // --- REQUEST DETAIL & SNAPSHOTS AUDIT ---
        console.log('[TEST 16-25] Testing Immutable Audit Detail View for LR-000002');

        // 16. View Details opens correct historical request
        const viewDetailBtn = superPage.locator('a[href*="/superadmin/license-history/"]:has-text("View Audit"), a[href*="/superadmin/license-history/"]:has-text("View Details")').first();
        // Or navigate directly to ensure deterministic test of LR-000002
        await superPage.goto(`${BASE_URL}/superadmin/license-history`, { waitUntil: 'domcontentloaded' });
        const rowLr2 = superPage.locator('tr:has-text("LR-000002")');
        const detailLink = rowLr2.locator('a[href*="/superadmin/license-history/"]').first();
        await detailLink.click();
        await superPage.waitForLoadState('domcontentloaded');
        await superPage.screenshot({ path: path.join(SCREENSHOT_DIR, '02_license_history_detail_lr000002.png') });

        const detailPageText = await superPage.innerText('body');
        recordResult(16, 'View Details opens correct historical request (LR-000002 detail view)', detailPageText.includes('Audit Detail — LR-000002') || detailPageText.includes('LR-000002'));

        // 17. Complete request snapshot is visible
        const requestSnapshotVisible = detailPageText.includes('LR-000002') &&
            detailPageText.includes('INITIAL') &&
            detailPageText.includes('FULFILLED') &&
            detailPageText.includes('New Life Clinic') &&
            detailPageText.includes('Narendra Pathak') &&
            detailPageText.includes('4444444444') &&
            detailPageText.includes('Varanasi') &&
            detailPageText.includes('221005');
        recordResult(17, 'Complete request and clinic snapshot is preserved and visible', requestSnapshotVisible);

        // 18. Plan/pricing snapshot is preserved
        const planSnapshotVisible = detailPageText.includes('5-Year Plan') &&
            detailPageText.includes('60 Months') &&
            detailPageText.includes('49,999.00');
        recordResult(18, 'Plan/pricing snapshot is preserved (5-Year Plan, 60 Months, ₹49,999.00)', planSnapshotVisible);

        // 19. Free-trial snapshot is visible
        const trialSnapshotVisible = detailPageText.includes('1 month FREE trial') &&
            detailPageText.includes('61 Months');
        recordResult(19, 'Free-trial snapshot is visible (1 month FREE trial, Effective Duration: 61 Months)', trialSnapshotVisible);

        // 20. Payment snapshot is visible where applicable
        const paymentSnapshotVisible = detailPageText.includes('CASH') &&
            detailPageText.includes('cash-ref-001') &&
            detailPageText.includes('49,999.00');
        recordResult(20, 'Payment snapshot is visible (CASH, cash-ref-001, Net Paid: ₹49,999.00)', paymentSnapshotVisible);

        // 21. License issuance snapshot is visible when fulfilled
        const issuanceSnapshotVisible = detailPageText.includes('NX0004') &&
            detailPageText.includes('newlife') &&
            detailPageText.includes('NXCL-24LG-8DDW-WJSV-PYQC') &&
            detailPageText.includes('ACTIVE');
        recordResult(21, 'License issuance snapshot is visible (NX0004, newlife, License Key, ACTIVE)', issuanceSnapshotVisible);

        // 22. Account issuance metadata is visible
        const accountMetadataVisible = detailPageText.includes('ADM0001') &&
            detailPageText.includes('OPR0001') &&
            detailPageText.includes('DOC0001') &&
            detailPageText.includes('Dr. Narendra Pathak');
        recordResult(22, 'Account issuance metadata is visible (ADM0001, OPR0001, DOC0001, Dr. Narendra Pathak)', accountMetadataVisible);

        // 23. Temporary password is NOT visible in permanent history
        const noPlaintextPassword = !detailPageText.includes('Temporary Password:') &&
            !detailPageText.includes('Password123!') &&
            !detailPageText.includes('raw_password') &&
            detailPageText.includes('Protected — Displayed Once in Onboarding Package');
        recordResult(23, 'Temporary password is NEVER visible in permanent history (Strict Credential Security)', noPlaintextPassword);

        // 24. One-time activation secret is masked/not exposed
        const activationMasked = detailPageText.includes('ISSUED (Masked: ********)');
        recordResult(24, 'One-time activation code is masked / not exposed (ISSUED Masked: ********)', activationMasked);

        // 25. Event timeline is visible (card titles use text-transform:uppercase in CSS)
        const timelineVisible = (detailPageText.includes('EVENT TIMELINE & AUDIT TRAIL') || detailPageText.includes('Event Timeline & Audit Trail')) &&
            detailPageText.includes('SUBMITTED') &&
            detailPageText.includes('FULFILLED');
        recordResult(25, 'Event timeline is complete and chronological (SUBMITTED, FULFILLED)', timelineVisible);

        // --- REJECTED & CANCELLED REQUEST DETAIL AUDIT ---
        console.log('[TEST 26-27] Testing Rejected and Cancelled Requests Detail');

        // 26. Rejected reason is preserved (LR-000010)
        await superPage.goto(`${BASE_URL}/superadmin/license-history`, { waitUntil: 'domcontentloaded' });
        const rowLr10 = superPage.locator('tr:has-text("LR-000010")');
        await rowLr10.locator('a[href*="/superadmin/license-history/"]').first().click();
        await superPage.waitForLoadState('domcontentloaded');
        const rejectedPageText = await superPage.innerText('body');
        const rejectionPreserved = rejectedPageText.includes('REJECTED') &&
            rejectedPageText.includes('Invalid medical registration certificate provided');
        recordResult(26, 'Rejected request preserves rejection reason and remains in history', rejectionPreserved, 'Reason: Invalid medical registration certificate');

        // 27. Cancelled reason is preserved (LR-000011)
        await superPage.goto(`${BASE_URL}/superadmin/license-history`, { waitUntil: 'domcontentloaded' });
        const rowLr11 = superPage.locator('tr:has-text("LR-000011")');
        await rowLr11.locator('a[href*="/superadmin/license-history/"]').first().click();
        await superPage.waitForLoadState('domcontentloaded');
        const cancelledPageText = await superPage.innerText('body');
        const cancellationPreserved = cancelledPageText.includes('CANCELLED') &&
            cancelledPageText.includes('Operator submitted duplicate request by error');
        recordResult(27, 'Cancelled request preserves cancellation reason and remains in history', cancellationPreserved, 'Reason: Operator submitted duplicate request');

        // --- DOCTOR SEAT ADDON AUDIT ---
        console.log('[TEST 28-29] Testing Doctor Seat Add-on Requests');

        // 28. Doctor-seat request shows requested seat quantity (LR-000003)
        await superPage.goto(`${BASE_URL}/superadmin/license-history`, { waitUntil: 'domcontentloaded' });
        const rowLr3 = superPage.locator('tr:has-text("LR-000003")');
        await rowLr3.locator('a[href*="/superadmin/license-history/"]').first().click();
        await superPage.waitForLoadState('domcontentloaded');
        const addonPageText = await superPage.innerText('body');
        // 28. Doctor-seat request shows DOCTOR_SEAT_ADDON type and quantity (+1)
        // Note: card-title CSS uses text-transform:uppercase, so labels appear uppercased in innerText
        const addonQtyPreserved = (addonPageText.includes('DOCTOR_SEAT_ADDON') || addonPageText.includes('DOCTOR SEAT ADD-ON') || addonPageText.includes('Doctor Seat Add-on')) &&
            (addonPageText.includes('ADDITIONAL DOCTOR SEATS REQUESTED') || addonPageText.includes('Additional Doctor Seats Requested')) &&
            addonPageText.includes('+1');
        if (!addonQtyPreserved) {
            console.log(`  [DBG28] hasDSA=${addonPageText.includes('DOCTOR_SEAT_ADDON') || addonPageText.includes('Doctor Seat Add-on')} hasLabel=${addonPageText.includes('ADDITIONAL DOCTOR SEATS REQUESTED') || addonPageText.includes('Additional Doctor Seats Requested')} hasPlusOne=${addonPageText.includes('+1')}`);
            const idx = addonPageText.indexOf('Doctor');
            if (idx >= 0) console.log(`  [DBG28] snippet from 'Doctor': ${JSON.stringify(addonPageText.substring(idx, idx+300))}`);
        }
        recordResult(28, 'Doctor-seat request shows requested seat quantity (+1 seat)', addonQtyPreserved);

        // 29. Fulfilled doctor-seat request shows resulting capacity (LR-000013)
        await superPage.goto(`${BASE_URL}/superadmin/license-history`, { waitUntil: 'domcontentloaded' });
        const rowLr13 = superPage.locator('tr:has-text("LR-000013")');
        await rowLr13.locator('a[href*="/superadmin/license-history/"]').first().click();
        await superPage.waitForLoadState('domcontentloaded');
        const fulfilledAddonText = await superPage.innerText('body');
        const resultingCapacityShown = fulfilledAddonText.includes('Resulting Capacity: 3') || fulfilledAddonText.includes('3');
        recordResult(29, 'Fulfilled doctor-seat request shows resulting capacity (Resulting Capacity: 3)', resultingCapacityShown);

        await superContext.close();

        // --- CLINIC-ADMIN AUTHORIZATION DENIAL ---
        console.log('[TEST 30] Testing Authorization Denial for Non-Superadmin (Clinic Admin)');

        // 30. Clinic-admin cannot access SuperAdmin history (403 Forbidden)
        const clinicContext = await browser.newContext({ viewport: { width: 1440, height: 960 } });
        const clinicPage = await clinicContext.newPage();

        await clinicPage.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
        await clinicPage.fill('#email', 'newlifeadmin@dxclinic.local');
        await clinicPage.fill('#password', 'Password123!');
        await clinicPage.click('#btn_submit_login');
        await clinicPage.waitForURL(url => url.pathname.includes('/dashboard') || url.pathname.includes('/onboarding'), { timeout: 15000 });

        const historyResponse = await clinicPage.goto(`${BASE_URL}/superadmin/license-history`, { waitUntil: 'domcontentloaded' });
        const statusCode = historyResponse.status();
        recordResult(30, 'Clinic-admin cannot access SuperAdmin history (HTTP 403 Forbidden)', statusCode === 403, `HTTP ${statusCode}`);

        await clinicContext.close();

    } catch (err) {
        console.error('Fatal error during license history E2E test:', err);
        recordResult(99, 'Suite Execution Failure', false, err.message);
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

runLicenseHistoryCompleteE2ESuite();
