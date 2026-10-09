import { beforeEach, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "../test/render";
import { ApiError, request } from "../api";
import { AiSettings } from "./AiSettings";
import {
  aiSettingsPath,
  type AiSettings as Settings,
} from "@motominator/client";

vi.mock("../api", async (original) => ({
  ...(await original<typeof import("../api")>()),
  request: vi.fn(),
}));
const providers = [
  { id: "openai", label: "OpenAI" },
  { id: "anthropic", label: "Anthropic" },
  { id: "gemini", label: "Google Gemini" },
];
const empty: Settings = { data: null, providers };
const saved: Settings = {
  data: { provider: "openai", model: "test-model", key_hint: "••••1234" },
  providers,
};
beforeEach(() => vi.mocked(request).mockReset());

it("preserves a failed key draft, saves on retry and clears the plaintext field", async () => {
  let attempts = 0;
  vi.mocked(request).mockImplementation(async (_path, method) => {
    if (method === "PUT") {
      if (++attempts === 1)
        throw new ApiError(
          422,
          { api_key: ["Enter a valid key."] },
          "Enter a valid key.",
        );
      return saved;
    }
    return empty;
  });
  render(<AiSettings verified />);
  await screen.findByText("No AI key configured.");
  const key = screen.getByLabelText(/^API key/);
  fireEvent.change(screen.getByLabelText(/^AI model/), {
    target: { value: "test-model" },
  });
  fireEvent.change(key, { target: { value: "test-owner-key-1234" } });
  fireEvent.click(screen.getByRole("button", { name: "Save AI settings" }));
  await screen.findByRole("alert");
  expect(key).toHaveValue("test-owner-key-1234");
  fireEvent.click(screen.getByRole("button", { name: "Save AI settings" }));
  await screen.findByText("AI settings saved.");
  expect(screen.getByLabelText("Replace API key")).toHaveValue("");
  expect(screen.getByText("Saved key: ••••1234")).toBeInTheDocument();
  expect(request).toHaveBeenLastCalledWith(aiSettingsPath, "PUT", {
    provider: "openai",
    model: "test-model",
    api_key: "test-owner-key-1234",
  });
});

it("requires a new key when switching provider and tests only saved settings", async () => {
  vi.mocked(request).mockResolvedValue(saved);
  render(<AiSettings verified />);
  await screen.findByText("Saved key: ••••1234");
  fireEvent.change(screen.getByLabelText("Replace API key"), {
    target: { value: "draft-openai-key-1234" },
  });
  fireEvent.change(screen.getByLabelText("AI provider"), {
    target: { value: "anthropic" },
  });
  expect(screen.getByLabelText(/^API key/)).toHaveValue("");
  expect(screen.getByLabelText(/^API key/)).toBeRequired();
  expect(screen.getByLabelText(/^AI model/)).toHaveValue("");
  expect(
    screen.getByRole("button", { name: "Test AI connection" }),
  ).toBeDisabled();
  expect(
    screen.getByText("Save your changes before testing."),
  ).toBeInTheDocument();
});

it("reports a connection failure, retries and removes the saved key", async () => {
  let tests = 0;
  vi.mocked(request).mockImplementation(async (path, method) => {
    if (path === `${aiSettingsPath}/test`) {
      if (++tests === 1) throw new ApiError(422, {}, "Connection failed.");
      return { message: "Connection successful." };
    }
    return method === "DELETE" ? empty : saved;
  });
  render(<AiSettings verified />);
  await screen.findByText("Saved key: ••••1234");
  fireEvent.click(screen.getByRole("button", { name: "Test AI connection" }));
  await screen.findByText("Connection failed.");
  fireEvent.click(screen.getByRole("button", { name: "Test AI connection" }));
  await screen.findByText("Connection successful.");
  expect(request).toHaveBeenCalledWith(`${aiSettingsPath}/test`, "POST");
  fireEvent.click(screen.getByRole("button", { name: "Remove AI key" }));
  await screen.findByText("AI key removed.");
  expect(screen.getByText("No AI key configured.")).toBeInTheDocument();
  expect(
    screen.queryByRole("button", { name: "Test AI connection" }),
  ).not.toBeInTheDocument();
  expect(screen.getByLabelText(/^AI model/)).toHaveValue("");
});

it("recovers from an initial load failure without offering an empty form", async () => {
  vi.mocked(request)
    .mockRejectedValueOnce(new Error("Offline"))
    .mockResolvedValue(empty);
  render(<AiSettings verified />);
  await screen.findByText("Unable to load AI settings. Please retry.");
  expect(
    screen.queryByRole("button", { name: "Save AI settings" }),
  ).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole("button", { name: "Retry AI settings" }));
  await screen.findByText("No AI key configured.");
  expect(screen.queryByRole("alert")).not.toBeInTheDocument();
});

it("blocks saving and testing for unverified users while allowing key removal", async () => {
  vi.mocked(request).mockResolvedValue(saved);
  render(<AiSettings verified={false} />);
  await screen.findByText("Saved key: ••••1234");
  expect(
    screen.getByRole("button", { name: "Save AI settings" }),
  ).toBeDisabled();
  expect(
    screen.getByRole("button", { name: "Test AI connection" }),
  ).toBeDisabled();
  expect(screen.getByRole("button", { name: "Remove AI key" })).toBeEnabled();
});

it("prevents repeated submissions while a save is pending", async () => {
  let finish!: (value: Settings) => void;
  vi.mocked(request).mockImplementation(async (_path, method) =>
    method === "PUT"
      ? new Promise<Settings>((resolve) => {
          finish = resolve;
        })
      : saved,
  );
  render(<AiSettings verified />);
  await screen.findByText("Saved key: ••••1234");
  const button = screen.getByRole("button", { name: "Save AI settings" });
  fireEvent.click(button);
  fireEvent.click(button);
  expect(button).toBeDisabled();
  expect(
    vi.mocked(request).mock.calls.filter((call) => call[1] === "PUT"),
  ).toHaveLength(1);
  finish(saved);
  await waitFor(() => expect(button).toBeEnabled());
});
