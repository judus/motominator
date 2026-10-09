import { randomUUID } from "node:crypto";
import { spawn, spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";

function port(value) {
  if (!/^\d+$/.test(value) || Number(value) < 1024 || Number(value) > 65535) {
    throw new Error("Invalid E2E server port.");
  }
  return value;
}

const apiPort = port(process.env.E2E_API_PORT ?? "8001");
const webPort = port(process.env.E2E_WEB_PORT ?? "5179");
const name = `motominator-e2e-${randomUUID()}`;
const apiUrl = `http://localhost:${apiPort}`;
const webUrl = `http://localhost:${webPort}`;
const child = spawn(
  "./vendor/bin/sail",
  [
    "-f",
    "compose.yaml",
    "run",
    "--rm",
    "--no-deps",
    "--name",
    name,
    "-p",
    `127.0.0.1:${apiPort}:8080`,
    "-e",
    "APP_ENV=e2e",
    "-e",
    "DB_CONNECTION=sqlite",
    "-e",
    "DB_DATABASE=/tmp/motominator-e2e.sqlite",
    "-e",
    "DB_URL=",
    "-e",
    "QUEUE_CONNECTION=sync",
    "-e",
    `APP_URL=${apiUrl}`,
    "-e",
    `FRONTEND_URL=${webUrl}`,
    "-e",
    `CORS_ALLOWED_ORIGINS=${webUrl}`,
    "-e",
    `SANCTUM_STATEFUL_DOMAINS=localhost:${webPort}`,
    "-e",
    "AUTH_REGISTRATION_ENABLED=true",
    "-e",
    "MAIL_MAILER=array",
    "laravel.test",
    "sh",
    "-c",
    "touch /tmp/motominator-e2e.sqlite && php artisan migrate --force --no-interaction && exec php artisan serve --host=0.0.0.0 --port=8080 --no-reload",
  ],
  {
    cwd: fileURLToPath(new URL("../../server", import.meta.url)),
    stdio: "inherit",
  },
);

let cleaned = false;
function cleanup() {
  if (cleaned) return;
  cleaned = true;
  // Stop only our uniquely named disposable server, even if the Sail shell died.
  spawnSync("docker", ["rm", "--force", name], {
    stdio: "ignore",
    timeout: 10000,
  });
}

process.on("exit", cleanup);
for (const signal of ["SIGTERM", "SIGINT", "SIGHUP"]) {
  process.on(signal, () => {
    cleanup();
    child.kill(signal);
    process.exit(0);
  });
}
child.on("error", (error) => {
  console.error(error.message);
  process.exit(1);
});
child.on("exit", (code) => process.exit(code ?? 1));
