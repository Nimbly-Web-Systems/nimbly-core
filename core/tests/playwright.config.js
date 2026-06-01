import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './specs',
  outputDir: './test-results',
  timeout: 30_000,
  workers: 1,
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost',
    headless: true,
  },
  reporter: [['list'], ['html', { outputFolder: './playwright-report', open: 'never' }]],
});
