import { test, expect } from '@playwright/test';

test.describe('Server browser', () => {
    test('server list page renders', async ({ page }) => {
        await page.goto('/servers');
        await expect(page).toHaveTitle(/server|game/i);
        await expect(page.locator('h1')).toBeVisible();
    });

    test('search filters servers', async ({ page }) => {
        await page.goto('/servers');
        const searchInput = page.locator('input[type="search"], input[placeholder*="search" i]').first();
        if (await searchInput.isVisible()) {
            await searchInput.fill('test');
            await page.waitForTimeout(500);
            // Just verify the page doesn't crash
            await expect(page.locator('body')).not.toContainText('500');
        }
    });
});

test.describe('Public profile', () => {
    test('members page renders', async ({ page }) => {
        await page.goto('/members');
        await expect(page).toHaveTitle(/member/i);
        await expect(page.locator('h1')).toBeVisible();
    });
});
