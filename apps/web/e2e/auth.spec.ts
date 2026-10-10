import { createHmac } from "node:crypto";
import { expect, test } from "@playwright/test";

test("real cookie session, CSRF, account settings and two-factor recovery", async ({
  page,
}) => {
  const email = `browser-${Date.now()}@example.test`;
  await page.goto("/register");
  await page.getByLabel(/^Name\s*\*?$/).fill("Browser Rider");
  await page.getByLabel(/^Email\s*\*?$/).fill(email);
  await page.getByLabel(/^Password\s*\*?$/).fill("browser-password-123");
  await page
    .getByLabel(/^Confirm password\s*\*?$/)
    .fill("browser-password-123");
  await page
    .getByRole("button", { name: "Create account", exact: true })
    .click();
  await expect(page.getByText("Welcome, Browser Rider")).toBeVisible();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.getByRole("button", { name: "Toggle navigation" }).click();
  await page.getByRole("link", { name: "Garage", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Your garage" }),
  ).toBeVisible();
  await page.getByRole("link", { name: "Your motorcycles" }).click();
  await expect(
    page.getByRole("heading", { name: "Your motorcycles" }),
  ).toBeVisible();
  await expect(
    page.getByText("No motorcycles yet. Add your first motorcycle."),
  ).toBeVisible();
  await page.goBack();
  await expect(
    page.getByRole("heading", { name: "Your garage" }),
  ).toBeVisible();
  await page.getByRole("link", { name: "Motominator", exact: true }).click();
  await expect(page.getByText("Welcome, Browser Rider")).toBeVisible();
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/account/ai");
  await expect(
    page.getByRole("heading", { name: "AI settings", exact: true }),
  ).toBeVisible();
  await expect(page.getByText("No AI key configured.")).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Save AI settings" }),
  ).toBeDisabled();
  await page.goto("/account");
  await expect(
    page.getByText("Email not verified", { exact: true }),
  ).toBeVisible();
  const cookies = await page.context().cookies();
  expect(
    cookies.some((cookie) => cookie.httpOnly && cookie.name !== "XSRF-TOKEN"),
  ).toBeTruthy();
  await page.goto("/");
  await page.reload();
  await expect(page.getByText("Welcome, Browser Rider")).toBeVisible();
  const csrfFailure = await page.request.put(
    `http://localhost:${process.env.E2E_API_PORT ?? "8001"}/api/v1/account/profile`,
    {
      headers: {
        Accept: "application/json",
        Origin: new URL(page.url()).origin,
      },
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
  await page.getByLabel(/^Email\s*\*?$/).fill(email);
  await page.getByLabel(/^Password\s*\*?$/).fill("wrong-password");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(
    page
      .getByText("These credentials do not match our records.", {
        exact: false,
      })
      .first(),
  ).toBeVisible();
  await page.getByLabel(/^Password\s*\*?$/).fill("browser-password-123");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page.getByText("Welcome, Browser Rider")).toBeVisible();
  await page.goto("/account/profile");
  await page.getByLabel(/^Name\s*\*?$/).fill("Updated Rider");
  await page.getByRole("button", { name: "Save profile" }).click();
  await expect(
    page.getByText("Account updated.", { exact: true }),
  ).toBeVisible();
  await page.getByLabel(/^Email\s*\*?$/).fill(`changed-${email}`);
  await page
    .getByLabel(/^Current password to change email/)
    .fill("wrong-password");
  await page.getByRole("button", { name: "Save profile" }).click();
  await expect(
    page
      .getByText("The provided password is incorrect.", { exact: false })
      .first(),
  ).toBeVisible();
  await page.reload();
  await expect(page.getByLabel(/^Email\s*\*?$/)).toHaveValue(email);
  await page.goto("/");
  await expect(page.getByText("Welcome, Updated Rider")).toBeVisible();
  await page.goto("/account/password");
  await page
    .getByLabel(/^Current password\s*\*?$/)
    .fill("browser-password-123");
  await page
    .getByLabel(/^New password\s*\*?$/)
    .fill("replacement-password-123");
  await page
    .getByLabel(/^Confirm new password\s*\*?$/)
    .fill("replacement-password-123");
  await page.getByRole("button", { name: "Change password" }).click();
  await expect(
    page.getByText("Password changed.", { exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Change password" }),
  ).toBeEnabled();
  await page.goto("/account/two-factor");
  await page
    .getByLabel(/^Confirm password\s*\*?$/)
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
  await page.getByLabel(/^Password\s*\*?$/).fill("replacement-password-123");
  await page.getByLabel(/^Authenticator code\s*\*?$/).fill(code);
  await page.getByRole("button", { name: "Confirm authenticator" }).click();
  await expect(page.getByText("Enabled", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Sign out", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Sign in", exact: true }),
  ).toBeVisible();
  await page.getByLabel(/^Email\s*\*?$/).fill(email);
  await page.getByLabel(/^Password\s*\*?$/).fill("replacement-password-123");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Two-factor authentication" }),
  ).toBeVisible();
  await page.getByLabel("Or recovery code").fill(recovery);
  await page.getByRole("button", { name: "Verify code" }).click();
  await expect(page.getByText("Welcome, Updated Rider")).toBeVisible();
});
