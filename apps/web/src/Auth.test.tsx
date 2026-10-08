import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import App from "./App";
import { ApiError, request } from "./api";

vi.mock("./api", async (original) => ({
  ...(await original<typeof import("./api")>()),
  request: vi.fn(),
}));
const user = {
  id: 1,
  name: "Rider",
  email: "rider@example.test",
  email_verified_at: null,
  two_factor_enabled: false,
  two_factor_pending: false,
};

beforeEach(() => {
  window.history.replaceState({}, "", "/login");
  vi.mocked(request).mockReset();
});

function backend(twoFactor = false) {
  let authenticated = false;
  vi.mocked(request).mockImplementation(async (path) => {
    if (path === "/api/v1/auth/config")
      return { registration_enabled: false, providers: [] };
    if (path === "/api/v1/user") {
      if (!authenticated) throw new ApiError(401);
      return user;
    }
    if (path === "/login") {
      authenticated = !twoFactor;
      return { two_factor: twoFactor };
    }
    if (path === "/two-factor-challenge") {
      authenticated = true;
      return {};
    }
    if (path === "/logout") {
      authenticated = false;
      return {};
    }
    return {};
  });
}

describe("browser authentication", () => {
  it("signs in, loads the account, and signs out", async () => {
    backend();
    render(<App />);
    await screen.findByRole("heading", { name: "Sign in" });
    fireEvent.change(screen.getByLabelText("Email"), {
      target: { value: user.email },
    });
    fireEvent.change(screen.getByLabelText("Password"), {
      target: { value: "password" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));
    expect(await screen.findByText("Welcome, Rider")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Sign out" }));
    expect(
      await screen.findByRole("heading", { name: "Sign in" }),
    ).toBeInTheDocument();
    expect(screen.queryByText("Welcome, Rider")).not.toBeInTheDocument();
  });
  it("requires the two-factor challenge before showing the account", async () => {
    backend(true);
    render(<App />);
    await screen.findByRole("heading", { name: "Sign in" });
    fireEvent.submit(
      screen.getByRole("button", { name: "Sign in" }).closest("form")!,
    );
    expect(
      await screen.findByRole("heading", { name: "Two-factor authentication" }),
    ).toBeInTheDocument();
    expect(screen.queryByText("Welcome, Rider")).not.toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Or recovery code"), {
      target: { value: "recovery-code" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Verify code" }));
    expect(await screen.findByText("Welcome, Rider")).toBeInTheDocument();
  });
  it("keeps registration closed", async () => {
    window.history.replaceState({}, "", "/register");
    backend();
    render(<App />);
    expect(
      await screen.findByText("Account registration is closed."),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Create account" }),
    ).not.toBeInTheDocument();
  });
  it("removes protected content after session expiry", async () => {
    backend();
    render(<App />);
    await screen.findByRole("heading", { name: "Sign in" });
    fireEvent.submit(
      screen.getByRole("button", { name: "Sign in" }).closest("form")!,
    );
    await screen.findByText("Welcome, Rider");
    fireEvent(window, new Event("auth-expired"));
    expect(
      await screen.findByText("Your session expired. Please sign in again."),
    ).toBeInTheDocument();
    expect(screen.queryByText("Welcome, Rider")).not.toBeInTheDocument();
  });
});

it("requests recovery without revealing whether the account exists", async () => {
  window.history.replaceState({}, "", "/forgot-password");
  backend();
  render(<App />);
  await screen.findByRole("heading", { name: "Forgot password?" });
  fireEvent.change(screen.getByLabelText("Email"), {
    target: { value: user.email },
  });
  fireEvent.click(screen.getByRole("button", { name: "Send reset link" }));
  expect(
    await screen.findByText(
      "If an account exists, a password reset link has been sent.",
    ),
  ).toBeInTheDocument();
  expect(request).toHaveBeenCalledWith("/forgot-password", "POST", {
    email: user.email,
  });
});
it("passes the reset link token and email to Fortify", async () => {
  window.history.replaceState(
    {},
    "",
    "/reset-password?token=reset-token&email=rider%40example.test",
  );
  backend();
  render(<App />);
  await screen.findByRole("heading", { name: "Reset password" });
  fireEvent.change(screen.getByLabelText("Password"), {
    target: { value: "replacement-password" },
  });
  fireEvent.change(screen.getByLabelText("Confirm password"), {
    target: { value: "replacement-password" },
  });
  fireEvent.click(screen.getByRole("button", { name: "Reset password" }));
  expect(
    await screen.findByText("Password reset. You can now sign in."),
  ).toBeInTheDocument();
  expect(request).toHaveBeenCalledWith(
    "/reset-password",
    "POST",
    expect.objectContaining({ token: "reset-token", email: user.email }),
  );
});
it("updates the profile and resends verification", async () => {
  backend();
  render(<App />);
  await screen.findByRole("heading", { name: "Sign in" });
  fireEvent.submit(
    screen.getByRole("button", { name: "Sign in" }).closest("form")!,
  );
  await screen.findByText("Welcome, Rider");
  fireEvent.change(screen.getByLabelText("Name"), {
    target: { value: "Updated Rider" },
  });
  fireEvent.click(screen.getByRole("button", { name: "Save profile" }));
  await screen.findByText("Account updated.");
  expect(request).toHaveBeenCalledWith("/user/profile-information", "PUT", {
    name: "Updated Rider",
    email: user.email,
  });
  fireEvent.click(
    screen.getByRole("button", { name: "Resend verification email" }),
  );
  expect(
    await screen.findByText("Verification email sent."),
  ).toBeInTheDocument();
});
