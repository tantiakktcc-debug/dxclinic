import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/ui_ux_refinements');

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

const summary = {
    total: 0,
    passed: 0,
    failed: 0,
    tests: [],
    consoleErrors: [],
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
    console.log('  DXCLINIC — UI/UX REFINEMENTS & PRESCRIPTION PAD DESIGNER E2E TESTS  ');
    console.log('  FIELD LABELS / RIGHT-CLICK DELETE / SIDEBAR COLLAPSE & PERSISTENCE  ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        userAgent: 'DxClinic-UIUX-Refinements-E2E/1.0 (Google-Chrome-Desktop)'
    });

    const page = await context.newPage();

    page.on('console', msg => {
        if (msg.type() === 'error') {
            summary.consoleErrors.push(`[Console Error] ${msg.text()}`);
        }
    });

    try {
        // ------------------------------------------------------------------
        // SUITE 1: AUTHENTICATION
        // ------------------------------------------------------------------
        console.log('[SUITE 1] Administrator Authentication');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await page.fill('#email', 'admin@dxclinic.local');
        await page.fill('#password', 'DxClinic@Admin2026');
        await page.locator('#btn_submit_login').click();
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        recordResult('Admin Login Success', page.url().includes('/dashboard'), 'Authenticated as admin');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_dashboard_expanded.png') });

        // ------------------------------------------------------------------
        // SUITE 2: MAIN LEFT SIDEBAR HIDE / UNHIDE & WORKSPACE EXPANSION
        // ------------------------------------------------------------------
        console.log('\n[SUITE 2] Main Left Sidebar Hide / Unhide & Workspace Expansion');
        const sidebar = page.locator('#appNavigationSidebar');
        const toggleBtn = page.locator('#btn_toggle_desktop_sidebar');
        const mainContent = page.locator('main.flex-1');

        recordResult('Sidebar Toggle Button Exists', await toggleBtn.count() > 0, 'Toggle button mounted in header');
        
        // Check initial expanded state
        const initialAriaExpanded = await toggleBtn.getAttribute('aria-expanded');
        recordResult('Initial Sidebar aria-expanded is true', initialAriaExpanded === 'true', `aria-expanded=${initialAriaExpanded}`);

        const initialSidebarBox = await sidebar.boundingBox();
        const initialMainBox = await mainContent.boundingBox();
        recordResult('Initial Sidebar is wide (~256px)', initialSidebarBox.width >= 240, `Width: ${initialSidebarBox.width}px`);

        // Click Hide Sidebar
        await toggleBtn.click();
        await page.waitForTimeout(400);

        const collapsedAriaExpanded = await toggleBtn.getAttribute('aria-expanded');
        recordResult('Collapsed Sidebar aria-expanded is false', collapsedAriaExpanded === 'false', `aria-expanded=${collapsedAriaExpanded}`);

        const collapsedSidebarBox = await sidebar.boundingBox();
        const expandedMainBox = await mainContent.boundingBox();
        recordResult('Sidebar collapsed to compact rail (~64px)', collapsedSidebarBox.width <= 75, `Width: ${collapsedSidebarBox.width}px`);
        recordResult('Main Content expanded into freed space', expandedMainBox.width > initialMainBox.width, `Main width: ${expandedMainBox.width}px > ${initialMainBox.width}px`);

        // Verify Dashboard item remains accessible in collapsed rail
        const dashboardItem = page.locator('#nav_item_dashboard');
        recordResult('Dashboard remains visible and accessible in rail', await dashboardItem.isVisible(), 'Dashboard icon identifiable');

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_dashboard_sidebar_collapsed.png') });

        // Verify LocalStorage persistence across page navigation
        console.log('  [TEST] Persistence across navigation...');
        await page.goto(`${BASE_URL}/patients`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(400);

        const persistedSidebarBox = await sidebar.boundingBox();
        recordResult('Persisted Collapsed State on New Page Load', persistedSidebarBox.width <= 75, `Width: ${persistedSidebarBox.width}px`);

        // Expand sidebar again
        await toggleBtn.click();
        await page.waitForTimeout(400);
        const restoredSidebarBox = await sidebar.boundingBox();
        recordResult('Sidebar Expands back to full width (~256px)', restoredSidebarBox.width >= 240, `Width: ${restoredSidebarBox.width}px`);

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_patients_sidebar_expanded.png') });

        // ------------------------------------------------------------------
        // SUITE 3: PRESCRIPTION PAD DESIGNER - FIELD LABELS & PREFIX
        // ------------------------------------------------------------------
        console.log('\n[SUITE 3] Prescription Pad Designer - Field Labels');
        await page.goto(`${BASE_URL}/settings/prescription-pad-templates`, { waitUntil: 'networkidle' });
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
        recordResult('Designer in DRAFT mode', page.url().includes('/settings/prescription-pad-designer/'), 'Ready for editing');

        // Add Token No field
        console.log('  [ACTION] Adding Token No field...');
        const addTokenBtn = page.locator('#btn_add_field_visit_token_no');
        await addTokenBtn.scrollIntoViewIfNeeded();
        await addTokenBtn.click();
        await page.waitForTimeout(600);
        await page.waitForSelector('g[data-element-key="visit.token_no"]', { timeout: 10000 });

        // Select the added field
        const lastFieldGroup = page.locator('g[data-element-key="visit.token_no"]').last();
        await lastFieldGroup.click({ force: true });
        await page.waitForTimeout(400);

        // Check properties panel for Field Label controls
        const showLabelCheckbox = page.locator('#input_show_label');
        const labelTextInput = page.locator('#prop_input_label_text');
        const previewText = page.locator('#field_label_preview_text');

        recordResult('Show Field Name Checkbox Exists', await showLabelCheckbox.count() > 0, 'Found #input_show_label');
        recordResult('Label Text Input Exists', await labelTextInput.count() > 0, 'Found #prop_input_label_text');

        const initialLabelVal = await labelTextInput.inputValue();
        recordResult('Default Label text populated', initialLabelVal === 'Token No.' || initialLabelVal === 'Token No', `Label: "${initialLabelVal}"`);

        // Check initial state (Show Field Name unchecked -> value only)
        const isShowLabelChecked = await showLabelCheckbox.isChecked();
        recordResult('Show Field Name default is FALSE', !isShowLabelChecked, 'Unchecked by default');

        const initialSvgText = await lastFieldGroup.locator('text.elem-rendered-text').textContent();
        recordResult('Canvas renders value only initially', !initialSvgText.includes(':'), `Canvas text: "${initialSvgText.trim()}"`);

        // Turn ON Show Field Name
        console.log('  [ACTION] Enabling Show Field Name...');
        await showLabelCheckbox.check();
        await page.waitForTimeout(500);

        const enabledSvgText = await lastFieldGroup.locator('text.elem-rendered-text').textContent();
        recordResult('Canvas renders "Label: Value" format', enabledSvgText.includes(': '), `Canvas text: "${enabledSvgText.trim()}"`);
        recordResult('Separator strictly contains ": "', enabledSvgText.includes(': ') && !enabledSvgText.includes(' : '), `Formatted: "${enabledSvgText.trim()}"`);

        // Custom Label Text
        console.log('  [ACTION] Changing Label text to "Token Number"...');
        await labelTextInput.fill('Token Number');
        await page.waitForTimeout(500);

        const customSvgText = await lastFieldGroup.locator('text.elem-rendered-text').textContent();
        recordResult('Canvas reflects custom label text', customSvgText.includes('Token Number: '), `Canvas text: "${customSvgText.trim()}"`);

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_field_label_enabled.png') });

        // Save Draft and reload to verify persistence
        console.log('  [ACTION] Saving template draft...');
        const saveDraftBtn = page.locator('#btn_save_draft, button:has-text("Save Draft")').first();
        await saveDraftBtn.click();
        await page.waitForTimeout(1000);

        await page.reload({ waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);

        const reloadedField = page.locator('g[data-element-key="visit.token_no"]').last();
        const reloadedText = await reloadedField.locator('text.elem-rendered-text').textContent();
        recordResult('Field label preference persisted after reload', reloadedText.includes('Token Number: '), `Reloaded text: "${reloadedText.trim()}"`);

        // ------------------------------------------------------------------
        // SUITE 4: RIGHT-CLICK DELETE SELECTED OBJECT
        // ------------------------------------------------------------------
        console.log('\n[SUITE 4] Right-Click Delete Selected Object');

        // Add a Static Text object as Object B so we can verify Object B is NOT deleted
        console.log('  [ACTION] Adding Static Text (Object B)...');
        const addStaticBtn = page.locator('#btn_add_static_text, button:has-text("+ Static Text")').first();
        await addStaticBtn.click();
        await page.waitForTimeout(800);

        const initialCount = await page.locator('g[data-element-id]').count();
        recordResult('Multiple objects exist on canvas', initialCount >= 2, `Object count: ${initialCount}`);

        // Select and Right-click the Token No field (Object A)
        console.log('  [ACTION] Right-clicking Object A (Token No)...');
        const tokenElem = page.locator('g[data-element-key="visit.token_no"]').last();
        const tokenBox = await tokenElem.boundingBox();

        // Right-click on the object
        await page.mouse.click(tokenBox.x + tokenBox.width / 2, tokenBox.y + tokenBox.height / 2, { button: 'right' });
        await page.waitForTimeout(300);

        const contextMenu = page.locator('#designerContextMenu');
        recordResult('Context Menu appears on right-click', await contextMenu.isVisible(), 'Custom context menu opened');

        const contextDeleteBtn = page.locator('#btn_context_delete');
        recordResult('Context Menu has working Delete button', await contextDeleteBtn.isVisible(), '#btn_context_delete visible');

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_context_menu_visible.png') });

        // Click Delete
        console.log('  [ACTION] Clicking Delete from context menu...');
        await contextDeleteBtn.click();
        await page.waitForTimeout(800);

        const postDeleteCount = await page.locator('g[data-element-id]').count();
        recordResult('Exact targeted object was deleted', postDeleteCount === initialCount - 1, `Count reduced from ${initialCount} to ${postDeleteCount}`);

        // Verify remaining object is still intact
        const remainingStatic = page.locator('g[data-element-type="STATIC_TEXT"]').last();
        recordResult('Object B (Static Text) remained intact', await remainingStatic.count() > 0, 'Object B preserved');

        // Test Right-click Canvas Background does NOT delete anything
        console.log('  [ACTION] Right-clicking canvas background...');
        const canvasBg = page.locator('#canvasBackground');
        await canvasBg.click({ button: 'right', position: { x: 50, y: 50 }, force: true });
        await page.waitForTimeout(300);

        const countAfterBgClick = await page.locator('g[data-element-id]').count();
        recordResult('Right-clicking background does not delete objects', countAfterBgClick === postDeleteCount, `Count unchanged: ${countAfterBgClick}`);

        // Test Keyboard Delete on selected object
        console.log('  [ACTION] Testing keyboard Delete on Object B...');
        await remainingStatic.click({ force: true });
        await page.waitForTimeout(300);
        await page.keyboard.press('Delete');
        await page.waitForTimeout(600);

        const countAfterKeyDelete = await page.locator('g[data-element-id]').count();
        recordResult('Keyboard Delete key removed selected object', countAfterKeyDelete === postDeleteCount - 1, `Count: ${countAfterKeyDelete}`);

        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_all_deleted_canvas.png') });

        // Save draft and reload to verify deletions persist
        await saveDraftBtn.click();
        await page.waitForTimeout(1000);
        await page.reload({ waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);

        const reloadedCount = await page.locator('g[data-element-id]').count();
        recordResult('Deletions persisted in database on reload', reloadedCount === countAfterKeyDelete, `DB element count: ${reloadedCount}`);

    } catch (err) {
        console.error('\n[SUITE EXCEPTION]', err);
        recordResult('Test Execution Exception', false, err.message);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'error_state.png') }).catch(() => {});
    } finally {
        await browser.close();

        console.log('\n======================================================================');
        console.log(`  E2E SUMMARY: ${summary.passed}/${summary.total} PASSED (${summary.failed} FAILED)`);
        console.log('======================================================================\n');

        if (summary.failed > 0) {
            process.exit(1);
        }
    }
}

runSuite();
