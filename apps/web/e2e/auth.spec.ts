import { createHmac } from "node:crypto";
import { expect, test } from "@playwright/test";

test("real cookie session, CSRF, account settings and two-factor recovery", async ({
  page,
}) => {
  const email = `browser-${Date.now()}@example.test`;
  await page.goto("/register");
  await page.getByLabel("Name", { exact: true }).fill("Browser Rider");
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page
    .getByLabel("Password", { exact: true })
    .fill("browser-password-123");
  await page
    .getByLabel("Confirm password", { exact: true })
    .fill("browser-password-123");
  await page
    .getByRole("button", { name: "Create account", exact: true })
    .click();
  await expect(page.getByText("Welcome, Browser Rider")).toBeVisible();
  await expect(
    page.getByText("Email not verified", { exact: true }),
  ).toBeVisible();
  const cookies = await page.context().cookies();
  expect(
    cookies.some((cookie) => cookie.httpOnly && cookie.name !== "XSRF-TOKEN"),
  ).toBeTruthy();
  await page.reload();
  await expect(page.getByText("Welcome, Browser Rider")).toBeVisible();
  const csrfFailure = await page.request.put(
    "http://localhost:8001/user/profile-information",
    {
      headers: { Accept: "application/json" },
      data: { name: "Forged", email },
    },
  );
  expect(csrfFailure.status()).toBe(419);
  await page.getByRole("button", { name: "Sign out", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Sign in", exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Sign in", exact: true }),
  ).toBeVisible();
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page.getByLabel("Password", { exact: true }).fill("wrong-password");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(
    page
      .getByText("These credentials do not match our records.", {
        exact: false,
      })
      .first(),
  ).toBeVisible();
  await page
    .getByLabel("Password", { exact: true })
    .fill("browser-password-123");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page.getByText("Welcome, Browser Rider")).toBeVisible();
  await page.getByLabel("Name", { exact: true }).fill("Updated Rider");
  await page.getByRole("button", { name: "Save profile" }).click();
  await expect(page.getByText("Welcome, Updated Rider")).toBeVisible();
  await page
    .getByLabel("Current password", { exact: true })
    .fill("browser-password-123");
  await page
    .getByLabel("New password", { exact: true })
    .fill("replacement-password-123");
  await page
    .getByLabel("Confirm new password", { exact: true })
    .fill("replacement-password-123");
  await page.getByRole("button", { name: "Change password" }).click();
  await expect(
    page.getByRole("button", { name: "Change password" }),
  ).toBeEnabled();
  await page
    .getByLabel("Confirm password", { exact: true })
    .fill("replacement-password-123");
  await page
    .getByRole("button", { name: "Update two-factor authentication" })
    .click();
  await expect(page.getByText("Awaiting confirmation")).toBeVisible();
  const secret = await page
    .locator("p")
    .filter({ hasText: "Add this secret to your authenticator:" })
    .locator("code")
    .innerText();
  const bits = [...secret]
    .map((letter) =>
      "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567"
        .indexOf(letter)
        .toString(2)
        .padStart(5, "0"),
    )
    .join("");
  const key = Buffer.from(
    (bits.match(/.{8}/g) ?? []).map((byte) => Number.parseInt(byte, 2)),
  );
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
  const digest = createHmac("sha1", key).update(counter).digest();
  const offset = digest[19] & 15;
  const code = ((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000)
    .toString()
    .padStart(6, "0");
  const recovery = await page.locator("li code").first().innerText();
  await page
    .getByLabel("Password", { exact: true })
    .fill("replacement-password-123");
  await page.getByLabel("Authenticator code", { exact: true }).fill(code);
  await page.getByRole("button", { name: "Confirm authenticator" }).click();
  await expect(page.getByText("Enabled", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Sign out", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Sign in", exact: true }),
  ).toBeVisible();
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page
    .getByLabel("Password", { exact: true })
    .fill("replacement-password-123");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Two-factor authentication" }),
  ).toBeVisible();
  await page.getByLabel("Or recovery code").fill(recovery);
  await page.getByRole("button", { name: "Verify code" }).click();
  await expect(page.getByText("Welcome, Updated Rider")).toBeVisible();
});
