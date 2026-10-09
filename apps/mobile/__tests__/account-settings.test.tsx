import {
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react-native";
import { authenticatedApi } from "../src/auth/client";
import ProfileScreen from "../src/account/profile-screen";
import PasswordScreen from "../src/account/password-screen";
import TwoFactorScreen from "../src/account/two-factor-screen";
import DevicesScreen from "../src/account/devices-screen";
import SocialScreen from "../src/account/social-screen";
import AccountScreen from "../src/account-screen";
import { api, linkSocialAccount } from "../src/auth/client";

jest.mock("tamagui", () => jest.requireActual("../test/tamagui-mock"));
jest.mock("expo-router", () => ({
  Link: ({ children }: { children: unknown }) => children,
}));
jest.mock("../src/auth/client", () => ({
  authenticatedApi: jest.fn(),
  api: jest.fn(),
  linkSocialAccount: jest.fn(),
  AuthError: class extends Error {},
}));
const mockRefresh = jest.fn(async () => {});
const mockUser = {
  id: 1,
  name: "Rider",
  email: "rider@example.test",
  email_verified_at: "2026-01-01",
  two_factor_enabled: false,
  two_factor_pending: false,
  providers: [] as string[],
};
jest.mock("../src/auth/auth-context", () => ({
  useAuth: () => ({
    user: mockUser,
    refresh: mockRefresh,
    logout: jest.fn(),
    error: "",
  }),
}));
beforeEach(() => {
  jest.clearAllMocks();
  jest.mocked(authenticatedApi).mockReset();
  mockRefresh.mockReset();
  mockRefresh.mockResolvedValue();
  mockUser.two_factor_enabled = false;
  mockUser.two_factor_pending = false;
  mockUser.providers = [];
});

it("offers every account section from the overview", async () => {
  await render(<AccountScreen />);
  for (const name of [
    "Profile",
    "Password",
    "Two-factor authentication",
    "Social accounts",
    "Signed-in devices",
    "AI settings",
  ])
    expect(screen.getByRole("button", { name })).toBeOnTheScreen();
});
it("preserves a failed profile draft and refreshes the account after retry", async () => {
  jest
    .mocked(authenticatedApi)
    .mockRejectedValueOnce(new Error("Offline."))
    .mockResolvedValueOnce({});
  await render(<ProfileScreen />);
  await fireEvent.changeText(screen.getByLabelText("Name"), "Updated Rider");
  await fireEvent.press(screen.getByRole("button", { name: "Save profile" }));
  await screen.findByText("Offline.");
  expect(screen.getByLabelText("Name")).toHaveProp("value", "Updated Rider");
  await fireEvent.press(screen.getByRole("button", { name: "Save profile" }));
  await screen.findByText("Account updated.");
  expect(authenticatedApi).toHaveBeenCalledWith(
    "/api/v1/account/profile",
    "PUT",
    { name: "Updated Rider", email: mockUser.email },
  );
  expect(mockRefresh).toHaveBeenCalledTimes(1);
});
it("clears password fields on success and explains device sign-out", async () => {
  jest.mocked(authenticatedApi).mockResolvedValue({});
  await render(<PasswordScreen />);
  await fireEvent.changeText(
    screen.getByLabelText("Current password"),
    "old-password",
  );
  await fireEvent.changeText(
    screen.getByLabelText("New password"),
    "replacement-password",
  );
  await fireEvent.changeText(
    screen.getByLabelText("Confirm new password"),
    "replacement-password",
  );
  await fireEvent.press(
    screen.getByRole("button", { name: "Change password" }),
  );
  await screen.findByText("Password changed.");
  expect(screen.getByLabelText("New password")).toHaveProp("value", "");
  expect(mockRefresh).toHaveBeenCalledTimes(1);
  expect(authenticatedApi).toHaveBeenCalledWith(
    "/api/v1/account/password",
    "PUT",
    expect.objectContaining({
      current_password: "old-password",
      password: "replacement-password",
    }),
  );
});
it("shows setup material and confirms two-factor authentication", async () => {
  jest
    .mocked(authenticatedApi)
    .mockResolvedValueOnce({ secret: "TEST-SECRET", codes: ["recovery-test"] })
    .mockResolvedValueOnce({ secret: null, codes: [] });
  mockRefresh.mockImplementation(async () => {
    mockUser.two_factor_pending = true;
  });
  await render(<TwoFactorScreen />);
  await fireEvent.changeText(
    screen.getByLabelText("Confirm password"),
    "password",
  );
  await fireEvent.press(
    screen.getByRole("button", { name: "Set up authenticator" }),
  );
  await screen.findByText("TEST-SECRET");
  await screen.findByText("recovery-test");
  await fireEvent.changeText(
    screen.getByLabelText("Confirm password"),
    "password",
  );
  await fireEvent.changeText(
    screen.getByLabelText("Authenticator code"),
    "123456",
  );
  await fireEvent.press(
    screen.getByRole("button", { name: "Confirm authenticator" }),
  );
  await waitFor(() =>
    expect(screen.queryByText("TEST-SECRET")).not.toBeOnTheScreen(),
  );
  expect(authenticatedApi).toHaveBeenCalledWith(
    "/api/v1/account/two-factor",
    "POST",
    { password: "password", operation: "confirm", code: "123456" },
  );
});
it("revokes this device and refreshes authentication without a follow-up token request", async () => {
  jest
    .mocked(authenticatedApi)
    .mockResolvedValueOnce([
      { id: 2, name: "Test phone", expires_at: null, last_used_at: null },
    ])
    .mockResolvedValueOnce({ current_device: true });
  await render(<DevicesScreen />);
  await fireEvent.press(screen.getByRole("button", { name: "Load devices" }));
  await screen.findByText("Test phone");
  await fireEvent.changeText(
    screen.getByLabelText("Password to revoke device"),
    "password",
  );
  await fireEvent.press(
    screen.getByRole("button", { name: "Revoke Test phone" }),
  );
  await screen.findByText("Device revoked.");
  expect(authenticatedApi).toHaveBeenCalledTimes(2);
  expect(mockRefresh).toHaveBeenCalledTimes(1);
});
it("shows unconfigured social providers and can unlink a previously linked provider", async () => {
  mockUser.providers = ["google"];
  jest.mocked(api).mockResolvedValue({ providers: [] });
  jest.mocked(authenticatedApi).mockResolvedValue({});
  await render(<SocialScreen />);
  await screen.findByText("Google");
  await fireEvent.changeText(
    screen.getByLabelText("Confirm password"),
    "password",
  );
  await fireEvent.press(screen.getByRole("button", { name: "Unlink google" }));
  await screen.findByText("Account updated.");
  expect(authenticatedApi).toHaveBeenCalledWith(
    "/api/v1/account/social/google",
    "DELETE",
    { password: "password" },
  );
  expect(linkSocialAccount).not.toHaveBeenCalled();
});
it("suppresses double submissions and ignores responses after leaving the editor", async () => {
  let finish: (value: unknown) => void = () => {};
  jest.mocked(authenticatedApi).mockReturnValue(
    new Promise((resolve) => {
      finish = resolve;
    }),
  );
  const view = await render(<ProfileScreen />);
  await fireEvent.press(screen.getByRole("button", { name: "Save profile" }));
  expect(screen.getByRole("button", { name: "Saving…" })).toBeDisabled();
  await view.unmount();
  finish({});
  await waitFor(() => expect(authenticatedApi).toHaveBeenCalledTimes(1));
  expect(mockRefresh).not.toHaveBeenCalled();
});

it("requires email-change confirmation and clears the password after success", async () => {
  jest
    .mocked(authenticatedApi)
    .mockRejectedValueOnce(new Error("Wrong password."))
    .mockResolvedValueOnce({});
  await render(<ProfileScreen />);
  expect(
    screen.queryByLabelText("Current password to change email"),
  ).not.toBeOnTheScreen();
  await fireEvent.changeText(
    screen.getByLabelText("Email"),
    "new@example.test",
  );
  await fireEvent.changeText(
    screen.getByLabelText("Current password to change email"),
    "password",
  );
  await fireEvent.press(screen.getByRole("button", { name: "Save profile" }));
  await screen.findByText("Wrong password.");
  expect(screen.getByLabelText("Current password to change email")).toHaveProp(
    "value",
    "password",
  );
  await fireEvent.press(screen.getByRole("button", { name: "Save profile" }));
  await screen.findByText("Account updated.");
  expect(screen.getByLabelText("Current password to change email")).toHaveProp(
    "value",
    "",
  );
  expect(authenticatedApi).toHaveBeenLastCalledWith(
    "/api/v1/account/profile",
    "PUT",
    { name: "Rider", email: "new@example.test", current_password: "password" },
  );
});
