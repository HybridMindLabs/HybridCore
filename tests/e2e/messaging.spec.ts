import { test, expect } from '@playwright/test';
import { createTestUser } from './helpers/auth';

/**
 * Two independent, authenticated browser contexts (not just one page) since
 * this exercises a real conversation between two distinct accounts.
 */
test('two users can exchange a direct message', async ({ browser }) => {
    const baseURL = test.info().project.use.baseURL as string;
    const userA = await createTestUser(baseURL);
    const userB = await createTestUser(baseURL);

    const ctxA = await browser.newContext();
    const pageA = await ctxA.newPage();
    await pageA.goto('/login');
    await pageA.fill('#email', userA.email);
    await pageA.fill('#password', userA.password);
    await pageA.click('button[type="submit"]');
    await pageA.waitForURL(/\/(account|welcome)/);

    const ctxB = await browser.newContext();
    const pageB = await ctxB.newPage();
    await pageB.goto('/login');
    await pageB.fill('#email', userB.email);
    await pageB.fill('#password', userB.password);
    await pageB.click('button[type="submit"]');
    await pageB.waitForURL(/\/(account|welcome)/);

    // Read B's own username off their profile tab to start a DM as A.
    await pageB.goto('/account');
    const usernameB = await pageB.locator('#pf_username').inputValue();

    await pageA.goto('/account/messages');
    await pageA.getByRole('button', { name: /new message|ново съобщение/i }).click();
    await pageA.fill('#dm-username', usernameB);
    await pageA.locator('#new-dm-form button[type="submit"]').click();

    await pageA.waitForURL(/\/account\/messages\/\d+/);

    const body = `E2E message ${Date.now()}`;
    await pageA.fill('#message-body', body);
    await pageA.getByRole('button', { name: /send message|изпрати/i }).click();
    await expect(pageA.locator('body')).toContainText(body);

    // B sees it too — this is a freshly created account, so the conversation
    // with A is the only one in their list.
    await pageB.goto('/account/messages');
    await pageB.locator('a[href*="/account/messages/"]').first().click();
    await expect(pageB.locator('body')).toContainText(body);

    await ctxA.close();
    await ctxB.close();
});
