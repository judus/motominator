import {
  act,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react-native";
import { authenticatedApi } from "../src/auth/client";
import { InvoiceUpload } from "../src/invoices/invoices";
import {
  pickInvoice,
  invoiceBody,
  clearPickedInvoice,
} from "../src/invoices/files";

const mockRefresh = jest.fn();
const mockUploaded = jest.fn();
jest.mock("../src/auth/auth-context", () => ({
  useAuth: () => ({ refresh: mockRefresh }),
}));
jest.mock("../src/auth/client", () => ({
  authenticatedApi: jest.fn(),
  AuthError: class extends Error {
    status = 0;
  },
}));
jest.mock("tamagui", () => jest.requireActual("../test/tamagui-mock"));
jest.mock("../src/invoices/files", () => ({
  pickInvoice: jest.fn(),
  invoiceBody: jest.fn(),
  clearPickedInvoice: jest.fn(),
  shareInvoice: jest.fn(),
}));
const uploaded = {
  id: 7,
  motorcycle_id: 1,
  filename: "bill.jpg",
  mime: "image/jpeg",
  status: "uploaded",
  version: 0,
  draft: null,
  retry_available: true,
};
const page = (data: unknown[]) => ({
  data,
  meta: { current_page: 1, last_page: 1 },
});
beforeEach(() => {
  jest.clearAllMocks();
});
it("uses the camera adapter, uploads privately and does not automatically invoke AI", async () => {
  const picked = { uri: "file:///cache/bill.jpg", name: "bill.jpg" };
  const body = new FormData();
  jest.mocked(pickInvoice).mockResolvedValue(picked);
  jest.mocked(invoiceBody).mockReturnValue(body);
  jest
    .mocked(authenticatedApi)
    .mockImplementation(async (_path, method) =>
      method === "POST" ? { data: uploaded } : page([]),
    );
  await render(
    <InvoiceUpload motorcycleId={1} verified onUploaded={mockUploaded} />,
  );
  await fireEvent.press(
    screen.getByRole("button", { name: "Photograph invoice" }),
  );
  expect(pickInvoice).toHaveBeenCalledWith("camera");
  await fireEvent.press(screen.getByRole("button", { name: "Upload invoice" }));
  await waitFor(() => expect(mockUploaded).toHaveBeenCalledWith(uploaded));
  expect(authenticatedApi).toHaveBeenCalledWith(
    "/api/v1/motorcycles/1/invoice-imports",
    "POST",
    body,
  );
  expect(clearPickedInvoice).toHaveBeenCalledWith(picked);
  expect(
    jest
      .mocked(authenticatedApi)
      .mock.calls.some(([path]) => path.endsWith("/extract")),
  ).toBe(false);
});
it("shows denied camera access without losing the upload controls", async () => {
  jest.mocked(authenticatedApi).mockResolvedValue(page([]));
  jest.mocked(pickInvoice).mockRejectedValue(new Error("Allow camera access."));
  await render(
    <InvoiceUpload motorcycleId={1} verified onUploaded={mockUploaded} />,
  );
  await fireEvent.press(
    screen.getByRole("button", { name: "Photograph invoice" }),
  );
  expect(await screen.findByText("Allow camera access.")).toBeOnTheScreen();
  expect(
    screen.getByRole("button", { name: "Choose invoice file" }),
  ).toBeEnabled();
});
it("keeps a picked file after upload failure and allows retry", async () => {
  jest
    .mocked(pickInvoice)
    .mockResolvedValue({ uri: "file:///cache/bill.jpg", name: "bill.jpg" });
  jest.mocked(invoiceBody).mockReturnValue(new FormData());
  let saves = 0;
  jest.mocked(authenticatedApi).mockImplementation(async (_path, method) => {
    if (method === "POST") {
      if (++saves === 1) throw new Error("Connection lost.");
      return { data: uploaded };
    }
    return page([]);
  });
  await render(
    <InvoiceUpload motorcycleId={1} verified onUploaded={mockUploaded} />,
  );
  await fireEvent.press(
    screen.getByRole("button", { name: "Choose invoice file" }),
  );
  await fireEvent.press(screen.getByRole("button", { name: "Upload invoice" }));
  await screen.findByText("Connection lost.");
  expect(clearPickedInvoice).not.toHaveBeenCalled();
  expect(screen.getByRole("button", { name: "Upload invoice" })).toBeEnabled();
  await fireEvent.press(screen.getByRole("button", { name: "Upload invoice" }));
  await waitFor(() => expect(mockUploaded).toHaveBeenCalledWith(uploaded));
  expect(clearPickedInvoice).toHaveBeenCalledTimes(1);
});

it("releases an abandoned selected cache file", async () => {
  const picked = { uri: "file:///cache/bill.jpg", name: "bill.jpg" };
  jest.mocked(pickInvoice).mockResolvedValue(picked);
  const view = await render(
    <InvoiceUpload motorcycleId={1} verified onUploaded={mockUploaded} />,
  );
  await fireEvent.press(
    screen.getByRole("button", { name: "Choose invoice file" }),
  );
  await view.unmount();
  expect(clearPickedInvoice).toHaveBeenCalledWith(picked);
});

it("retains a file while an abandoned upload reads it, then releases it", async () => {
  const picked = { uri: "file:///cache/bill.jpg", name: "bill.jpg" };
  jest.mocked(pickInvoice).mockResolvedValue(picked);
  jest.mocked(invoiceBody).mockReturnValue(new FormData());
  let finish!: (value: unknown) => void;
  jest.mocked(authenticatedApi).mockImplementation(
    () =>
      new Promise((resolve) => {
        finish = resolve;
      }),
  );
  const view = await render(
    <InvoiceUpload motorcycleId={1} verified onUploaded={mockUploaded} />,
  );
  await fireEvent.press(
    screen.getByRole("button", { name: "Choose invoice file" }),
  );
  await fireEvent.press(screen.getByRole("button", { name: "Upload invoice" }));
  await view.unmount();
  expect(clearPickedInvoice).not.toHaveBeenCalled();
  await act(async () => {
    finish({ data: uploaded });
  });
  expect(clearPickedInvoice).toHaveBeenCalledWith(picked);
  expect(mockUploaded).not.toHaveBeenCalled();
});

it("releases a picker result arriving after the screen was abandoned", async () => {
  let finish!: (value: { uri: string; name: string }) => void;
  const picked = { uri: "file:///cache/bill.jpg", name: "bill.jpg" };
  jest.mocked(pickInvoice).mockImplementation(
    () =>
      new Promise((resolve) => {
        finish = resolve;
      }),
  );
  const view = await render(
    <InvoiceUpload motorcycleId={1} verified onUploaded={mockUploaded} />,
  );
  await fireEvent.press(
    screen.getByRole("button", { name: "Choose invoice file" }),
  );
  await view.unmount();
  await act(async () => {
    finish(picked);
  });
  expect(clearPickedInvoice).toHaveBeenCalledWith(picked);
  expect(mockUploaded).not.toHaveBeenCalled();
});
