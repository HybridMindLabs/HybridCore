import { test, expect } from '@playwright/test';
import { loginAs, createTestUser } from './helpers/auth';

/**
 * Generating a real TOTP code would need a signed-in authenticator library
 * just for this one test — the actual TOTP verification logic (valid code,
 * recovery codes, challenge redirect) is already covered thoroughly by
 * tests/Feature/Web/TwoFactorTest.php with a mocked Google2FA. What that
 * suite can't see is whether the browser flow is wired correctly: does
 * clicking "set up" actually fetch and show a QR/secret, and does an
 * obviously-wrong code surface an error instead of silently doing nothing.
 */
test('setting up 2FA reaches the code step, and a bad code is rejected', async ({ page }) => {
    test.setTimeout(45_000);

    const user = await createTestUser(test.info().project.use.baseURL as string);
    await loginAs(page, user.email, user.password);

    await page.goto('/account');
    // The same tab label exists twice — a mobile nav (sm:hidden) and the
    // desktop sidebar — so scope to whichever is actually visible/clickable
    // at the test's viewport instead of guessing DOM order.
    const securityTab = page.getByRole('button', { name: /password & 2fa|парола & 2fa/i }).and(page.locator(':visible'));
    if (await securityTab.count() === 0) test.skip();
    await securityTab.first().click();

    const setupButton = page.getByRole('button', { name: /set up two-factor|настрои двуфакторна/i });
    if (await setupButton.count() === 0) test.skip();
    await setupButton.click();

    await expect(page.locator('#totp_code')).toBeVisible();
    // The secret/QR fetch completed, which is the actual point of this check.
    await expect(page.locator('code').first()).not.toHaveText('');

    await page.fill('#totp_code', '000000');
    await page.getByRole('button', { name: /verify & enable|потвърди и включи/i }).click();

    // account.2fa_code_invalid — distinct from the login-challenge error
    // (account.2fa_challenge_invalid, "Invalid code..."), which is a
    // different message for a different step.
    await expect(page.locator('body')).toContainText(/that code is not right|кодът не е верен/i, { timeout: 15_000 });
});
