import { fireEvent, render, screen } from "@testing-library/react-native";
import { authenticatedApi } from "../src/auth/client";
import { AiSettings } from "../src/ai/ai-settings";

const mockRefresh = jest.fn();
const mockAuth = {
  user: { email_verified_at: "2026-10-08" as string | null },
  refresh: mockRefresh,
};
jest.mock("../src/auth/auth-context", () => ({ useAuth: () => mockAuth }));
jest.mock("../src/auth/client", () => ({
  authenticatedApi: jest.fn(),
  AuthError: class extends Error {
    status = 0;
  },
}));
jest.mock("tamagui", () => jest.requireActual("../test/tamagui-mock"));
const path = "/api/v1/ai/settings";
const providers = [
  { id: "openai", label: "OpenAI" },
  { id: "anthropic", label: "Anthropic" },
];
const empty = { data: null, providers };
const saved = {
  data: { provider: "openai", model: "test-model", key_hint: "••••1234" },
  providers,
};
beforeEach(() => {
  jest.mocked(authenticatedApi).mockReset();
  mockAuth.user.email_verified_at = "2026-10-08";
});

it("keeps a failed draft, retries with the same key and clears it after saving", async () => {
  let attempts = 0;
  jest.mocked(authenticatedApi).mockImplementation(async (_path, method) => {
    if (method === "PUT") {
      if (++attempts === 1) throw new Error("Connection lost.");
      return saved;
    }
    return empty;
  });
  await render(<AiSettings />);
  await screen.findByText("No AI key configured.");
  await fireEvent.changeText(screen.getByTestId("ai-model"), "test-model");
  await fireEvent.changeText(
    screen.getByTestId("ai-key"),
    "test-owner-key-1234",
  );
  expect(screen.getByTestId("ai-key").props.secureTextEntry).toBe(true);
  const input = screen.getByTestId("ai-key");
  await fireEvent.press(
    screen.getByRole("button", { name: "Save AI settings" }),
  );
  await screen.findByText("Connection lost.");
  await fireEvent.press(
    screen.getByRole("button", { name: "Save AI settings" }),
  );
  await screen.findByText("AI settings saved.");
  expect(authenticatedApi).toHaveBeenLastCalledWith(path, "PUT", {
    provider: "openai",
    model: "test-model",
    api_key: "test-owner-key-1234",
  });
  expect(screen.getByTestId("ai-key")).not.toBe(input);
  expect(
    screen.getByRole("button", { name: "Test AI connection" }),
  ).toBeEnabled();
});

it("clears provider-specific drafts and requires saving before connection tests", async () => {
  jest.mocked(authenticatedApi).mockResolvedValue(saved);
  await render(<AiSettings />);
  await screen.findByText("Saved key: ••••1234");
  await fireEvent.changeText(
    screen.getByTestId("ai-key"),
    "draft-openai-key-1234",
  );
  await fireEvent.press(screen.getByTestId("ai-provider-anthropic"));
  expect(screen.getByTestId("ai-model").props.value).toBe("");
  expect(
    screen.getByRole("button", { name: "Save AI settings" }),
  ).toBeDisabled();
  expect(
    screen.getByRole("button", { name: "Test AI connection" }),
  ).toBeDisabled();
  await fireEvent.changeText(screen.getByTestId("ai-model"), "another-model");
  expect(
    screen.getByRole("button", { name: "Save AI settings" }),
  ).toBeDisabled();
  await fireEvent.changeText(
    screen.getByTestId("ai-key"),
    "new-anthropic-key-1234",
  );
  expect(
    screen.getByRole("button", { name: "Save AI settings" }),
  ).toBeEnabled();
});

it("shows connection failures, retries and removes the key", async () => {
  let tests = 0;
  jest.mocked(authenticatedApi).mockImplementation(async (url, method) => {
    if (url === `${path}/test`) {
      if (++tests === 1) throw new Error("Connection failed.");
      return { message: "Connection successful." };
    }
    return method === "DELETE" ? empty : saved;
  });
  await render(<AiSettings />);
  await screen.findByText("Saved key: ••••1234");
  await fireEvent.press(
    screen.getByRole("button", { name: "Test AI connection" }),
  );
  await screen.findByText("Connection failed.");
  await fireEvent.press(
    screen.getByRole("button", { name: "Test AI connection" }),
  );
  await screen.findByText("Connection successful.");
  expect(authenticatedApi).toHaveBeenCalledWith(`${path}/test`, "POST");
  await fireEvent.press(screen.getByRole("button", { name: "Remove AI key" }));
  await screen.findByText("AI key removed.");
  expect(screen.getByText("No AI key configured.")).toBeOnTheScreen();
  expect(
    screen.queryByRole("button", { name: "Test AI connection" }),
  ).toBeNull();
});

it("retries an initial load failure", async () => {
  jest
    .mocked(authenticatedApi)
    .mockRejectedValueOnce(new Error("Offline"))
    .mockResolvedValue(empty);
  await render(<AiSettings />);
  await screen.findByText("Unable to load AI settings. Please retry.");
  expect(screen.queryByRole("button", { name: "Save AI settings" })).toBeNull();
  await fireEvent.press(
    screen.getByRole("button", { name: "Retry AI settings" }),
  );
  expect(await screen.findByText("No AI key configured.")).toBeOnTheScreen();
});

it("keeps key removal available to an unverified user", async () => {
  mockAuth.user.email_verified_at = null;
  jest.mocked(authenticatedApi).mockResolvedValue(saved);
  await render(<AiSettings />);
  await screen.findByText("Saved key: ••••1234");
  expect(
    screen.getByRole("button", { name: "Save AI settings" }),
  ).toBeDisabled();
  expect(
    screen.getByRole("button", { name: "Test AI connection" }),
  ).toBeDisabled();
  expect(screen.getByRole("button", { name: "Remove AI key" })).toBeEnabled();
});
