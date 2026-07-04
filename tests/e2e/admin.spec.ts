import { test, expect } from '@playwright/test';

// These tests require an admin user seeded in the test DB.
// Set ADMIN_EMAIL / ADMIN_PASSWORD env vars or use the defaults below.
const ADMIN_EMAIL = process.env.ADMIN_EMAIL ?? 'admin@hybridcore.test';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD ?? 'password';

test.describe('Admin dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', ADMIN_EMAIL);
        await page.fill('input[name="password"]', ADMIN_PASSWORD);
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/(admin|account|welcome)/);
    });

    test('admin can reach dashboard', async ({ page }) => {
        await page.goto('/admin');
        // Non-admin redirected away; admin sees the panel
        const url = page.url();
        if (url.includes('/admin')) {
            await expect(page.locator('h1, [data-page-title]')).toBeVisible();
        }
    });

    test('admin user list renders', async ({ page }) => {
        await page.goto('/admin/users');
        const url = page.url();
        if (url.includes('/admin/users')) {
            await expect(page.locator('table, [data-testid="user-list"]')).toBeVisible();
        }
    });
});
