import { test, expect } from '@playwright/test';
import { loginAs, createTestUser } from './helpers/auth';

/**
 * Registration is throttled at 5/min, so these tests share a single account
 * created once per run.
 */
let shared: { email: string; password: string };

test.beforeAll(async ({ }, testInfo) => {
    shared = await createTestUser(testInfo.project.use.baseURL as string);
});

test.describe('News comments', () => {
    test('logged-in user can post and delete a comment', async ({ page }) => {
        await loginAs(page, shared.email, shared.password);

        // Open the first published article from the news index.
        await page.goto('/news');
        const firstArticle = page.locator('a[href*="/news/"]:not([href*="category"]):not([href*="tag"]):not([href*="feed"])').first();
        if (await firstArticle.count() === 0) test.skip();
        await firstArticle.click();
        await page.waitForURL(/\/news\/.+/);

        const body = `E2E comment ${Date.now()}`;
        await page.fill('textarea[maxlength="1000"]', body);
        await page.getByRole('button', { name: /post comment|публикувай коментар/i }).click();

        await expect(page.locator('body')).toContainText(body);

        // Delete own comment (trash button inside the comment row).
        page.on('dialog', (d) => d.accept());
        const deleteBtn = page.locator('div', { hasText: body }).locator('button[title*="elete"], button[title*="зтрий"]').last();
        await deleteBtn.click();
        await expect(page.locator('body')).not.toContainText(body);
    });

    test('guest sees login prompt instead of comment form', async ({ page }) => {
        await page.goto('/news');
        const firstArticle = page.locator('a[href*="/news/"]:not([href*="category"]):not([href*="tag"]):not([href*="feed"])').first();
        if (await firstArticle.count() === 0) test.skip();
        await firstArticle.click();
        await page.waitForURL(/\/news\/.+/);

        await expect(page.locator('textarea[maxlength="1000"]')).toHaveCount(0);
        await expect(page.locator('body')).toContainText(/log in|влез/i);
    });
});

test.describe('Follow system', () => {
    test('user can follow and unfollow another member', async ({ page }) => {
        await loginAs(page, shared.email, shared.password);

        // Find someone to follow via the members page.
        await page.goto('/members');
        const profileLink = page.locator('a[href*="/u/"]').first();
        if (await profileLink.count() === 0) test.skip();
        await profileLink.click();
        await page.waitForURL(/\/u\/.+/);

        const followBtn = page.getByRole('button', { name: /^(follow|последвай)$/i });
        // Own profile has no follow button — skip in that unlikely case.
        if (await followBtn.count() === 0) test.skip();

        await followBtn.click();
        await expect(page.getByRole('button', { name: /following|следваш/i })).toBeVisible();

        // Unfollow again
        await page.getByRole('button', { name: /following|следваш/i }).click();
        await expect(page.getByRole('button', { name: /^(follow|последвай)$/i })).toBeVisible();
    });
});
