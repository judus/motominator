import { act, fireEvent, render, screen, waitFor } from "../test/render";
import { beforeEach, expect, it, vi } from "vitest";
import { ApiError, request } from "../api";
import { MemoryRouter, Route, Routes } from "react-router";
import { Garage } from "./Garage";
import type { MaintenanceRecord, Motorcycle } from "@motominator/client";

vi.mock("../api", async (original) => ({
  ...(await original<typeof import("../api")>()),
  request: vi.fn(),
}));
const bike: Motorcycle = {
  id: 1,
  make: "Honda",
  model: "CB500X",
  year: 2022,
  nickname: null,
  odometer_km: 12000,
};
const entry: MaintenanceRecord = {
  id: 10,
  motorcycle_id: 1,
  title: "Oil change",
  performed_on: "2025-06-01",
  odometer_km: 15000,
  notes: "Changed filter",
  cost_amount: "0.00",
  currency: "CHF",
};
const page = <T,>(data: T[], current = 1, last = 1) => ({
  data,
  meta: { current_page: current, last_page: last },
});
beforeEach(() => {
  vi.mocked(request).mockReset();
});

it("distinguishes loading from empty and offers recovery after a failed load", async () => {
  vi.mocked(request)
    .mockRejectedValueOnce(new Error("Network unavailable."))
    .mockResolvedValueOnce(page([]));
  render(
    <MemoryRouter initialEntries={["/garage/motorcycles"]}>
      <Routes>
        <Route path="/garage/*" element={<Garage verified />} />
      </Routes>
    </MemoryRouter>,
  );
  expect(screen.getByText("Loading motorcycles…")).toBeInTheDocument();
  expect(
    screen.queryByText("No motorcycles yet. Add your first motorcycle."),
  ).not.toBeInTheDocument();
  await screen.findByText("Network unavailable.");
  fireEvent.click(screen.getByRole("button", { name: "Retry garage" }));
  expect(
    await screen.findByText("No motorcycles yet. Add your first motorcycle."),
  ).toBeInTheDocument();
});
it("keeps a failed motorcycle draft and saves it on retry", async () => {
  let saves = 0;
  vi.mocked(request).mockImplementation(async (_path, method) => {
    if (method === "POST") {
      if (++saves === 1)
        throw new ApiError(422, { year: ["Choose a valid year."] });
      return { data: bike };
    }
    if (_path === "/api/v1/motorcycles/1") return { data: bike };
    return page([]);
  });
  render(
    <MemoryRouter initialEntries={["/garage/motorcycles"]}>
      <Routes>
        <Route path="/garage/*" element={<Garage verified />} />
      </Routes>
    </MemoryRouter>,
  );
  await screen.findByText("No motorcycles yet. Add your first motorcycle.");
  fireEvent.click(screen.getByRole("link", { name: "Add motorcycle" }));
  fireEvent.change(screen.getByLabelText(/^Make\s*\*?$/), {
    target: { value: "Honda" },
  });
  fireEvent.change(screen.getByLabelText(/^Model\s*\*?$/), {
    target: { value: "CB500X" },
  });
  fireEvent.change(screen.getByLabelText(/^Year\s*\*?$/), {
    target: { value: "2022" },
  });
  fireEvent.click(screen.getByRole("button", { name: "Save" }));
  await screen.findByText("Choose a valid year.");
  expect(screen.getByLabelText(/^Make\s*\*?$/)).toHaveValue("Honda");
  fireEvent.click(screen.getByRole("button", { name: "Save" }));
  expect(
    await screen.findByRole("heading", { name: "Honda CB500X (2022)" }),
  ).toBeInTheDocument();
  expect(request).toHaveBeenCalledWith("/api/v1/motorcycles", "POST", {
    make: "Honda",
    model: "CB500X",
    year: "2022",
    nickname: null,
    odometer_km: "0",
  });
});
it("records maintenance, preserves decimal costs and refreshes the current mileage", async () => {
  let saved = false;
  vi.mocked(request).mockImplementation(async (path, method) => {
    if (method === "POST") {
      saved = true;
      return { data: entry };
    }
    if (path === "/api/v1/motorcycles/1")
      return { data: { ...bike, odometer_km: 15000 } };
    return path.includes("maintenance-records")
      ? page(saved ? [entry] : [])
      : page([bike]);
  });
  render(
    <MemoryRouter initialEntries={["/garage/motorcycles"]}>
      <Routes>
        <Route path="/garage/*" element={<Garage verified />} />
      </Routes>
    </MemoryRouter>,
  );
  fireEvent.click(
    await screen.findByRole("link", { name: "Honda CB500X (2022)" }),
  );
  fireEvent.click(
    await screen.findByRole("link", { name: "Record maintenance" }),
  );
  fireEvent.change(screen.getByLabelText(/^Date\s*\*?$/), {
    target: { value: "2025-06-01" },
  });
  fireEvent.change(screen.getByLabelText(/^Mileage \(km\)\s*\*?$/), {
    target: { value: "15000" },
  });
  fireEvent.change(screen.getByLabelText(/^Work performed\s*\*?$/), {
    target: { value: "Oil change" },
  });
  fireEvent.change(screen.getByLabelText(/^Cost \(up to 2 decimals\)\s*\*?$/), {
    target: { value: "0.00" },
  });
  fireEvent.change(
    screen.getByLabelText(/^Currency code \(e\.g\. CHF\)\s*\*?$/),
    {
      target: { value: "CHF" },
    },
  );
  fireEvent.click(screen.getByRole("button", { name: "Save" }));
  expect(await screen.findByText("0.00 CHF")).toBeInTheDocument();
  fireEvent.click(screen.getByRole("link", { name: /Back to motorcycle$/ }));
  expect(
    await screen.findByRole("heading", { name: "15,000 km" }),
  ).toBeInTheDocument();
  expect(request).toHaveBeenCalledWith(
    "/api/v1/motorcycles/1/maintenance-records",
    "POST",
    expect.objectContaining({
      cost_amount: "0.00",
      currency: "CHF",
      odometer_km: "15000",
    }),
  );
});
it("disables writes for an unverified account", async () => {
  vi.mocked(request).mockImplementation(async (path) =>
    path === "/api/v1/motorcycles/1" ? { data: bike } : page([bike]),
  );
  render(
    <MemoryRouter initialEntries={["/garage/motorcycles"]}>
      <Routes>
        <Route path="/garage/*" element={<Garage verified={false} />} />
      </Routes>
    </MemoryRouter>,
  );
  expect(screen.getByRole("button", { name: "Add motorcycle" })).toBeDisabled();
  fireEvent.click(
    await screen.findByRole("link", { name: "Honda CB500X (2022)" }),
  );
  await screen.findByRole("heading", { name: "Honda CB500X (2022)" });
  expect(
    screen.getByRole("button", { name: "Record maintenance" }),
  ).toBeDisabled();
  expect(
    screen.getByRole("button", { name: "Edit motorcycle" }),
  ).toBeDisabled();
});
it("discards a late history response after selecting a different motorcycle", async () => {
  let finish: (value: unknown) => void = () => undefined;
  const old = new Promise((resolve) => {
    finish = resolve;
  });
  vi.mocked(request).mockImplementation(async (path) => {
    if (path === "/api/v1/motorcycles/1") return { data: bike };
    if (path === "/api/v1/motorcycles/2")
      return { data: { ...bike, id: 2, model: "CB650R" } };
    if (path.startsWith("/api/v1/motorcycles/1/maintenance-records"))
      return old;
    if (path.includes("maintenance-records")) return page([]);
    return page([bike, { ...bike, id: 2, model: "CB650R" }]);
  });
  render(
    <MemoryRouter initialEntries={["/garage/motorcycles"]}>
      <Routes>
        <Route path="/garage/*" element={<Garage verified />} />
      </Routes>
    </MemoryRouter>,
  );
  fireEvent.click(
    await screen.findByRole("link", { name: "Honda CB500X (2022)" }),
  );
  fireEvent.click(
    await screen.findByRole("link", { name: "Maintenance history" }),
  );
  await waitFor(() =>
    expect(request).toHaveBeenCalledWith(
      "/api/v1/motorcycles/1/maintenance-records?page=1",
    ),
  );
  fireEvent.click(screen.getByRole("link", { name: "Garage home" }));
  fireEvent.click(screen.getByRole("link", { name: "Your motorcycles" }));
  fireEvent.click(
    await screen.findByRole("link", { name: "Honda CB650R (2022)" }),
  );
  fireEvent.click(
    await screen.findByRole("link", { name: "Maintenance history" }),
  );
  await screen.findByText("No maintenance recorded yet.");
  await act(async () => {
    finish(page([entry]));
    await old;
  });
  expect(screen.queryByText("Oil change")).not.toBeInTheDocument();
});

it("does not show the previous page as the next page after a pagination failure", async () => {
  vi.mocked(request)
    .mockResolvedValueOnce(page([bike], 1, 2))
    .mockRejectedValueOnce(new Error("Second page unavailable."))
    .mockResolvedValueOnce(page([{ ...bike, id: 2, model: "CB650R" }], 2, 2));
  render(
    <MemoryRouter initialEntries={["/garage/motorcycles"]}>
      <Routes>
        <Route path="/garage/*" element={<Garage verified />} />
      </Routes>
    </MemoryRouter>,
  );
  await screen.findByRole("link", { name: "Honda CB500X (2022)" });
  fireEvent.click(screen.getByRole("button", { name: "Next page" }));
  await screen.findByText("Second page unavailable.");
  expect(
    screen.queryByRole("link", { name: "Honda CB500X (2022)" }),
  ).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole("button", { name: "Retry garage" }));
  await screen.findByRole("link", { name: "Honda CB650R (2022)" });
  expect(screen.getByText("Page 2 of 2")).toBeInTheDocument();
  expect(request).toHaveBeenLastCalledWith("/api/v1/motorcycles?page=2");
});

it("loads a maintenance deep link without visiting its list and provides parent navigation", async () => {
  vi.mocked(request).mockImplementation(async (path) => {
    if (path === "/api/v1/motorcycles/1") return { data: bike };
    if (path === "/api/v1/motorcycles/1/maintenance-records/10")
      return { data: entry };
    return page([]);
  });
  render(
    <MemoryRouter initialEntries={["/garage/motorcycles/1/maintenance/10"]}>
      <Routes>
        <Route path="/garage/*" element={<Garage verified />} />
      </Routes>
    </MemoryRouter>,
  );
  await screen.findByRole("heading", { name: "Oil change" });
  expect(screen.queryByRole("form")).not.toBeInTheDocument();
  expect(request).not.toHaveBeenCalledWith(
    "/api/v1/motorcycles/1/maintenance-records?page=1",
  );
  fireEvent.click(
    screen.getByRole("link", { name: /Back to maintenance history/ }),
  );
  await screen.findByRole("heading", { name: "Maintenance history" });
  fireEvent.click(screen.getByRole("link", { name: "Garage home" }));
  await screen.findByRole("heading", { name: "Your garage" });
});
