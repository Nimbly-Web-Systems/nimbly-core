import { test, expect } from '@playwright/test';

const TEST_IMAGE_UUID = '836dccc3121a25ebdcee594631b0a4a7';
const TEST_IMAGE_URL = `/img/${TEST_IMAGE_UUID}/120w`;

test('thumbnail ratios accept decimals and reject malformed values', async ({ request }) => {
  const default_response = await request.get(TEST_IMAGE_URL);
  expect(default_response.status()).toBe(200);
  expect(default_response.headers()['content-type']).toContain('image/webp');

  for (const ratio of ['1.5', '1.50']) {
    const response = await request.get(`${TEST_IMAGE_URL}?ratio=${ratio}`);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('image/webp');
  }

  for (const ratio of ['anytext', '1e2', '0', '-1', '101', "1' OR 1=1"]) {
    const response = await request.get(
      `${TEST_IMAGE_URL}?ratio=${encodeURIComponent(ratio)}`,
    );
    expect(response.status()).toBe(400);
    expect((await response.body()).length).toBe(0);
  }
});
