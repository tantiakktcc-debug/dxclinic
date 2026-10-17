import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/revision38_failure_cleanup_test');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function runFailureIsolationTest() {
    console.log('======================================================================');
    console.log('  DXCLINIC REVISION 38.3 — FAILURE RESILIENCE CLEANUP VERIFICATION    ');
    console.log('  VERIFY AUTOMATIC CLEANUP EXECUTES EVEN UPON TEST ASSERTION FAILURE  ');
    console.log('======================================================================\n');

    const runId = `failtest_${Date.now()}_${Math.random().toString(36).substring(2, 6)}`;
    const runMarker = `rev38_e2e_test_${runId}`;
    const baselineSnapshotFile = path.resolve(SCREENSHOT_DIR, `baseline_${runId}.json`);

    // 1. Capture pre-test baseline
    const snapshotRaw = execSync(`php playwright/prescription_test_helper.php snapshot 44`, { encoding: 'utf-8' });
    const baselineSnapshot = JSON.parse(snapshotRaw.trim());
    fs.writeFileSync(baselineSnapshotFile, JSON.stringify(baselineSnapshot, null, 2));
    console.log(`[INIT] Baseline element count: ${baselineSnapshot.count}`);

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const page = await browser.newPage();
    let deliberateFailureTriggered = false;
    let cleanupExecuted = false;
    let integrityVerified = false;

    try {
        console.log('[STEP 1] Login as Admin');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await page.fill('#email', 'admin@dxclinic.local');
        await page.fill('#password', 'DxClinic@Admin2026');
        await page.locator('#btn_submit_login').click();
        await page.waitForURL('**/dashboard');

        console.log('[STEP 2] Open Template 44 Designer');
        await page.goto(`${BASE_URL}/settings/prescription-pad-designer/44`, { waitUntil: 'networkidle' });
        await page.waitForSelector('#designerRoot');

        const countBase = await page.locator('g[data-element-id]').count();

        console.log('[STEP 3] Create Marked Temporary Element');
        await page.locator('#tab_toolbox').click();
        await page.waitForTimeout(200);
        await page.locator('#btn_add_static_text').click();
        await page.waitForFunction((c) => document.querySelectorAll('g[data-element-id]').length >= c + 1, countBase, { timeout: 8000 });

        const elem = page.locator('g[data-element-id]').last();
        const origKey = await elem.getAttribute('data-element-key');
        const taggedKey = `${runMarker}_${origKey}`;

        await page.evaluate(({ idx, marker, taggedKey }) => {
            const root = document.getElementById('designerRoot');
            const alpine = window.Alpine ? window.Alpine.$data(root) : null;
            if (alpine && alpine.$wire) {
                alpine.$wire.updateElementProperty(idx, 'field_key', taggedKey);
                alpine.$wire.updateElementConfig(idx, 'test_marker', marker);
            }
        }, { idx: countBase, marker: runMarker, taggedKey });
        await page.waitForTimeout(300);

        console.log('[STEP 4] Save Draft with Temporary Element');
        await page.locator('#btn_save_draft').click();
        await page.waitForSelector('#flash_success_banner', { timeout: 8000 });

        // Confirm it was written to database
        const dbCountDuringRaw = execSync(`php playwright/prescription_test_helper.php status 44`, { encoding: 'utf-8' });
        const dbCountDuring = JSON.parse(dbCountDuringRaw.trim()).elements_count;
        console.log(`[VERIFY] Elements in DB during test: ${dbCountDuring} (Expected: ${baselineSnapshot.count + 1})`);

        console.log('\n[STEP 5] DELIBERATELY THROWING ASSERTION FAILURE...');
        deliberateFailureTriggered = true;
        throw new Error('INTENTIONAL_ASSERTION_FAILURE: Simulating an unexpected test crash during execution!');

    } catch (err) {
        console.log(`[CAUGHT ERROR AS EXPECTED]: ${err.message}`);
    } finally {
        console.log('\n[STEP 6] FINALLY BLOCK EXECUTING (TEARDOWN)...');
        await browser.close();

        // Run isolated cleanup for this runMarker
        const cleanupRaw = execSync(`php playwright/prescription_test_helper.php cleanup 44 "${runMarker}"`, { encoding: 'utf-8' });
        const cleanupRes = JSON.parse(cleanupRaw.trim());
        console.log(`  [CLEANUP] Pruned ${cleanupRes.removed_count} test elements matching marker "${runMarker}".`);
        cleanupExecuted = (cleanupRes.removed_count === 1);

        // Verify Template 44 database state against pre-test baseline
        const verifyRaw = execSync(`php playwright/prescription_test_helper.php verify 44 "${baselineSnapshotFile}"`, { encoding: 'utf-8' });
        const verifyRes = JSON.parse(verifyRaw.trim());
        console.log(`  [INTEGRITY] Baseline: ${verifyRes.count_before} | Post-Cleanup: ${verifyRes.count_after} | Difference: ${verifyRes.difference}`);
        integrityVerified = (verifyRes.intact && verifyRes.difference === 0);

        if (fs.existsSync(baselineSnapshotFile)) {
            fs.unlinkSync(baselineSnapshotFile);
        }
    }

    console.log('\n======================================================================');
    console.log('  FAILURE RESILIENCE TEST SUMMARY');
    console.log(`  - Deliberate Failure Triggered: ${deliberateFailureTriggered ? 'YES' : 'NO'}`);
    console.log(`  - Teardown Executed in Finally: ${cleanupExecuted ? 'YES' : 'NO'}`);
    console.log(`  - Database Integrity Verified:  ${integrityVerified ? 'PASS (Zero Pollution)' : 'FAIL'}`);
    console.log('======================================================================\n');

    if (!deliberateFailureTriggered || !cleanupExecuted || !integrityVerified) {
        process.exit(1);
    }
}

runFailureIsolationTest().catch((e) => {
    console.error('Fatal test error:', e);
    process.exit(1);
});
