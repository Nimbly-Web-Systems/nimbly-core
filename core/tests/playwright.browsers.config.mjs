// The server-free editor specs in one browser engine: BROWSER=chromium|firefox|webkit
//   BROWSER=webkit npx playwright test -c core/tests/playwright.browsers.config.mjs
// (webkit on Linux needs its system libraries once: sudo env "PATH=$PATH" npx playwright install-deps webkit)
import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './specs', outputDir: './test-results', timeout: 30_000, workers: 1,
  testMatch: ['bubble-editor.spec.js', 'translation-editor.spec.js'],
  projects: [{ name: process.env.BROWSER, use: { browserName: process.env.BROWSER, headless: true } }],
  reporter: [['line']],
});
