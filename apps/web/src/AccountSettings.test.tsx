import { act, fireEvent, render, screen, waitFor } from "./test/render";
import { beforeEach, expect, it, vi } from "vitest";
import { AccountSettings } from "./AccountSettings";
import { request, type User } from "./api";

vi.mock("./api", async (original) => ({
  ...(await original<typeof import("./api")>()),
  request: vi.fn(),
}));
const user: User = {
  id: 1,
  name: "Rider",
  email: "rider@example.test",
  email_verified_at: null,
  two_factor_enabled: false,
  two_factor_pending: false,
  providers: [],
};
beforeEach(() => vi.mocked(request).mockReset());
it("preserves failed password edits and clears credentials after success", async () => {
  vi.mocked(request)
    .mockRejectedValueOnce(new Error("Offline."))
    .mockResolvedValueOnce({});
  render(
    <AccountSettings
      section="password"
      user={user}
      refresh={vi.fn(async () => {})}
      providers={[]}
    />,
  );
  fireEvent.change(screen.getByLabelText(/^Current password\s*\*?$/), {
    target: { value: "old-password" },
  });
  fireEvent.change(screen.getByLabelText(/^New password\s*\*?$/), {
    target: { value: "replacement-password" },
  });
  fireEvent.change(screen.getByLabelText(/^Confirm new password\s*\*?$/), {
    target: { value: "replacement-password" },
  });
  fireEvent.click(screen.getByRole("button", { name: "Change password" }));
  await screen.findByText("Offline.");
  expect(screen.getByLabelText(/^New password\s*\*?$/)).toHaveValue(
    "replacement-password",
  );
  fireEvent.click(screen.getByRole("button", { name: "Change password" }));
  await screen.findByText("Password changed.");
  await waitFor(() =>
    expect(screen.getByLabelText(/^New password\s*\*?$/)).toHaveValue(""),
  );
});
it("shows and hides recovery material from the shared account operation", async () => {
  vi.mocked(request).mockResolvedValue({
    secret: "TEST-SECRET",
    codes: ["recovery-test"],
  });
  render(
    <AccountSettings
      section="two-factor"
      user={user}
      refresh={vi.fn(async () => {})}
      providers={[]}
    />,
  );
  fireEvent.change(screen.getByLabelText(/^Confirm password\s*\*?$/), {
    target: { value: "password" },
  });
  fireEvent.click(
    screen.getByRole("button", { name: "Update two-factor authentication" }),
  );
  await screen.findByText("TEST-SECRET");
  expect(screen.getByText("recovery-test")).toBeInTheDocument();
  fireEvent.click(screen.getByRole("button", { name: "Hide recovery codes" }));
  expect(screen.queryByText("TEST-SECRET")).not.toBeInTheDocument();
  expect(request).toHaveBeenCalledWith("/api/v1/account/two-factor", "POST", {
    password: "password",
    operation: "enable",
    code: undefined,
  });
});
it("ignores a late response after leaving an account screen", async () => {
  let finish: (value: unknown) => void = () => {};
  vi.mocked(request).mockReturnValue(
    new Promise((resolve) => {
      finish = resolve;
    }),
  );
  const refresh = vi.fn(async () => {});
  const view = render(
    <AccountSettings
      section="profile"
      user={user}
      refresh={refresh}
      providers={[]}
    />,
  );
  fireEvent.click(screen.getByRole("button", { name: "Save profile" }));
  expect(screen.getByRole("button", { name: "Save profile" })).toBeDisabled();
  view.unmount();
  await act(async () => finish({}));
  expect(refresh).not.toHaveBeenCalled();
});

it("confirms email changes while preserving failed drafts and clearing successful passwords", async () => {
  vi.mocked(request)
    .mockRejectedValueOnce(new Error("Wrong password."))
    .mockResolvedValueOnce({});
  render(
    <AccountSettings
      section="profile"
      user={user}
      refresh={vi.fn(async () => {})}
      providers={[]}
    />,
  );
  expect(
    screen.queryByLabelText(/^Current password to change email/),
  ).not.toBeInTheDocument();
  fireEvent.change(screen.getByLabelText(/^Email\s*\*?$/), {
    target: { value: "new@example.test" },
  });
  fireEvent.change(screen.getByLabelText(/^Current password to change email/), {
    target: { value: "password" },
  });
  fireEvent.click(screen.getByRole("button", { name: "Save profile" }));
  await screen.findByText("Wrong password.");
  expect(
    screen.getByLabelText(/^Current password to change email/),
  ).toHaveValue("password");
  fireEvent.click(screen.getByRole("button", { name: "Save profile" }));
  await screen.findByText("Account updated.");
  await waitFor(() =>
    expect(
      screen.getByLabelText(/^Current password to change email/),
    ).toHaveValue(""),
  );
  expect(request).toHaveBeenLastCalledWith("/api/v1/account/profile", "PUT", {
    name: "Rider",
    email: "new@example.test",
    current_password: "password",
  });
});
