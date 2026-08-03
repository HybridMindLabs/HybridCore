import { test, expect } from '@playwright/test';

const ADMIN_EMAIL = process.env.ADMIN_EMAIL ?? 'admin@hybridcore.test';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD ?? 'password';

test.describe('Admin news article CRUD', () => {
    test.setTimeout(60_000);

    test.beforeEach(async ({ page }) => {
        await page.goto('/admin/login');
        await page.fill('#email', ADMIN_EMAIL);
        await page.fill('#password', ADMIN_PASSWORD);
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/admin/);
    });

    test('admin can create and delete a news article', async ({ page }) => {
        await page.goto('/admin/news/articles/create');
        if (!page.url().includes('/admin/news/articles/create')) test.skip();

        const title = `E2E Article ${Date.now()}`;

        await page.fill('input[placeholder="Article title..."]', title);

        // Body is a CodeMirror editor, not a plain textarea/input.
        await page.locator('.cm-content').click();
        await page.keyboard.type('This is the E2E article body.');

        await page.getByRole('button', { name: 'Create Article' }).click();

        // store() redirects to the edit page for the new article.
        await page.waitForURL(/\/admin\/news\/articles\/\d+\/edit/);

        await page.goto('/admin/news/articles');
        await expect(page.locator('body')).toContainText(title);

        const row = page.locator('div', { hasText: title }).last();
        page.on('dialog', (d) => d.accept());
        await row.locator('button').last().click();

        await expect(page.locator('body')).not.toContainText(title);
    });
});
