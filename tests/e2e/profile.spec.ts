import { test, expect } from '@playwright/test';
import { loginAs, createTestUser } from './helpers/auth';

test('user can edit their profile', async ({ page }) => {
    const user = await createTestUser(test.info().project.use.baseURL as string);
    await loginAs(page, user.email, user.password);

    // Profile is the default tab on /account for a fresh session.
    await page.goto('/account');

    const displayName = `E2E Name ${Date.now()}`;
    await page.fill('#pf_display_name', displayName);

    const bio = `E2E bio ${Date.now()}`;
    await page.fill('#pf_bio', bio);

    await page.getByRole('button', { name: /save profile|запази профила/i }).click();

    // form.recentlySuccessful renders a role="status" confirmation.
    await expect(page.getByRole('status')).toBeVisible();

    await page.reload();
    await expect(page.locator('#pf_display_name')).toHaveValue(displayName);
    await expect(page.locator('#pf_bio')).toHaveValue(bio);
});
