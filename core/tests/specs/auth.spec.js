import { test, expect } from '@playwright/test';

const CREDENTIALS = { email: 'test@nimbly.dev', password: 'testpass123' };

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', CREDENTIALS.email);
  await page.fill('[name=password]', CREDENTIALS.password);
  await page.click('[type=submit]');
  await page.waitForURL(url => !url.toString().includes('/login'));
}

test('login page loads', async ({ page }) => {
  await page.goto('/login');
  await expect(page.locator('form[name=login]')).toBeVisible();
});

test('wrong credentials shows error', async ({ page }) => {
  await page.goto('/login');
  await page.fill('[name=email]', CREDENTIALS.email);
  await page.fill('[name=password]', 'wrongpassword');
  await page.click('[type=submit]');
  await expect(page).toHaveURL(/login/);
  await expect(page.locator('body')).toContainText(/invalid/i);
});

test('login succeeds and test-records admin is accessible', async ({ page }) => {
  await login(page);
  await page.goto('/nb-admin/test-records');
  await expect(page).toHaveURL(/nb-admin\/test-records/);
});

test('protected test route accessible after login', async ({ page }) => {
  await login(page);
  await page.goto('/.test/ping');
  await expect(page.locator('#status')).toHaveText('ok');
});

test('protected test route redirects when not logged in', async ({ page }) => {
  await page.goto('/.test/ping');
  await expect(page).not.toHaveURL(/\.test\/ping/);
});

test('logout clears session', async ({ page }) => {
  await login(page);
  await page.goto('/logout');
  // session gone: protected page now bounces
  await page.goto('/.test/ping');
  await expect(page).not.toHaveURL(/\.test\/ping/);
});
