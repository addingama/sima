import { defineConfig, devices } from "@playwright/test";

import { resolve } from "node:path";

const database = resolve(__dirname, "../storage/e2e-demo.sqlite");

export default defineConfig({
  testDir: "./e2e/demo",
  testMatch: "**/*.spec.ts",
  fullyParallel: false,
  retries: 0,
  workers: 1,
  timeout: 10 * 60 * 1000,
  expect: { timeout: 15_000 },
  reporter: [["list"], ["html", { open: "never", outputFolder: "playwright-report-demo" }]],
  use: {
    baseURL: "http://127.0.0.1:3110",
    trace: "off",
    screenshot: "off",
    video: { mode: "on", size: { width: 1280, height: 720 } },
    viewport: { width: 1280, height: 720 },
    deviceScaleFactor: 1,
    locale: "id-ID",
    launchOptions: {
      slowMo: 280,
    },
  },
  webServer: [
    {
      command: "node frontend/e2e/demo/prepare.mjs && php artisan serve --host=127.0.0.1 --port=8110 --no-reload",
      cwd: "..",
      url: "http://127.0.0.1:8110/api/health",
      reuseExistingServer: false,
      timeout: 180_000,
      env: {
        ...process.env,
        APP_ENV: "testing",
        DB_CONNECTION: "sqlite",
        DB_DATABASE: database,
        CACHE_STORE: "array",
        SESSION_DRIVER: "array",
        QUEUE_CONNECTION: "sync",
        FRONTEND_URL: "http://127.0.0.1:3110",
      },
    },
    {
      command: "npm run dev -- --hostname 127.0.0.1 --port 3110",
      url: "http://127.0.0.1:3110/auth/v2/login",
      reuseExistingServer: false,
      timeout: 180_000,
      env: {
        ...process.env,
        NEXT_PUBLIC_API_URL: "http://127.0.0.1:8110/api",
        NEXT_DIST_DIR: ".next-e2e-demo",
      },
    },
  ],
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"], viewport: { width: 1280, height: 720 } } }],
  outputDir: "test-results-demo",
});
