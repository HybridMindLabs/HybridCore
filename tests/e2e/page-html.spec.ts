import { test, expect } from '@playwright/test';

const ADMIN_EMAIL = process.env.ADMIN_EMAIL ?? 'admin@hybridcore.test';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD ?? 'password';

/**
 * The admin Pages editor's markdown/html format switch isn't wired up in the
 * current UI (MarkdownEditor never emits update:format), so there's no click
 * path to reach format=html through the form. This posts to the same
 * endpoint the form would, using the authenticated admin session, which is
 * exactly what a compromised/careless admin account could do too — the real
 * thing being tested here (PageController's HTMLPurifier pass) doesn't care
 * which UI produced the request.
 */
test('a format=html page has its script tags stripped on render', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/admin/login');
    await page.fill('#email', ADMIN_EMAIL);
    await page.fill('#password', ADMIN_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/admin/);

    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const slug = `e2e-html-page-${Date.now()}`;

    const response = await page.request.post('/admin/pages', {
        headers: { 'X-CSRF-TOKEN': csrf ?? '', Accept: 'application/json' },
        form: {
            title: 'E2E HTML Page',
            slug,
            format: 'html',
            status: 'published',
            body: '<p>Hello from E2E</p><script>window.__xss = true;</script><img src=x onerror="window.__xss = true">',
        },
    });
    expect(response.ok()).toBeTruthy();

    await page.goto(`/${slug}`);
    await expect(page.locator('body')).toContainText('Hello from E2E');
    await expect(page.locator('script', { hasText: '__xss' })).toHaveCount(0);
    expect(await page.evaluate(() => (window as unknown as { __xss?: boolean }).__xss)).toBeUndefined();
});
