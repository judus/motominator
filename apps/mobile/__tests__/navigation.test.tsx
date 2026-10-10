import {
  act,
  fireEvent,
  renderRouter,
  screen,
  waitFor,
} from "expo-router/testing-library";
import { Stack } from "expo-router/stack";
import { router } from "expo-router";
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
        _layout: () => <Stack screenOptions={{ headerShown: false }} />,
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
