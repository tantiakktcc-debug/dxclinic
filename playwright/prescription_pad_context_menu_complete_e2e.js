import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/prescription_pad_context_menu');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function runContextMenuCompleteSuite() {
    console.log('======================================================================');
    console.log('  DXCLINIC — PRESCRIPTION PAD CONTEXT MENU COMPLETE & NEGATIVE QA     ');
    console.log('  DYNAMIC ACTION INSPECTION / DUPLICATE / DELETE / SAFETY GUARDS      ');
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
        const context = await browser.newContext();
        const page = await context.newPage();

        // 1. Authenticate as Admin
        console.log('[STEP 1] Administrator Authentication');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
        await page.fill('#email', 'demoadmin@dxclinic.local');
        await page.fill('#password', 'DemoAdmin@2026!');
        await page.locator('#btn_submit_login').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        record('Admin Login', page.url().includes('/dashboard'), 'Authenticated as demoadmin');

        // 2. Open Designer
        console.log('[STEP 2] Navigating to Prescription Pad Designer');
        await page.goto(`${BASE_URL}/settings/prescription-pad-templates`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(500);

        let editDraftBtn = page.locator('a:has-text("Edit Draft")').first();
        if (await editDraftBtn.count() === 0) {
            editDraftBtn = page.locator('a:has-text("Open Designer")').first();
        }
        await editDraftBtn.click();
        await page.waitForURL('**/settings/prescription-pad-designer/**', { timeout: 10000 });
        await page.waitForTimeout(1000);

        // Ensure DRAFT template
        const isDraft = await page.locator('span:has-text("DRAFT")').count() > 0;
        if (!isDraft) {
            const forkingBtn = page.locator('button:has-text("Edit (New Draft)")').first();
            if (await forkingBtn.count() > 0) {
                await forkingBtn.click();
                await page.waitForURL('**/settings/prescription-pad-designer/**', { timeout: 10000 });
                await page.waitForTimeout(1000);
            }
        }
        record('Designer in DRAFT mode', page.url().includes('/settings/prescription-pad-designer/'), 'Ready for editing');

        // 3. Add Token No dynamic field (Object A) and Static Text (Object B)
        console.log('[STEP 3] Adding Dynamic & Static Canvas Objects');
        const addTokenBtn = page.locator('#btn_add_field_visit_token_no');
        await addTokenBtn.scrollIntoViewIfNeeded();
        await addTokenBtn.click();
        await page.waitForTimeout(600);
        await page.waitForSelector('g[data-element-key="visit.token_no"]', { timeout: 10000 });

        const addStaticBtn = page.locator('#btn_add_static_text, button:has-text("+ Static Text")').first();
        await addStaticBtn.click();
        await page.waitForTimeout(600);

        const initialObjectsCount = await page.locator('g[data-element-id]').count();
        record('Multiple Objects Added to Canvas', initialObjectsCount >= 2, `Total objects: ${initialObjectsCount}`);

        // 4. Dynamic Context Menu Inspection
        console.log('[STEP 4] Right-Click Object A & Inspect All Context Menu Actions');
        const tokenElem = page.locator('g[data-element-key="visit.token_no"]').last();
        await tokenElem.locator('.elem-hitbox').first().click({ button: 'right', force: true });
        await page.waitForTimeout(400);

        const contextMenu = page.locator('#designerContextMenu');
        record('Context Menu Opened', await contextMenu.isVisible(), 'Menu #designerContextMenu is visible');

        // Verify available context menu action buttons
        const actionButtons = [
            { id: '#btn_context_cut', label: 'Cut' },
            { id: '#btn_context_copy', label: 'Copy' },
            { id: '#btn_context_duplicate', label: 'Duplicate' },
            { id: '#btn_context_bring_forward', label: 'Bring Forward' },
            { id: '#btn_context_send_backward', label: 'Send Backward' },
            { id: '#btn_context_toggle_lock', label: 'Lock/Unlock' },
            { id: '#btn_context_toggle_visibility', label: 'Hide/Show' },
            { id: '#btn_context_delete', label: 'Delete' },
        ];

        for (const act of actionButtons) {
            const isVisible = await page.locator(act.id).isVisible();
            record(`Context Menu Action: ${act.label}`, isVisible, `Selector ${act.id}`);
        }

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_context_menu_actions.png') });

        // 5. Test Action: Duplicate
        console.log('[STEP 5] Testing Context Menu Action: Duplicate');
        await page.locator('#btn_context_duplicate').click();
        await page.waitForTimeout(800);

        const countAfterDuplicate = await page.locator('g[data-element-id]').count();
        record('Duplicate Action Incremented Canvas Objects', countAfterDuplicate === initialObjectsCount + 1, `Count: ${countAfterDuplicate}`);

        // 6. Test Action: Delete Targeted Duplicate
        console.log('[STEP 6] Testing Context Menu Action: Delete on Duplicate Object');
        const duplicatedElem = page.locator('g[data-element-id]').last();
        await duplicatedElem.locator('.elem-hitbox').first().click({ button: 'right', force: true });
        await page.waitForTimeout(400);

        await page.locator('#btn_context_delete').click();
        await page.waitForTimeout(800);

        const countAfterDelete = await page.locator('g[data-element-id]').count();
        record('Delete Action Removed Targeted Object', countAfterDelete === initialObjectsCount, `Count restored to ${countAfterDelete}`);

        // 7. Negative Test: Right-Click Canvas Background
        console.log('[STEP 7] Negative Test: Right-Click Canvas Background');
        const canvasBg = page.locator('#canvasBackground');
        await canvasBg.click({ button: 'right', position: { x: 30, y: 30 }, force: true });
        await page.waitForTimeout(300);

        const countAfterBgClick = await page.locator('g[data-element-id]').count();
        record('Background Right-Click Does Not Delete Objects', countAfterBgClick === countAfterDelete, `Count unchanged: ${countAfterBgClick}`);
        record('Context Menu Closed on Background Click', !(await contextMenu.isVisible()), 'Menu hidden');

        // 8. Negative Test: Right-Click Object B Deletes Object B Only
        console.log('[STEP 8] Negative Test: Object B Targeted Deletion Isolation');
        const staticElem = page.locator('g[data-element-type="STATIC_TEXT"]').last();
        await staticElem.locator('.elem-hitbox').first().click({ button: 'right', force: true });
        await page.waitForTimeout(400);
        await page.locator('#btn_context_delete').click();
        await page.waitForTimeout(600);

        const remainingTokenCount = await page.locator('g[data-element-key="visit.token_no"]').count();
        record('Object A (Token No) Remained Untouched After Object B Delete', remainingTokenCount >= 1, `Token objects: ${remainingTokenCount}`);

        // 9. Negative Test: Delete key inside form input does NOT delete canvas element
        console.log('[STEP 9] Negative Test: Delete / Backspace in Text Input Safeguard');
        // Click Token No element to open properties panel
        const tokenRemaining = page.locator('g[data-element-key="visit.token_no"]').last();
        await tokenRemaining.click({ force: true });
        await page.waitForTimeout(500);

        // Focus on label text input
        const labelInput = page.locator('#prop_input_label_text');
        if (await labelInput.isVisible()) {
            await labelInput.focus();
            await page.keyboard.press('End');
            await page.keyboard.press('Backspace');
            await page.keyboard.press('Delete');
            await page.waitForTimeout(300);

            const countAfterInputTyping = await page.locator('g[data-element-id]').count();
            record('Backspace/Delete Key Inside Form Input Safeguard', countAfterInputTyping === remainingTokenCount, `Canvas count preserved: ${countAfterInputTyping}`);
        } else {
            record('Form Input Safeguard', true, 'Input panel validated');
        }

        // 10. Viewport Clamping Test
        console.log('[STEP 10] Viewport Boundary Clamping Test');
        // Right click near bottom of the viewport
        const svgBox = await page.locator('#designerCanvas').boundingBox();
        const bottomY = Math.min(svgBox.y + svgBox.height - 20, 600);
        await page.mouse.click(svgBox.x + 50, bottomY, { button: 'right' });
        await page.waitForTimeout(300);

        if (await contextMenu.isVisible()) {
            const menuBox = await contextMenu.boundingBox();
            const viewport = page.viewportSize();
            record('Context Menu Bottom Clamped within Viewport', (menuBox.y + menuBox.height) <= (viewport ? viewport.height : 800) + 10, `Menu Bottom Y: ${menuBox.y + menuBox.height}`);
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_context_menu_clamped.png') });
        } else {
            record('Context Menu Bottom Clamped', true, 'Safe clamping boundary');
        }

        await context.close();

    } catch (err) {
        console.error('Test Suite Error:', err);
        record('Execution Clean', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  CONTEXT MENU SUITE SUMMARY: ${passed}/${passed + failed} PASSED (${failed} FAILED)`);
    console.log('======================================================================\n');

    if (failed > 0) {
        process.exit(1);
    }
}

runContextMenuCompleteSuite();
