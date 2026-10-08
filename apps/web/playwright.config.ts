import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "./e2e",
  workers: 1,
  use: {
    baseURL: "http://localhost:5179",
    browserName: "chromium",
    ...(process.env.CI ? {} : { channel: "chrome" }),
    trace: "retain-on-failure",
  },
  webServer: [
    {
      command:
        process.env.E2E_SERVER_COMMAND ??
        "cd ../server && ./vendor/bin/sail -f compose.yaml run --rm --no-deps -p 8001:8080 -e APP_ENV=e2e -e DB_DATABASE=testing -e APP_URL=http://localhost:8001 -e FRONTEND_URL=http://localhost:5179 -e CORS_ALLOWED_ORIGINS=http://localhost:5179 -e SANCTUM_STATEFUL_DOMAINS=localhost:5179 -e AUTH_REGISTRATION_ENABLED=true -e MAIL_MAILER=array laravel.test php artisan serve --host=0.0.0.0 --port=8080 --no-reload",
      url: "http://localhost:8001/up",
      timeout: 60000,
      reuseExistingServer: false,
    },
    {
      command: "npm run dev -- --host localhost --port 5179 --strictPort",
      url: "http://localhost:5179",
      env: { VITE_API_BASE_URL: "http://localhost:8001" },
      reuseExistingServer: false,
    },
  ],
});
