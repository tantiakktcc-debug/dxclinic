import { chromium } from 'playwright';
import { execSync } from 'child_process';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';

async function debugTests() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 960 } });
    const page = await context.newPage();

    // Login
    await page.goto(`${BASE_URL}/superadmin/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('#username', 'dxdev');
    await page.fill('#password', 'dev@dxclinic');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('domcontentloaded');

    // Test 25: LR-000002 detail page - event timeline heading
    await page.goto(`${BASE_URL}/superadmin/license-history`, { waitUntil: 'domcontentloaded' });
    const rowLr2 = page.locator('tr:has-text("LR-000002")');
    await rowLr2.locator('a[href*="/superadmin/license-history/"]').first().click();
    await page.waitForLoadState('domcontentloaded');
    
    const detailText = await page.innerText('body');
    console.log('\n=== TEST 25 DEBUG ===');
    console.log('Has "Event Timeline & Audit Trail":', detailText.includes('Event Timeline & Audit Trail'));
    console.log('Has "Event Timeline":', detailText.includes('Event Timeline'));
    console.log('Has "Audit Trail":', detailText.includes('Audit Trail'));
    console.log('Has "SUBMITTED":', detailText.includes('SUBMITTED'));
    console.log('Has "FULFILLED":', detailText.includes('FULFILLED'));
    
    // Find the actual timeline heading text
    const timelineCard = await page.$('#cardEventTimeline');
    if (timelineCard) {
        const cardText = await timelineCard.innerText();
        console.log('Event timeline card text (first 200 chars):', cardText.substring(0, 200));
    } else {
        console.log('cardEventTimeline NOT FOUND');
    }

    // Test 28: LR-000003 detail page
    await page.goto(`${BASE_URL}/superadmin/license-history`, { waitUntil: 'domcontentloaded' });
    const rowLr3 = page.locator('tr:has-text("LR-000003")');
    await rowLr3.locator('a[href*="/superadmin/license-history/"]').first().click();
    await page.waitForLoadState('domcontentloaded');
    
    const addonText = await page.innerText('body');
    console.log('\n=== TEST 28 DEBUG ===');
    console.log('Has "Additional Doctor Seats Requested":', addonText.includes('Additional Doctor Seats Requested'));
    console.log('Has "DOCTOR_SEAT_ADDON":', addonText.includes('DOCTOR_SEAT_ADDON'));
    console.log('Has "Doctor Seat":', addonText.includes('Doctor Seat'));
    console.log('Has "+1":', addonText.includes('+1'));
    
    // Check the plan card
    const planCard = await page.$('#cardPlanPricingSnapshot');
    if (planCard) {
        const planText = await planCard.innerText();
        console.log('Plan card text:', planText.substring(0, 400));
    } else {
        console.log('cardPlanPricingSnapshot NOT FOUND');
    }
    
    await browser.close();
}

debugTests().catch(console.error);
