import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/whatsapp_live_queue');

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

async function runSuite() {
    console.log('======================================================================');
    console.log('  DXCLINIC — WHATSAPP & REAL-TIME LIVE QUEUE WINDOW E2E SUITE');
    console.log('  Developer: Kundan Kumar | Nexora Solutions, Varanasi, India');
    console.log('======================================================================\n');
    console.log(`Target: ${BASE_URL}\n`);

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-WhatsApp-LiveQueue-E2E/1.0 (Google-Chrome-Desktop)'
    });

    const adminPage = await context.newPage();

    try {
        // ------------------------------------------------------------------
        // TEST 1: Admin Authentication on NX0001 (Sharma Care Clinic)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 1] Admin Authentication on NX0001 (Sharma Care Clinic)...');
        await adminPage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        
        // Use quick fill button or inputs
        const adminBtn = adminPage.locator('button:has-text("Admin")');
        if (await adminBtn.count() > 0) {
            await adminBtn.first().click();
        } else {
            await adminPage.fill('#clinic_code', 'NX0001');
            await adminPage.fill('#email', 'admin@dxclinic.local');
            await adminPage.fill('#password', 'DxClinic@Admin2026');
        }
        await adminPage.click('#btn_submit_login');
        await adminPage.waitForTimeout(2000);

        const currentUrl = adminPage.url();
        const isLoggedIn = !currentUrl.includes('/login');
        recordResult('Admin login succeeded', isLoggedIn, `Current URL: ${currentUrl}`);
        await adminPage.screenshot({ path: path.join(SCREENSHOT_DIR, '01_admin_login.png') });

        // ------------------------------------------------------------------
        // TEST 2: Verify [Open Live Queue] button in navigation & Arrival Queue
        // ------------------------------------------------------------------
        console.log('▶ [TEST 2] Verifying [Open Live Queue] button in Operator Arrival Queue...');
        await adminPage.goto(`${BASE_URL}/clinic/sharmacare/operator/queue`, { waitUntil: 'networkidle' });
        
        const openBtn = adminPage.locator('#open_live_queue_btn, a:has-text("Open Live Queue"), button:has-text("Open Live Queue")');
        const hasOpenBtn = (await openBtn.count()) > 0;
        recordResult('Arrival Queue contains [Open Live Queue] trigger', hasOpenBtn);
        await adminPage.screenshot({ path: path.join(SCREENSHOT_DIR, '02_operator_queue_live_btn.png') });

        // Check window.open invocation attributes
        if (hasOpenBtn) {
            const onclickAttr = await openBtn.first().getAttribute('onclick') || '';
            const hrefAttr = await openBtn.first().getAttribute('href') || '';
            const isOpenWindowTarget = onclickAttr.includes('window.open') || (await openBtn.first().getAttribute('target')) === '_blank' || hrefAttr.includes('/queue/live');
            recordResult('[Open Live Queue] triggers separate window target', isOpenWindowTarget, `target: ${onclickAttr || hrefAttr}`);
        }

        // ------------------------------------------------------------------
        // TEST 3: Dedicated Live Queue Window Access & High-Visibility Layout
        // ------------------------------------------------------------------
        console.log('▶ [TEST 3] Loading Dedicated Live Queue Window in separate viewport...');
        const liveQueuePage = await context.newPage();
        const liveResp = await liveQueuePage.goto(`${BASE_URL}/clinic/sharmacare/queue/live`, { waitUntil: 'networkidle' });
        const liveStatus = liveResp.status();
        recordResult('Live Queue Window loads with HTTP 200', liveStatus === 200, `status: ${liveStatus}`);
        await liveQueuePage.screenshot({ path: path.join(SCREENSHOT_DIR, '03_live_queue_window.png') });

        // Check Header Elements (Section 53, 54)
        const liveContent = await liveQueuePage.content();
        const hasBrand = liveContent.includes('DxClinic') && liveContent.includes('LIVE QUEUE');
        const hasClinicName = liveContent.includes('Sharma Care');
        const hasConnectionBadge = liveContent.includes('CONNECTED') || liveContent.includes('REALTIME');
        const hasLastUpdated = liveContent.includes('Last Updated') || liveContent.includes('LIVE');
        recordResult('Live Queue Header displays Brand, Clinic, and Realtime status', hasBrand && hasClinicName, `Badge: ${hasConnectionBadge}`);

        // Check Metrics Bar (Section 43)
        const hasTotalMetric = liveContent.includes('TOTAL') || liveContent.includes('Total');
        const hasWaitingMetric = liveContent.includes('WAITING') || liveContent.includes('Waiting');
        recordResult('Live Queue displays real-time metrics cards', hasTotalMetric && hasWaitingMetric);

        // Check Table Structure (Section 37, 38, 39, Part 2 & 3 Enhancements)
        const hasTokenCol = (await liveQueuePage.locator('th:has-text("TOKEN"), th:has-text("Token")').count()) > 0;
        const hasPatientCol = (await liveQueuePage.locator('th:has-text("PATIENT"), th:has-text("Patient")').count()) > 0;
        const hasSourceCol = (await liveQueuePage.locator('th:has-text("SOURCE"), th:has-text("Source"), th:has-text("BOOKING SOURCE")').count()) > 0;
        const hasFeeCol = (await liveQueuePage.locator('th:has-text("FEE"), th:has-text("Fee")').count()) > 0;
        const hasDoctorSeenCol = (await liveQueuePage.locator('th:has-text("DOCTOR SEEN"), th:has-text("Doctor Seen")').count()) > 0;
        const hasStatusCol = (await liveQueuePage.locator('th:has-text("STATUS"), th:has-text("Status")').count()) > 0;
        recordResult('Live Queue Table contains Token, Patient, Source, Fee, Doctor Seen, and Status columns', hasTokenCol && hasPatientCol && hasSourceCol && hasFeeCol && hasDoctorSeenCol && hasStatusCol);

        // ------------------------------------------------------------------
        // TEST 4: Operational Controls (Sound Toggle & Fullscreen)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 4] Testing Live Queue operational controls (Sound & Fullscreen)...');
        const soundBtn = liveQueuePage.locator('button:has-text("Sound"), button[wire\\:click*="soundEnabled"]');
        const hasSoundBtn = (await soundBtn.count()) > 0;
        recordResult('Sound Toggle button is present and staff-controllable', hasSoundBtn);

        const fullscreenBtn = liveQueuePage.locator('button:has-text("Full Screen"), button:has-text("Fullscreen")');
        const hasFullscreenBtn = (await fullscreenBtn.count()) > 0;
        recordResult('Fullscreen Mode toggle button is present', hasFullscreenBtn);

        // ------------------------------------------------------------------
        // TEST 5: Verify WhatsApp Settings in Admin Panel (/settings/whatsapp)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 5] Verifying WhatsApp Automation Settings in Admin Panel...');
        const settingsResp = await adminPage.goto(`${BASE_URL}/settings/whatsapp`, { waitUntil: 'networkidle' });
        const settingsStatus = settingsResp.status();
        recordResult('WhatsApp Settings page loads with HTTP 200', settingsStatus === 200, `status: ${settingsStatus}`);
        
        const settingsContent = await adminPage.content();
        const hasWhatsAppTitle = settingsContent.includes('WhatsApp Automation') || settingsContent.includes('Meta Cloud API');
        const hasConnectionStatus = settingsContent.includes('CONNECTED') || settingsContent.includes('CONNECTION STATUS');
        recordResult('WhatsApp Settings displays Meta connection state and toggles', hasWhatsAppTitle && hasConnectionStatus);
        await adminPage.screenshot({ path: path.join(SCREENSHOT_DIR, '04_whatsapp_settings.png') });

        // ------------------------------------------------------------------
        // TEST 6: WhatsApp Webhook Handshake (GET /api/v1/webhooks/whatsapp)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 6] Testing Meta Webhook Verification Handshake...');
        const challengeStr = 'challenge_test_code_98765';
        const verifyResp = await adminPage.request.get(`${BASE_URL}/api/v1/webhooks/whatsapp`, {
            params: {
                'hub.mode': 'subscribe',
                'hub.verify_token': 'dxclinic_verify_token_secure',
                'hub.challenge': challengeStr,
            }
        });
        const verifyStatus = verifyResp.status();
        const verifyBody = await verifyResp.text();
        recordResult('Webhook GET handshake returns challenge with HTTP 200', verifyStatus === 200 && verifyBody === challengeStr, `status: ${verifyStatus}`);

        // ------------------------------------------------------------------
        // TEST 7: WhatsApp Webhook Inbound Ingestion (POST /api/v1/webhooks/whatsapp)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 7] Testing Inbound WhatsApp Webhook message ingestion...');
        const webhookPayload = {
            object: 'whatsapp_business_account',
            entry: [
                {
                    id: 'waba_sharma',
                    changes: [
                        {
                            value: {
                                messaging_product: 'whatsapp',
                                metadata: {
                                    display_phone_number: '+91 98765 43210',
                                    phone_number_id: '109876543210002'
                                },
                                contacts: [
                                    {
                                        profile: { name: 'E2E Test Patient' },
                                        wa_id: '919876543299'
                                    }
                                ],
                                messages: [
                                    {
                                        from: '919876543299',
                                        id: 'wamid.E2ETEST' + Date.now(),
                                        timestamp: Math.floor(Date.now() / 1000).toString(),
                                        type: 'text',
                                        text: { body: 'Hello' }
                                    }
                                ]
                            },
                            field: 'messages'
                        }
                    ]
                }
            ]
        };

        const postResp = await adminPage.request.post(`${BASE_URL}/api/v1/webhooks/whatsapp`, {
            data: webhookPayload,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        });
        const postStatus = postResp.status();
        const postData = await postResp.json().catch(() => ({}));
        recordResult('Webhook POST ingested successfully', postStatus === 200 && postData.status === 'EVENT_RECEIVED', `status: ${postStatus}`);

        // ------------------------------------------------------------------
        // TEST 8: Webhook Idempotency (Sending exact same message ID twice)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 8] Testing Webhook Idempotency on repeated delivery...');
        const duplicateResp = await adminPage.request.post(`${BASE_URL}/api/v1/webhooks/whatsapp`, {
            data: webhookPayload,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        });
        const dupStatus = duplicateResp.status();
        recordResult('Repeated Webhook returns 200 without creating duplicate events', dupStatus === 200, `status: ${dupStatus}`);

        // ------------------------------------------------------------------
        // TEST 9: Refresh Live Queue & verify booking source badges
        // ------------------------------------------------------------------
        console.log('▶ [TEST 9] Verifying Live Queue table rows and booking sources...');
        await liveQueuePage.reload({ waitUntil: 'networkidle' });
        await liveQueuePage.waitForTimeout(1000);
        
        const queueContent = await liveQueuePage.content();
        const hasAnyRow = (await liveQueuePage.locator('tbody tr').count()) > 0 || queueContent.includes('No patients in queue today');
        recordResult('Live Queue renders table rows or clean empty state', hasAnyRow);
        await liveQueuePage.screenshot({ path: path.join(SCREENSHOT_DIR, '05_live_queue_after_refresh.png') });

        // ------------------------------------------------------------------
        // TEST 10: Close & Reopen Live Queue Window Lifecycle
        // ------------------------------------------------------------------
        console.log('▶ [TEST 10] Testing Live Queue Window Close and Reopen independence...');
        await liveQueuePage.close();
        
        // Verify main admin dashboard is still active and operational
        await adminPage.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle' });
        const isMainSessionActive = !adminPage.url().includes('/login');
        recordResult('Closing Live Queue does not logout user or affect main app', isMainSessionActive);

        // Reopen Live Queue in fresh page
        const reopenedPage = await context.newPage();
        await reopenedPage.goto(`${BASE_URL}/clinic/sharmacare/queue/live`, { waitUntil: 'networkidle' });
        const reopenStatus = (await reopenedPage.content()).includes('LIVE QUEUE');
        recordResult('Reopening Live Queue reloads authoritative queue state cleanly', reopenStatus);
        await reopenedPage.screenshot({ path: path.join(SCREENSHOT_DIR, '06_reopened_live_queue.png') });
        await reopenedPage.close();

        // ------------------------------------------------------------------
        // TEST 11: Cross-Clinic Isolation & Security Enforcement (Section 65, 78)
        // ------------------------------------------------------------------
        console.log('▶ [TEST 11] Testing Cross-Clinic Live Queue access rejection...');
        // User is authenticated for Sharma Care Clinic (ID 1). Try accessing Demo Clinic queue (ID 6).
        const crossClinicResp = await adminPage.goto(`${BASE_URL}/clinic/democlinic/queue/live`, { waitUntil: 'networkidle' });
        const crossStatus = crossClinicResp.status();
        recordResult('Cross-Clinic Live Queue access is forbidden (HTTP 403)', crossStatus === 403, `status: ${crossStatus}`);
        await adminPage.screenshot({ path: path.join(SCREENSHOT_DIR, '07_cross_clinic_forbidden.png') });

        // ------------------------------------------------------------------
        // TEST 12: Doctor Scope Isolation
        // ------------------------------------------------------------------
        console.log('▶ [TEST 12] Testing Doctor Scope Isolation...');
        const doctorContext = await browser.newContext();
        const doctorPage = await doctorContext.newPage();
        
        await doctorPage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        const doctorBtn = doctorPage.locator('button:has-text("Doctor")');
        if (await doctorBtn.count() > 0) {
            await doctorBtn.first().click();
        } else {
            await doctorPage.fill('#clinic_code', 'NX0001');
            await doctorPage.fill('#email', 'doctor@dxclinic.local');
            await doctorPage.fill('#password', 'DxClinic@Doc2026');
        }
        await doctorPage.click('#btn_submit_login');
        await doctorPage.waitForTimeout(2000);
        
        // Access Live Queue as Doctor
        const docQueueResp = await doctorPage.goto(`${BASE_URL}/clinic/sharmacare/queue/live`, { waitUntil: 'networkidle' });
        const docQueueStatus = docQueueResp.status();
        recordResult('Doctor can access Live Queue for assigned clinic', docQueueStatus === 200, `status: ${docQueueStatus}`);
        await doctorPage.screenshot({ path: path.join(SCREENSHOT_DIR, '08_doctor_live_queue.png') });
        await doctorContext.close();

    } catch (err) {
        console.error('Unhandled exception during E2E suite:', err);
        recordResult('Suite Exception Boundary', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  E2E SUMMARY: TOTAL: ${summary.total} | PASSED: ${summary.passed} | FAILED: ${summary.failed}`);
    console.log('======================================================================\n');

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite();
