import { test, expect } from '@playwright/test';

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL(url => !url.toString().includes('/login'));
}

// The test role may edit .config but is no system manager: the refusal names the guard, not the permission.
test('an editor cannot change the page and navigation switches', async ({ page }) => {
  await login(page);
  const url = '/api/v1/.config/managed_pages';
  const read = async () => {
    const body = await (await page.request.get(url)).json();
    return body['.config']?.managed_pages ?? body;
  };
  const before = await read();
  const switches = { enabled: before.enabled === true, navigation_enabled: before.navigation_enabled === true };

  for (const flag of Object.keys(switches)) {
    const response = await page.request.put(url, { data: { ...switches, [flag]: !switches[flag] } });
    expect(response.ok(), `${flag} is refused`).toBe(false);
    expect(await response.text()).toContain('managed_pages:forbidden');
  }

  const after = await read();
  expect(after.enabled === true).toBe(switches.enabled);
  expect(after.navigation_enabled === true).toBe(switches.navigation_enabled);
});
