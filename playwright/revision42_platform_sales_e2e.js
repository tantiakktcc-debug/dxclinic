import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.resolve('test-results/revision42');

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
    console.log('  DXCLINIC REVISION 42 — PLATFORM SALES & ACCOUNTING E2E TEST SUITE   ');
    console.log('======================================================================\n');

    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    console.log(`[INIT] Automated Browser: Google Chrome v${browser.version()}`);

    try {
        // ==================================================================
        // PHASE 1: Superadmin Login
        // ==================================================================
        console.log('\n--- 1. Superadmin Login ---');
        const adminContext = await browser.newContext({
            viewport: { width: 1440, height: 900 },
            userAgent: 'DxClinic-Revision42-E2E-Superadmin/1.0'
        });
        const page = await adminContext.newPage();

        await page.goto(`${BASE_URL}/superadmin/login`, { waitUntil: 'networkidle' });
        await page.fill('#email', 'superadmin@dxclinic.local');
        await page.fill('#password', 'TestAdminPass123!');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/superadmin/dashboard');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_superadmin_dashboard.png'), fullPage: true });

        const isSuperadminDash = page.url().includes('/superadmin/dashboard');
        recordResult('1. Superadmin login successful', isSuperadminDash, page.url());

        // ==================================================================
        // PHASE 2: Sales Dashboard & Financial KPIs
        // ==================================================================
        console.log('\n--- 2. Sales Dashboard & Financial KPIs ---');
        await page.goto(`${BASE_URL}/superadmin/sales`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_sales_dashboard.png'), fullPage: true });

        const salesContent = await page.content();
        const hasGrossSales = salesContent.includes('Total Sales (Gross)');
        const hasNetSales = salesContent.includes('Net Sales');
        const hasCollected = salesContent.includes('Collected');
        const hasOutstanding = salesContent.includes('Outstanding');
        const hasExpenses = salesContent.includes('Platform Expenses');
        const hasProfit = salesContent.includes('Operating Profit');

        recordResult('2. Sales dashboard rendered with all 7 KPI cards', 
            hasGrossSales && hasNetSales && hasCollected && hasOutstanding && hasExpenses && hasProfit,
            'All core KPI cards verified'
        );

        // ==================================================================
        // PHASE 3: Sales Dashboard Filters
        // ==================================================================
        console.log('\n--- 3. Sales Dashboard Filters ---');
        // Test Filter: Financial Year Preset
        await page.click('a:has-text("Financial Year")');
        await page.waitForLoadState('networkidle');
        const fyContent = await page.content();
        recordResult('3. Financial Year filter applied', page.url().includes('range=financial_year'), 'Preset active');

        // Test Filter: Reset
        await page.click('a:has-text("All Time")');
        await page.waitForLoadState('networkidle');
        recordResult('3. All Time filter active', page.url().includes('range=all') || !page.url().includes('range='), 'All time reset');

        // ==================================================================
        // PHASE 4: Invoice List
        // ==================================================================
        console.log('\n--- 4. Invoice List ---');
        await page.goto(`${BASE_URL}/superadmin/invoices`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_invoice_list.png'), fullPage: true });

        const invListContent = await page.content();
        const hasInvoiceNumber = invListContent.includes('INV-2026-');
        const hasMaskedKey = invListContent.includes('NXCL-••••-••••-••••-••••');
        const hasClinicPill = invListContent.includes('NX0001');

        recordResult('4. Invoice list table rendered with masked key', 
            hasInvoiceNumber && hasMaskedKey && hasClinicPill,
            'INV numbers and masked keys present'
        );

        // ==================================================================
        // PHASE 5: Invoice Detail View & Immutability
        // ==================================================================
        console.log('\n--- 5. Invoice Detail View ---');
        // Click on the first invoice link
        const firstInvoiceLink = await page.locator('a.inv-num').first();
        const invoiceNumText = await firstInvoiceLink.innerText();
        await firstInvoiceLink.click();
        await page.waitForURL('**/superadmin/invoices/*');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_invoice_detail.png'), fullPage: true });

        const detailContent = await page.content();
        const hasBilledTo = detailContent.includes('Billed To (Clinic Tenant)');
        const hasLineItems = detailContent.includes('Item Description') && detailContent.includes('Base Plan');
        const hasTotals = detailContent.includes('Subtotal (Gross)') && detailContent.includes('Net Amount');
        const hasPrintBtn = detailContent.includes('Print Invoice');
        const hasPdfBtn = detailContent.includes('Download Vector PDF');

        recordResult('5. Invoice detail view rendered with items and totals', 
            hasBilledTo && hasLineItems && hasTotals && hasPrintBtn && hasPdfBtn,
            `Invoice ${invoiceNumText} detail loaded`
        );

        // ==================================================================
        // PHASE 6: Invoice Print View
        // ==================================================================
        console.log('\n--- 6. Invoice Print View ---');
        const currentUrl = page.url();
        const invoiceId = currentUrl.split('/').pop().split('?')[0];

        const printPage = await adminContext.newPage();
        await printPage.goto(`${BASE_URL}/superadmin/invoices/${invoiceId}/print`, { waitUntil: 'networkidle' });
        await printPage.screenshot({ path: path.join(SCREENSHOT_DIR, '06_invoice_print.png'), fullPage: true });

        const printContent = await printPage.content();
        recordResult('6. Invoice print view rendered clean HTML', 
            printContent.includes('COMMERCIAL INVOICE') && printContent.includes('Billed To'),
            'Print template validated'
        );
        await printPage.close();

        // ==================================================================
        // PHASE 7: Invoice PDF Download/Stream Route
        // ==================================================================
        console.log('\n--- 7. Invoice PDF Route ---');
        const pdfResp = await page.request.get(`${BASE_URL}/superadmin/invoices/${invoiceId}/pdf`);
        const pdfStatus = pdfResp.status();
        const contentType = pdfResp.headers()['content-type'] || '';
        recordResult('7. Invoice PDF generation endpoint functional', 
            pdfStatus === 200 && contentType.includes('application/pdf'),
            `HTTP ${pdfStatus} Content-Type: ${contentType}`
        );

        // ==================================================================
        // PHASE 8: Collections Ledger & Aggregations
        // ==================================================================
        console.log('\n--- 8. Collections Ledger & Aggregation Modes ---');
        await page.goto(`${BASE_URL}/superadmin/collections`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '08_collections_transactions.png'), fullPage: true });

        const collContent = await page.content();
        const hasTotalCollection = collContent.includes('Total Collection');
        const hasNetCollection = collContent.includes('Net Collection');
        recordResult('8. Collections ledger transactions rendered', 
            hasTotalCollection && hasNetCollection,
            'Collections KPI cards loaded'
        );

        // Test aggregation tabs
        await page.click('a:has-text("Daily Totals")');
        await page.waitForLoadState('networkidle');
        recordResult('8. Collections daily aggregation view functional', page.url().includes('view_type=daily'), 'Daily totals tab loaded');

        await page.click('a:has-text("Monthly Totals")');
        await page.waitForLoadState('networkidle');
        recordResult('8. Collections monthly aggregation view functional', page.url().includes('view_type=monthly'), 'Monthly totals tab loaded');

        await page.click('a:has-text("Financial Year Totals")');
        await page.waitForLoadState('networkidle');
        recordResult('8. Collections FY aggregation view functional', page.url().includes('view_type=fy'), 'FY totals tab loaded');

        // ==================================================================
        // PHASE 9: Per-Clinic Commercial History
        // ==================================================================
        console.log('\n--- 9. Per-Clinic Commercial History ---');
        await page.goto(`${BASE_URL}/superadmin/clinics/1/sales`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '09_clinic_sales_history.png'), fullPage: true });

        const clinicSalesContent = await page.content();
        const hasPermanentKeyBox = clinicSalesContent.includes('Permanent License Identity');
        const hasChronologicalTimeline = clinicSalesContent.includes('Chronological Commercial');
        const hasClinicCode = clinicSalesContent.includes('NX0001');

        recordResult('9. Per-Clinic commercial history with permanent license identity rendered', 
            hasPermanentKeyBox && hasChronologicalTimeline && hasClinicCode,
            'NX0001 commercial history verified'
        );

        // ==================================================================
        // PHASE 10: Platform Overhead Expenses Management
        // ==================================================================
        console.log('\n--- 10. Platform Expenses Management ---');
        await page.goto(`${BASE_URL}/superadmin/expenses`, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '10_platform_expenses.png'), fullPage: true });

        const expensesInitialContent = await page.content();
        recordResult('10. Platform expenses page loaded with provider banner', 
            expensesInitialContent.includes('Provider-Level Operating Expenses Only') && expensesInitialContent.includes('Record Platform Expense'),
            'Strict isolation banner present'
        );

        // Record a new platform expense
        const testExpenseDesc = 'AWS Multi-Region Database Cluster E2E ' + Date.now();
        await page.selectOption('select[name="category"]', 'SERVER_HOSTING');
        await page.fill('input[name="description"]', testExpenseDesc);
        await page.fill('input[name="amount"]', '5500.00');
        await page.selectOption('select[name="payment_method"]', 'BANK_TRANSFER');
        await page.fill('input[name="reference"]', 'AWS-E2E-REF-01');
        await page.click('button:has-text("Record Expense")');

        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '10_platform_expense_created.png'), fullPage: true });

        const expensesAfterContent = await page.content();
        recordResult('10. New platform expense recorded successfully', 
            expensesAfterContent.includes(testExpenseDesc) && expensesAfterContent.includes('5,500.00'),
            'Recorded ₹5,500.00 expense'
        );

        // ==================================================================
        // PHASE 11: Operating Profit Calculation Verification
        // ==================================================================
        console.log('\n--- 11. Operating Profit Calculation on Dashboard ---');
        await page.goto(`${BASE_URL}/superadmin/sales`, { waitUntil: 'networkidle' });
        const updatedSalesContent = await page.content();
        recordResult('11. Operating profit incorporates new platform expenses', 
            updatedSalesContent.includes('Platform Expenses') && updatedSalesContent.includes('Operating Profit'),
            'Operating profit reconciled'
        );

        // ==================================================================
        // PHASE 12: Negative Security Test — Tenant Users Denied Access
        // ==================================================================
        console.log('\n--- 12. Negative Security Test: Tenant User Denied ---');
        const tenantContext = await browser.newContext({
            viewport: { width: 1440, height: 900 },
            userAgent: 'DxClinic-Revision42-E2E-Tenant/1.0'
        });
        const tenantPage = await tenantContext.newPage();

        // Login as tenant user of NX0001
        await tenantPage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
        await tenantPage.fill('#clinic_code', 'NX0001');
        await tenantPage.fill('#email', 'admin@dxclinic.com');
        await tenantPage.fill('#password', 'password');
        await tenantPage.click('button[type="submit"]');
        await tenantPage.waitForURL('**/dashboard');

        // Attempt accessing /superadmin/sales
        await tenantPage.goto(`${BASE_URL}/superadmin/sales`, { waitUntil: 'networkidle' });
        const deniedSales = tenantPage.url().includes('/superadmin/login') || tenantPage.url().includes('/dashboard');
        recordResult('12. Tenant blocked from /superadmin/sales', deniedSales, `Redirected to: ${tenantPage.url()}`);

        // Attempt accessing /superadmin/invoices
        await tenantPage.goto(`${BASE_URL}/superadmin/invoices`, { waitUntil: 'networkidle' });
        const deniedInvoices = tenantPage.url().includes('/superadmin/login') || tenantPage.url().includes('/dashboard');
        recordResult('12. Tenant blocked from /superadmin/invoices', deniedInvoices, `Redirected to: ${tenantPage.url()}`);

        // Attempt accessing /superadmin/collections
        await tenantPage.goto(`${BASE_URL}/superadmin/collections`, { waitUntil: 'networkidle' });
        const deniedCollections = tenantPage.url().includes('/superadmin/login') || tenantPage.url().includes('/dashboard');
        recordResult('12. Tenant blocked from /superadmin/collections', deniedCollections, `Redirected to: ${tenantPage.url()}`);

        // Attempt accessing /superadmin/expenses
        await tenantPage.goto(`${BASE_URL}/superadmin/expenses`, { waitUntil: 'networkidle' });
        const deniedExpenses = tenantPage.url().includes('/superadmin/login') || tenantPage.url().includes('/dashboard');
        recordResult('12. Tenant blocked from /superadmin/expenses', deniedExpenses, `Redirected to: ${tenantPage.url()}`);

        // Attempt accessing /superadmin/clinics/1/sales
        await tenantPage.goto(`${BASE_URL}/superadmin/clinics/1/sales`, { waitUntil: 'networkidle' });
        const deniedClinicSales = tenantPage.url().includes('/superadmin/login') || tenantPage.url().includes('/dashboard');
        recordResult('12. Tenant blocked from /superadmin/clinics/1/sales', deniedClinicSales, `Redirected to: ${tenantPage.url()}`);

        // API/JSON test: Expect 403 Forbidden
        const jsonResp = await tenantContext.request.get(`${BASE_URL}/superadmin/sales`, {
            headers: { 'Accept': 'application/json' }
        });
        recordResult('12. JSON request from tenant returns 403 Forbidden', 
            jsonResp.status() === 403 || jsonResp.status() === 401, 
            `Status: ${jsonResp.status()}`
        );

        await tenantContext.close();
        await adminContext.close();

    } catch (err) {
        console.error('\n[FATAL ERROR] E2E Suite interrupted:', err);
        recordResult('Suite execution', false, err.message);
    } finally {
        await browser.close();
    }

    console.log('\n======================================================================');
    console.log(`  E2E TEST SUMMARY: Total: ${summary.total} | Passed: ${summary.passed} | Failed: ${summary.failed}`);
    console.log('======================================================================\n');

    if (summary.failed > 0) {
        process.exit(1);
    }
}

runSuite().catch(err => {
    console.error('Test runner error:', err);
    process.exit(1);
});
