import { test, expect, Page } from '@playwright/test';
import { loginAs, createTestUser } from './helpers/auth';

/**
 * Registration is throttled at 5/min, so the comment/follow tests share a
 * single account created once per run; only the onboarding tests register
 * fresh users (they need an incomplete onboarding state).
 */
let shared: { email: string; password: string };

test.beforeAll(async ({ }, testInfo) => {
    shared = await createTestUser(testInfo.project.use.baseURL as string);
});

/** Register a fresh user through the UI and land on onboarding. */
async function registerFresh(page: Page): Promise<string> {
    const ts = Date.now() + Math.floor(Math.random() * 1000);
    const name = `E2E Community ${ts}`;
    await page.goto('/register');
    await page.fill('#name', name);
    await page.fill('#email', `community${ts}@example.com`);
    await page.fill('#password', 'password123');
    await page.fill('#password_confirmation', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(welcome|account)/);
    return name;
}

/** Complete onboarding if the login landed on the wizard. */
async function skipOnboardingIfPresent(page: Page) {
    if (page.url().includes('/welcome')) {
        await page.getByRole('button', { name: /skip all|пропусни всичко/i }).click();
        await page.waitForURL((url) => !url.pathname.includes('/welcome'));
    }
}

test.describe('Onboarding wizard', () => {
    test('walks all 4 steps and completes', async ({ page }) => {
        await registerFresh(page);
        if (!page.url().includes('/welcome')) test.skip();

        for (let i = 0; i < 3; i++) {
            await page.getByRole('button', { name: /next|напред/i }).click();
        }

        await expect(page.locator('body')).toContainText(/step 4 of 4|стъпка 4 от 4/i);

        await page.getByRole('button', { name: /let's go|да започваме/i }).click();
        await page.waitForURL((url) => !url.pathname.includes('/welcome'));
    });

    test('skip all completes onboarding immediately', async ({ page }) => {
        await registerFresh(page);
        if (!page.url().includes('/welcome')) test.skip();

        await page.getByRole('button', { name: /skip all|пропусни всичко/i }).click();
        await page.waitForURL((url) => !url.pathname.includes('/welcome'));
    });
});

test.describe('News comments', () => {
    test('logged-in user can post and delete a comment', async ({ page }) => {
        await loginAs(page, shared.email, shared.password);
        await skipOnboardingIfPresent(page);

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
        await skipOnboardingIfPresent(page);

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
