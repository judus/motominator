import { beforeEach, expect, it, vi } from "vitest";
import { fireEvent, render, screen, within } from "./test/render";
import App from "./App";
import { ApiError, request } from "./api";

vi.mock("./api", async (original) => ({
  ...(await original<typeof import("./api")>()),
  request: vi.fn(),
}));
beforeEach(() => {
  window.history.replaceState({}, "", "/garage");
  vi.mocked(request).mockReset();
});
it("shows the authenticated navigation and removes scaffold controls", async () => {
  vi.mocked(request).mockImplementation(async (path) => {
    if (path === "/api/v1/user")
      return { id: 1, name: "Rider", email_verified_at: "2026-10-08" };
    if (path === "/api/v1/auth/config")
      return { registration_enabled: true, providers: [] };
    return { data: [], meta: { current_page: 1, last_page: 1 } };
  });
  render(<App />);
  await screen.findByRole("heading", { name: "Your garage" });
  const nav = within(
    screen.getByRole("navigation", { name: "Main navigation" }),
  );
  expect(nav.getByRole("link", { name: "Garage" })).toHaveAttribute(
    "aria-current",
    "page",
  );
  expect(nav.getByRole("link", { name: "Account" })).toHaveAttribute(
    "href",
    "/account",
  );
  expect(nav.getByRole("button", { name: "Sign out" })).toBeInTheDocument();
  expect(nav.queryByRole("link", { name: "Sign in" })).not.toBeInTheDocument();
  expect(
    nav.queryByRole("link", { name: "Create account" }),
  ).not.toBeInTheDocument();
  expect(
    screen.queryByRole("button", { name: "Check server" }),
  ).not.toBeInTheDocument();
});
it("keeps a garage destination after signing in there", async () => {
  let authenticated = false;
  vi.mocked(request).mockImplementation(async (path) => {
    if (path === "/api/v1/user") {
      if (!authenticated) throw new ApiError(401);
      return { id: 1, name: "Rider", email_verified_at: "2026-10-08" };
    }
    if (path === "/api/v1/auth/config")
      return { registration_enabled: false, providers: [] };
    if (path === "/login") {
      authenticated = true;
      return {};
    }
    return { data: [], meta: { current_page: 1, last_page: 1 } };
  });
  render(<App />);
  await screen.findByRole("heading", { name: "Sign in" });
  fireEvent.submit(
    screen.getByRole("button", { name: "Sign in" }).closest("form")!,
  );
  expect(
    await screen.findByRole("heading", { name: "Your garage" }),
  ).toBeInTheDocument();
  expect(
    screen.queryByRole("heading", { name: "Account settings" }),
  ).not.toBeInTheDocument();
});
