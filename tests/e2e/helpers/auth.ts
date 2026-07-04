import { Page, request as playwrightRequest } from '@playwright/test';

export async function loginAs(page: Page, email: string, password: string) {
    await page.goto('/login');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(account|welcome)/);
}

export async function createTestUser(baseURL: string): Promise<{ email: string; password: string }> {
    const ctx = await playwrightRequest.newContext({ baseURL, ignoreHTTPSErrors: true });

    // Fetch the register page first for the CSRF token + session cookie.
    const pageResponse = await ctx.get('/register');
    const html = await pageResponse.text();
    const csrf = html.match(/name="csrf-token" content="([^"]+)"/)?.[1] ?? '';

    const ts = Date.now();
    const payload = {
        _token: csrf,
        name: `Test User ${ts}`,
        email: `testuser${ts}@example.com`,
        password: 'password123',
        password_confirmation: 'password123',
    };
    await ctx.post('/register', { form: payload });
    await ctx.dispose();
    return { email: payload.email, password: payload.password };
}
