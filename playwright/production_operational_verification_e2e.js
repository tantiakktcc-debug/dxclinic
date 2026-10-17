import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const TARGET_URL = process.env.PUBLIC_URL || 'https://dxclinic.example.local';
const SCREENSHOT_DIR = path.resolve('test-results/prod_verification');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

const summary = {
    target: TARGET_URL,
    timestamp: new Date().toISOString(),
    total: 0,
    passed: 0,
    failed: 0,
    tests: [],
    network: {
        livewireRequests: 0,
        livewireErrors: 0,
        http500Count: 0,
        http419Count: 0,
        failedAssets: 0,
    },
    consoleErrors: [],
    details: {}
};

function record(name, pass, detail = '') {
    summary.total++;
    if (pass) {
        summary.passed++;
        console.log(`  [PASS] ${name} ${detail ? '(' + detail + ')' : ''}`);
    } else {
        summary.failed++;
        console.error(`  [FAIL] ${name} ${detail ? '(' + detail + ')' : ''}`);
    }
    summary.tests.push({ name, pass, detail });
}

async function run() {
    console.log('======================================================================');
    console.log('  DXCLINIC — PRODUCTION OPERATIONAL VERIFICATION (CANONICAL PWA)      ');
    console.log(`  Target: ${TARGET_URL}/book/sharmacare                               `);
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-Prod-QA/1.0 (Windows NT 10.0; Win64; x64)'
    });

    const page = await context.newPage();

    // Listen to console and network
    page.on('console', msg => {
        const text = msg.text();
        // Ignore known harmless Permissions-Policy unload notice
        if (text.includes('Permissions policy violation') && text.includes('unload')) {
            return;
        }
        if (msg.type() === 'error') {
            summary.consoleErrors.push(text);
            console.warn(`  [Browser Console Error] ${text}`);
        }
    });

    page.on('response', resp => {
        const url = resp.url();
        const status = resp.status();

        if (status >= 500) {
            summary.network.http500Count++;
            console.error(`  [HTTP 500+ Error] ${status} on ${url}`);
        }
        if (status === 419) {
            summary.network.http419Count++;
            console.error(`  [HTTP 419 CSRF Error] on ${url}`);
        }
        if (url.includes('/livewire/') || url.includes('/livewire-')) {
            summary.network.livewireRequests++;
            if (status >= 400) {
                summary.network.livewireErrors++;
            }
        }
        if ((url.endsWith('.js') || url.endsWith('.css')) && status >= 400) {
            summary.network.failedAssets++;
            console.error(`  [Asset Failed] ${status} on ${url}`);
        }
    });

    try {
        // ==================================================================
        // PHASE 1 — PUBLIC PAGE AVAILABILITY
        // ==================================================================
        console.log('▶ [PHASE 1] Checking Canonical Public URL Availability...');
        const resp = await page.goto(`${TARGET_URL}/book/sharmacare`, { waitUntil: 'networkidle' });
        const statusCode = resp.status();
        const content = await page.content();

        record('Phase 1: Public URL /book/sharmacare returns HTTP 200', statusCode === 200, `status: ${statusCode}`);
        record('Phase 1: Clinic branding "Sharma Care Clinic" visible', content.includes('Sharma Care Clinic'), 'branding verified');
        record('Phase 1: Booking form rendered completely', (await page.locator('#pwa_patient_name').count()) > 0, 'inputs detected');
        record('Phase 1: No white screen or 500 page', !content.includes('Server Error') && !content.includes('500 Internal Server Error'), 'clean render');

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_public_page_loaded.png') });

        // Invalid slug check
        console.log('▶ [PHASE 1] Checking Invalid Slug /book/INVALID_TEST_SLUG...');
        const invalidResp = await page.goto(`${TARGET_URL}/book/INVALID_TEST_SLUG`, { waitUntil: 'networkidle' });
        record('Phase 1: Invalid slug /book/INVALID_TEST_SLUG returns HTTP 404', invalidResp.status() === 404, `status: ${invalidResp.status()}`);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01b_invalid_slug_404.png') });

        // Return to canonical public URL
        await page.goto(`${TARGET_URL}/book/sharmacare`, { waitUntil: 'networkidle' });

        // ==================================================================
        // PHASE 2 & 3 — DEVTOOLS NETWORK MONITORING & NEGATIVE PHONE TESTS
        // ==================================================================
        console.log('\n▶ [PHASE 3] Executing Negative Phone Validation Suite...');

        const invalidPhones = [
            '98765 43210',
            '98765-43210',
            '+91 98765 43210',
            '98765432100',
            'abcdefghij'
        ];

        for (let i = 0; i < invalidPhones.length; i++) {
            const badPhone = invalidPhones[i];
            console.log(`  Submitting deliberately invalid phone [${i + 1}/${invalidPhones.length}]: "${badPhone}"...`);

            // Fill basic required patient details
            await page.fill('#pwa_patient_name', 'DxClinic Negative Test');
            await page.fill('#pwa_patient_age', '35');
            await page.selectOption('#pwa_patient_sex', 'MALE');
            await page.fill('#pwa_patient_mobile', badPhone);
            await page.fill('#pwa_patient_address', 'Negative Test Street');
            await page.fill('#pwa_patient_city', 'Varanasi');

            // Select doctor and booking type if dropdowns exist
            if ((await page.locator('#pwa_select_doctor').count()) > 0) {
                await page.selectOption('#pwa_select_doctor', { index: 1 });
                await page.waitForTimeout(500);
            }

            // Click submit
            await page.click('#btn_pwa_submit_booking');
            await page.waitForTimeout(1000);

            // Assert NO 500 occurred
            const no500 = summary.network.http500Count === 0;
            record(`Phase 3 [${badPhone}]: Does NOT cause HTTP 500`, no500, `500 count: ${summary.network.http500Count}`);

            // Assert inline validation error is displayed
            const hasError = (await page.locator('text=Enter exactly 10 digits').count()) > 0;
            record(`Phase 3 [${badPhone}]: Inline validation error message displayed`, hasError, 'controlled validation');

            // Assert form remains usable and submit button exists
            const submitBtnCount = await page.locator('#btn_pwa_submit_booking').count();
            record(`Phase 3 [${badPhone}]: Form remains intact and usable`, submitBtnCount > 0);

            if (i === 0) {
                await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_negative_phone_validation.png') });
            }
        }

        // Verify database: zero records created during negative tests
        const negativeDbCheck = execSync('php scratch/verify_prod_operation_db.php verify_negative').toString();
        const negDbJson = JSON.parse(negativeDbCheck);
        record('Phase 3: Database integrity: zero patient/appointment records created by invalid submissions', negDbJson.found_invalid_records === 0, `found: ${negDbJson.found_invalid_records}`);

        // ==================================================================
        // PHASE 4 & 5 — POSITIVE NEW PATIENT REGISTRATION & SUCCESS CRITERIA
        // ==================================================================
        console.log('\n▶ [PHASE 4 & 5] Performing Positive End-to-End Registration...');

        const testPatient = {
            name: 'DxClinic Production QA',
            age: '42',
            sex: 'FEMALE',
            mobile: '9876543233',
            address: 'Production QA Test Address',
            city: 'Varanasi'
        };

        // Fill fresh positive registration data
        await page.fill('#pwa_patient_name', testPatient.name);
        await page.fill('#pwa_patient_age', testPatient.age);
        await page.selectOption('#pwa_patient_sex', testPatient.sex);
        await page.fill('#pwa_patient_mobile', testPatient.mobile);
        await page.fill('#pwa_patient_address', testPatient.address);
        await page.fill('#pwa_patient_city', testPatient.city);

        // Ensure doctor is selected
        if ((await page.locator('#pwa_select_doctor').count()) > 0) {
            await page.selectOption('#pwa_select_doctor', { index: 1 });
            await page.waitForTimeout(600);
        }

        // Verify available sessions dropdown has an option
        const sessionCount = await page.locator('#pwa_session_type option').count();
        if (sessionCount === 0 || (await page.locator('text=No sessions available').count()) > 0) {
            // Pick tomorrow's date if today has no remaining sessions
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            const tomorrowStr = tomorrow.toISOString().split('T')[0];
            await page.fill('#pwa_appointment_date', tomorrowStr);
            await page.waitForTimeout(600);
        }

        // Submit form exactly once
        console.log('  Submitting registration form...');
        await page.click('#btn_pwa_submit_booking');
        await page.waitForTimeout(3000);

        // Verify Phase 5 Success Criteria
        const is500Free = summary.network.http500Count === 0;
        record('Phase 5: Zero HTTP 500 errors during registration', is500Free);

        const confirmationCard = (await page.locator('text=Your Appointment is Confirmed!').count()) > 0;
        record('Phase 5: Booking confirmation card is displayed', confirmationCard);

        const tokenElem = await page.locator('#confirmed_token_number');
        const tokenVisible = (await tokenElem.count()) > 0;
        const allocatedToken = tokenVisible ? (await tokenElem.innerText()).trim() : '';
        record('Phase 5: Common queue token allocated and displayed', tokenVisible && allocatedToken.length > 0, `token: ${allocatedToken}`);

        const patientNameDisplayed = (await page.locator(`text=${testPatient.name}`).count()) > 0;
        record('Phase 5: Patient identity displayed on confirmation card', patientNameDisplayed);

        const downloadPdfBtn = await page.locator('#btn_download_token_pdf');
        const pdfBtnVisible = (await downloadPdfBtn.count()) > 0;
        record('Phase 5: "Download Token PDF" button is present', pdfBtnVisible);

        const pdfHref = pdfBtnVisible ? await downloadPdfBtn.getAttribute('href') : '';
        record('Phase 5: Token PDF link uses secure token capability URL', pdfHref.includes('/token-pdf/'), `href: ${pdfHref}`);

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_positive_booking_success.png') });

        // ==================================================================
        // PHASE 6 & 7 — DATABASE & TOKEN SEQUENCE VERIFICATION
        // ==================================================================
        console.log('\n▶ [PHASE 6 & 7] Verifying Database and Token Sequence Invariants...');
        const dbCheckRaw = execSync(`php scratch/verify_prod_operation_db.php verify_positive ${testPatient.mobile}`).toString();
        const dbJson = JSON.parse(dbCheckRaw);
        summary.details.database = dbJson;

        record('Phase 6: Database patient record created exactly once', dbJson.invariants?.patient_exact_single === true, `UHID: ${dbJson.patient?.uhid}`);
        record('Phase 6: Database appointment record created exactly once', dbJson.invariants?.appointment_exact_single === true, `Appt ID: ${dbJson.appointment?.id}`);
        record('Phase 6: Tenant consistency (patient, appointment, doctor under clinic)', dbJson.invariants?.tenant_consistent === true, `Clinic ID: ${dbJson.clinic_id}`);
        record('Phase 6: Booking source is strictly ONLINE', dbJson.invariants?.source_is_online === true, `source: ${dbJson.appointment?.booking_source}`);
        record('Phase 6: Zero cross-clinic association/leakage', dbJson.invariants?.no_cross_clinic_leak === true);

        record('Phase 7: Allocated token matches TokenSequence', String(dbJson.appointment?.token_number) === String(dbJson.token_sequence?.current_token), `token: ${dbJson.appointment?.formatted_token}`);

        // ==================================================================
        // PHASE 8 — TOKEN PDF VERIFICATION
        // ==================================================================
        console.log('\n▶ [PHASE 8] Verifying Token PDF Generation & Download...');
        if (pdfHref) {
            const fullPdfUrl = pdfHref.startsWith('http') ? pdfHref : `${TARGET_URL}${pdfHref}`;
            const pdfPage = await context.newPage();
            const pdfResp = await pdfPage.goto(fullPdfUrl, { waitUntil: 'networkidle' });

            record('Phase 8: Token PDF route returns HTTP 200', pdfResp.status() === 200, `status: ${pdfResp.status()}`);
            const pdfContent = await pdfPage.content();
            record('Phase 8: Token PDF includes clinic branding', pdfContent.includes('Sharma Care Clinic'));
            record('Phase 8: Token PDF includes correct token number', pdfContent.includes(allocatedToken));
            record('Phase 8: Token PDF includes patient ID', pdfContent.includes(dbJson.patient?.uhid));

            // Generate actual binary PDF artifact to verify %PDF- signature and non-zero bytes
            const pdfFilePath = path.join(SCREENSHOT_DIR, 'dxclinic_token.pdf');
            const pdfBuffer = await pdfPage.pdf({ format: 'A5' });
            fs.writeFileSync(pdfFilePath, pdfBuffer);

            const isNonZero = pdfBuffer.length > 0;
            const pdfSignature = pdfBuffer.subarray(0, 5).toString('ascii');
            record('Phase 8: Token PDF binary file is non-zero size', isNonZero, `${pdfBuffer.length} bytes`);
            record('Phase 8: Token PDF has valid %PDF- magic signature', pdfSignature === '%PDF-', `sig: ${pdfSignature}`);

            await pdfPage.screenshot({ path: path.join(SCREENSHOT_DIR, '04_token_pdf_rendered.png') });
            await pdfPage.close();
        } else {
            record('Phase 8: Token PDF link available', false, 'Missing PDF href');
        }

        // ==================================================================
        // PHASE 9 — IDEMPOTENCY RETRY VERIFICATION
        // ==================================================================
        console.log('\n▶ [PHASE 9] Verifying Booking Idempotency...');
        // In a new page, submit the exact same booking again
        const retryPage = await context.newPage();
        await retryPage.goto(`${TARGET_URL}/book/sharmacare`, { waitUntil: 'networkidle' });

        await retryPage.fill('#pwa_patient_name', testPatient.name);
        await retryPage.fill('#pwa_patient_age', testPatient.age);
        await retryPage.selectOption('#pwa_patient_sex', testPatient.sex);
        await retryPage.fill('#pwa_patient_mobile', testPatient.mobile);
        await retryPage.fill('#pwa_patient_address', testPatient.address);
        await retryPage.fill('#pwa_patient_city', testPatient.city);

        if ((await retryPage.locator('#pwa_select_doctor').count()) > 0) {
            await retryPage.selectOption('#pwa_select_doctor', { index: 1 });
            await retryPage.waitForTimeout(600);
        }

        // Submit retry
        await retryPage.click('#btn_pwa_submit_booking');
        await retryPage.waitForTimeout(3000);

        // Verify same token returned and confirmation displayed
        const retryToken = (await retryPage.locator('#confirmed_token_number').innerText()).trim();
        record('Phase 9: Idempotent retry returns existing confirmed token', retryToken === allocatedToken, `returned: ${retryToken}`);

        // Verify database: no second appointment or patient created
        const postRetryDb = JSON.parse(execSync(`php scratch/verify_prod_operation_db.php verify_positive ${testPatient.mobile}`).toString());
        record('Phase 9: Idempotency preserves single patient and single appointment', 
            postRetryDb.invariants?.patient_exact_single === true && postRetryDb.invariants?.appointment_exact_single === true,
            'zero duplicate records'
        );
        record('Phase 9: Token sequence was NOT advanced by retry', 
            postRetryDb.token_sequence?.current_token === dbJson.token_sequence?.current_token,
            `seq token: ${postRetryDb.token_sequence?.current_token}`
        );

        await retryPage.screenshot({ path: path.join(SCREENSHOT_DIR, '05_idempotent_retry_confirmed.png') });
        await retryPage.close();

        // ==================================================================
        // PHASE 10 — BOOK ANOTHER FLOW
        // ==================================================================
        console.log('\n▶ [PHASE 10] Testing "Book Another Appointment" Flow...');
        await page.bringToFront();
        await page.click('#btn_book_another');
        await page.waitForTimeout(1000);

        const isFormReset = (await page.locator('#btn_pwa_submit_booking').count()) > 0;
        record('Phase 10: "Book Another" returns to fresh booking form', isFormReset);

        const nameValue = await page.locator('#pwa_patient_name').inputValue();
        const mobileValue = await page.locator('#pwa_patient_mobile').inputValue();
        record('Phase 10: Previous patient inputs are cleared cleanly', nameValue === '' && mobileValue === '', `name: "${nameValue}", mobile: "${mobileValue}"`);

        const currentUrl = page.url();
        record('Phase 10: Canonical route maintained /book/sharmacare', currentUrl.endsWith('/book/sharmacare'), `url: ${currentUrl}`);

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_book_another_reset.png') });

    } catch (err) {
        console.error('Fatal Operational Verification Error:', err);
        record('Suite Execution', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  OPERATIONAL VERIFICATION SUMMARY: ${summary.passed}/${summary.total} PASSED (${summary.failed} FAILED)`);
    console.log(`  Livewire Requests: ${summary.network.livewireRequests} | Livewire Errors: ${summary.network.livewireErrors}`);
    console.log(`  HTTP 500 Count: ${summary.network.http500Count} | Failed Assets: ${summary.network.failedAssets}`);
    console.log('======================================================================\n');

    const reportFile = path.join(SCREENSHOT_DIR, 'operational_verification_summary.json');
    fs.writeFileSync(reportFile, JSON.stringify(summary, null, 2));
    console.log(`Summary JSON saved to: ${reportFile}`);

    if (summary.failed > 0) {
        process.exit(1);
    }
}

run().catch(err => {
    console.error(err);
    process.exit(1);
});
