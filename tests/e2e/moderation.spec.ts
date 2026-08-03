import { test, expect } from '@playwright/test';
import { loginAs, createTestUser } from './helpers/auth';

const ADMIN_EMAIL = process.env.ADMIN_EMAIL ?? 'admin@hybridcore.test';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD ?? 'password';

test('a reported comment shows up in admin moderation and can be resolved', async ({ page }) => {
    test.setTimeout(45_000);

    const user = await createTestUser(test.info().project.use.baseURL as string);
    await loginAs(page, user.email, user.password);

    await page.goto('/news');
    const firstArticle = page.locator('a[href*="/news/"]:not([href*="category"]):not([href*="tag"]):not([href*="feed"])').first();
    if (await firstArticle.count() === 0) test.skip();
    await firstArticle.click();
    await page.waitForURL(/\/news\/.+/);

    const body = `E2E reportable comment ${Date.now()}`;
    await page.fill('textarea[maxlength="1000"]', body);
    await page.getByRole('button', { name: /post comment|публикувай коментар/i }).click();
    await expect(page.locator('body')).toContainText(body);

    // The report flag opens a small reason menu next to the comment.
    const commentRow = page.locator('div', { hasText: body }).last();
    await commentRow.locator('button[title="Report"], button[title="Докладвай"]').click();
    await page.getByRole('button', { name: /spam|спам/i }).click();

    // Sign in as admin in a fresh context — the user session above must stay
    // intact. browser.newPage() would implicitly create a context that only
    // gets cleaned up when the whole browser closes, not when the page does —
    // with every e2e test sharing one browser process, that leaks for the
    // rest of the run. Create (and close) the context explicitly instead.
    const adminContext = await page.context().browser()!.newContext();
    const adminPage = await adminContext.newPage();
    await adminPage.goto('/admin/login');
    await adminPage.fill('#email', ADMIN_EMAIL);
    await adminPage.fill('#password', ADMIN_PASSWORD);
    await adminPage.click('button[type="submit"]');
    await adminPage.waitForURL(/\/admin/);

    await adminPage.goto('/admin/reports');
    await expect(adminPage.locator('body')).toContainText(body.slice(0, 30));

    const reportRow = adminPage.locator('div', { hasText: body.slice(0, 30) }).last();
    await reportRow.getByRole('button', { name: 'Resolve' }).click();
    await expect(adminPage.locator('body')).toContainText('resolved');

    await adminContext.close();
});
