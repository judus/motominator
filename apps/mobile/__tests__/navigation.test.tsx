import {
  act,
  fireEvent,
  renderRouter,
  screen,
  waitFor,
} from "expo-router/testing-library";
import { Stack } from "expo-router/stack";
import { router } from "expo-router";
import { CopilotProvider } from "@motominator/client/react";
import { useClient } from "../src/client";
import { authenticatedApi } from "../src/auth/client";

jest.mock("tamagui", () => jest.requireActual("../test/tamagui-mock"));
const mockRefresh = jest.fn();
jest.mock("../src/auth/auth-context", () => ({
  useAuth: () => ({
    user: { id: 1, name: "Rider", email_verified_at: "2026-01-01" },
    refresh: mockRefresh,
  }),
}));
jest.mock("../src/auth/client", () => ({
  authenticatedApi: jest.fn(),
  AuthError: class extends Error {},
}));

it("opens a motorcycle deep link and returns to the domain root and Home", async () => {
  jest.mocked(authenticatedApi).mockResolvedValue({
    data: {
      id: 1,
      make: "Honda",
      model: "CB500X",
      year: 2022,
      odometer_km: 12000,
    },
  });
  // SDK 57 attaches route helpers to the render promise, not its resolved result.
  const routes = renderRouter(
    {
      appDir: "src/app",
      overrides: {
        _layout: function TestLayout() {
          const client = useClient();
          return (
            <CopilotProvider client={client}>
              <Stack screenOptions={{ headerShown: false }} />
            </CopilotProvider>
          );
        },
      },
    },
    { initialUrl: "/garage/motorcycles/1" },
  );
  await routes;
  await screen.findByText("Honda CB500X (2022)");
  expect(routes.getPathname()).toBe("/garage/motorcycles/1");
  expect(router.canGoBack()).toBe(true);
  await act(() => router.back());
  await waitFor(() => expect(routes.getPathname()).toBe("/garage"));
  await act(() => router.push("/garage/motorcycles/1"));
  await screen.findByText("Honda CB500X (2022)");
  await fireEvent.press(screen.getByRole("button", { name: "Garage" }));
  await waitFor(() => expect(routes.getPathname()).toBe("/garage"));
  await screen.findByText("Your garage");
  await act(() => router.navigate("/"));
  await screen.findByText("Welcome, Rider");
  expect(routes.getPathname()).toBe("/");
});

it("restores the focused conversation when returning through a retained native stack", async () => {
  jest.mocked(authenticatedApi).mockImplementation(async (path) => ({
    data: path.includes("/messages")
      ? [
          {
            id: path,
            role: "assistant",
            content: path.includes("chat-one") ? "First chat" : "Second chat",
            status: "completed",
            created_at: "2026-10-10",
          },
        ]
      : [],
    meta: { current_page: 1, last_page: 1 },
  }));
  const routes = renderRouter(
    {
      appDir: "src/app",
      overrides: {
        _layout: function TestLayout() {
          const client = useClient();
          return (
            <CopilotProvider client={client}>
              <Stack screenOptions={{ headerShown: false }} />
            </CopilotProvider>
          );
        },
      },
    },
    { initialUrl: "/copilot/chat-one" },
  );
  await routes;
  await screen.findByText("First chat");
  await act(() => router.push("/copilot/chat-two"));
  await screen.findByText("Second chat");
  await act(() => router.back());
  await waitFor(() => expect(routes.getPathname()).toBe("/copilot/chat-one"));
  await screen.findByText("First chat");
  await waitFor(() => expect(screen.getByLabelText("Message")).toBeEnabled());
});
