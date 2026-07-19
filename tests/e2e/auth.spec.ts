import { test, expect } from '@playwright/test';

test.describe('Auth flows', () => {
    test('register lands straight on the site, with no wizard in between', async ({ page }) => {
        const ts = Date.now();
        await page.goto('/register');
        await page.fill('input[name="name"]', `E2E User ${ts}`);
        await page.fill('input[name="email"]', `e2e${ts}@example.com`);
        await page.fill('input[name="password"]', 'password123');
        await page.fill('input[name="password_confirmation"]', 'password123');
        await page.click('button[type="submit"]');

        // Home, or the verify-email notice when the owner requires it. What it
        // must never be again is a multi-step form standing between signing up
        // and seeing the site.
        await expect(page).toHaveURL(/\/(|email\/verify)$/);
    });

    test('login with invalid credentials shows error', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', 'notexist@example.com');
        await page.fill('input[name="password"]', 'wrongpassword');
        await page.click('button[type="submit"]');
        await expect(page.locator('form')).toContainText(/invalid|credentials|These credentials/i);
    });

    test('logout clears session', async ({ page }) => {
        const ts = Date.now();
        // register first
        await page.goto('/register');
        await page.fill('input[name="name"]', `Logout Test ${ts}`);
        await page.fill('input[name="email"]', `logout${ts}@example.com`);
        await page.fill('input[name="password"]', 'password123');
        await page.fill('input[name="password_confirmation"]', 'password123');
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/(welcome|account)/);

        // logout via form post
        await page.evaluate(() => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/logout';
            const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
            if (csrf) {
                const input = document.createElement('input');
                input.name = '_token';
                input.value = csrf;
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
        });
        await page.waitForURL('/');
        await expect(page).toHaveURL('/');
    });
});
