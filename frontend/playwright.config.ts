import { defineConfig, devices } from "@playwright/test";

import { resolve } from "node:path";

const database = resolve(__dirname, "../storage/e2e.sqlite");

export default defineConfig({
  testDir: "./e2e/specs",
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI ? [["html", { open: "never" }], ["github"]] : "list",
  use: {
    baseURL: "http://127.0.0.1:3100",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "retain-on-failure",
  },
  webServer: [
    {
      // Laravel's serve auto-reloader only forwards a small allowlist of environment
      // variables to its child process. Disable it so the E2E CORS/database variables
      // below reach the actual PHP server instead of falling back to the local .env.
      command: "node frontend/e2e/prepare.mjs && php artisan serve --host=127.0.0.1 --port=8100 --no-reload",
      cwd: "..",
      url: "http://127.0.0.1:8100/api/health",
      reuseExistingServer: !process.env.CI,
      env: {
        ...process.env,
        APP_ENV: "testing",
        DB_CONNECTION: "sqlite",
        DB_DATABASE: database,
        CACHE_STORE: "array",
        SESSION_DRIVER: "array",
        QUEUE_CONNECTION: "sync",
        FRONTEND_URL: "http://127.0.0.1:3100",
      },
    },
    {
      command: "npm run dev -- --hostname 127.0.0.1 --port 3100",
      url: "http://127.0.0.1:3100/auth/v2/login",
      reuseExistingServer: !process.env.CI,
      env: {
        ...process.env,
        NEXT_PUBLIC_API_URL: "http://127.0.0.1:8100/api",
        NEXT_DIST_DIR: ".next-e2e",
      },
    },
  ],
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
  outputDir: "test-results",
});
