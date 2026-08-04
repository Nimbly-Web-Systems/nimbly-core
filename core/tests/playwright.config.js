import { defineConfig } from '@playwright/test';

export default defineConfig({
  outputDir: './test-results',
  timeout: 30_000,
  workers: 1,
  bail: 1,
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost',
    headless: true,
  },
  projects: [
    {
      name: 'core',
      testDir: './specs',
    },
    {
      name: 'ext',
      testDir: '../../ext/tests',
      testMatch: '**/*.spec.js',
    },
  ],
  reporter: [['list'], ['html', { outputFolder: './playwright-report', open: 'never' }]],
});
