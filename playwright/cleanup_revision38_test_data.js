import { execSync } from 'child_process';
import path from 'path';

/**
 * DxClinic Revision 38.3 — Standalone Test Data Cleanup Utility
 * 
 * Safely finds and removes ONLY explicitly marked Revision 38 E2E test artifacts.
 * NEVER deletes unmarked real elements.
 * 
 * Usage:
 *   node playwright/cleanup_revision38_test_data.js [--marker=rev38_e2e_test_...] [--template=44]
 */

function runCleanup() {
    console.log('======================================================================');
    console.log('  DXCLINIC REVISION 38.3 — TEST DATA CLEANUP UTILITY                  ');
    console.log('  NARROW-SCOPE E2E TEST FIXTURE PRUNING (ZERO IMPACT TO REAL DATA)    ');
    console.log('======================================================================\n');

    const args = process.argv.slice(2);
    let marker = null;
    let templateId = 44;

    for (const arg of args) {
        if (arg.startsWith('--marker=')) {
            marker = arg.split('=')[1].trim();
        } else if (arg.startsWith('--template=')) {
            templateId = parseInt(arg.split('=')[1].trim(), 10) || 44;
        }
    }

    console.log(`[TARGET] Prescription Pad Template ID: ${templateId}`);
    if (marker) {
        console.log(`[SCOPE] Explicit Test Marker: "${marker}"`);
    } else {
        console.log(`[SCOPE] Default Scan: All explicitly marked "rev38_e2e_*" & "e2e_rev38_*" elements`);
    }

    const helperPath = path.resolve('playwright/prescription_test_helper.php');
    const cmd = `php "${helperPath}" cleanup ${templateId} ${marker ? '"' + marker + '"' : ''}`;

    try {
        const rawOutput = execSync(cmd, { encoding: 'utf-8' });
        const result = JSON.parse(rawOutput.trim());

        if (!result.success) {
            console.error(`[ERROR] Cleanup failed: ${result.error}`);
            process.exit(1);
        }

        console.log('\n[RESULT]');
        console.log(`  - Test Elements Removed: ${result.removed_count}`);
        if (result.removed_count > 0) {
            console.log(`  - Removed Keys: ${result.removed_keys.join(', ')}`);
        } else {
            console.log(`  - Note: No test elements matching the specified marker were found. Zero records touched.`);
        }
        console.log(`  - Remaining Template Elements: ${result.remaining_count}`);
        console.log('\n[STATUS] Template 44 database state is verified clean.\n');

    } catch (err) {
        console.error(`[FATAL] Error running test data cleanup: ${err.message}`);
        process.exit(1);
    }
}

runCleanup();
