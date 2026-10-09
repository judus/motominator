import {
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react-native";
import { authenticatedApi } from "../src/auth/client";
import { MotorcycleListScreen } from "../src/garage/garage-screen";
import {
  MotorcycleOverviewScreen,
  MaintenanceListScreen,
  NewMotorcycleScreen,
} from "../src/garage/motorcycle-screens";
import React from "react";

const mockRouter = {
  push: jest.fn(),
  replace: jest.fn(),
  dismissTo: jest.fn(),
};
const mockParams = { motorcycleId: "1" };
jest.mock("expo-router", () => ({
  get router() {
    return mockRouter;
  },
  useLocalSearchParams: () => mockParams,
  useFocusEffect: () => {},
  Link: ({
    children,
    href,
  }: {
    children: React.ReactElement<{ onPress?: () => void }>;
    href: string;
  }) => {
    const react = jest.requireActual<typeof React>("react");
    return react.cloneElement(children, {
      onPress: () => mockRouter.push(href),
    });
  },
}));
const mockRefresh = jest.fn();
const mockAuth = {
  user: { id: 1, email_verified_at: "2026-01-01" as string | null },
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
const bike = {
  id: 1,
  make: "Honda",
  model: "CB500X",
  year: 2022,
  nickname: null,
  odometer_km: 12000,
};
const page = (data: unknown[]) => ({
  data,
  meta: { current_page: 1, last_page: 1 },
});
beforeEach(() => {
  jest.mocked(authenticatedApi).mockReset();
  jest.clearAllMocks();
  mockAuth.user.email_verified_at = "2026-01-01";
});

it("loads the garage, opens a motorcycle and shows its maintenance", async () => {
  jest.mocked(authenticatedApi).mockImplementation(async (path) =>
    path === "/api/v1/motorcycles/1"
      ? { data: bike }
      : path.includes("maintenance-records")
        ? page([
            {
              id: 2,
              title: "Oil change",
              performed_on: "2025-06-01",
              odometer_km: 10000,
              notes: "Owner entry",
              cost_amount: "75.20",
              currency: "CHF",
            },
          ])
        : page([bike]),
  );
  const list = await render(<MotorcycleListScreen />);
  await fireEvent.press(
    await screen.findByRole("button", { name: "Honda CB500X (2022)" }),
  );
  expect(mockRouter.push).toHaveBeenCalledWith("/garage/motorcycles/1");
  await list.unmount();
  const overview = await render(<MotorcycleOverviewScreen />);
  await screen.findByText("Current mileage");
  expect(screen.queryByText("Oil change")).not.toBeOnTheScreen();
  await fireEvent.press(
    screen.getByRole("button", { name: "Maintenance history" }),
  );
  expect(mockRouter.push).toHaveBeenCalledWith(
    "/garage/motorcycles/1/maintenance",
  );
  await overview.unmount();
  await render(<MaintenanceListScreen />);
  expect(await screen.findByText("Oil change")).toBeOnTheScreen();
  expect(screen.getByText("75.20 CHF")).toBeOnTheScreen();
});
it("preserves a failed draft and saves on retry", async () => {
  let saves = 0;
  jest.mocked(authenticatedApi).mockImplementation(async (_path, method) => {
    if (method === "POST") {
      if (++saves === 1) throw new Error("Connection lost.");
      return { data: bike };
    }
    return page([]);
  });
  await render(<NewMotorcycleScreen />);
  await fireEvent.changeText(screen.getByTestId("garage-make"), "Honda");
  await fireEvent.changeText(screen.getByTestId("garage-model"), "CB500X");
  await fireEvent.changeText(screen.getByTestId("garage-year"), "2022");
  await fireEvent.press(screen.getByRole("button", { name: "Save" }));
  await screen.findByText("Connection lost.");
  expect(screen.getByTestId("garage-make")).toHaveProp("value", "Honda");
  await fireEvent.press(screen.getByRole("button", { name: "Save" }));
  await waitFor(() =>
    expect(mockRouter.replace).toHaveBeenCalledWith("/garage/motorcycles/1"),
  );
  expect(authenticatedApi).toHaveBeenCalledWith(
    "/api/v1/motorcycles",
    "POST",
    expect.objectContaining({ make: "Honda", year: "2022" }),
  );
});
it("offers load retry and prevents writes by unverified accounts", async () => {
  mockAuth.user.email_verified_at = null;
  jest
    .mocked(authenticatedApi)
    .mockRejectedValueOnce(new Error("Offline."))
    .mockResolvedValueOnce(page([]));
  await render(<MotorcycleListScreen />);
  await screen.findByText("Offline.");
  expect(screen.getByRole("button", { name: "Add motorcycle" })).toBeDisabled();
  await fireEvent.press(screen.getByRole("button", { name: "Retry garage" }));
  expect(
    await screen.findByText("No motorcycles yet. Add your first motorcycle."),
  ).toBeOnTheScreen();
});
