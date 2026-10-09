import { defineConfig } from "@playwright/test";

const apiPort = process.env.E2E_API_PORT ?? "8001";
const webPort = process.env.E2E_WEB_PORT ?? "5179";
const apiUrl = `http://localhost:${apiPort}`;
const webUrl = `http://localhost:${webPort}`;

export default defineConfig({
  testDir: "./e2e",
  workers: 1,
  use: {
    baseURL: webUrl,
    browserName: "chromium",
    ...(process.env.CI ? {} : { channel: "chrome" }),
    trace: "retain-on-failure",
  },
  webServer: [
    {
      command: process.env.E2E_SERVER_COMMAND ?? "node scripts/e2e-server.mjs",
      env: { E2E_API_PORT: apiPort, E2E_WEB_PORT: webPort },
      url: `${apiUrl}/up`,
      timeout: 60000,
      gracefulShutdown: { signal: "SIGTERM", timeout: 15000 },
      reuseExistingServer: false,
    },
    {
      command: `npm run dev -- --host localhost --port ${webPort} --strictPort`,
      url: webUrl,
      env: { VITE_API_BASE_URL: apiUrl },
      reuseExistingServer: false,
    },
  ],
});
