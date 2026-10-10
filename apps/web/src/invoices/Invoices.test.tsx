import { fireEvent, render, screen, waitFor } from "../test/render";
import { beforeEach, expect, it, vi } from "vitest";
import { request, ApiError } from "../api";
import { InvoiceReview, InvoiceUpload } from "./Invoices";
import {
  invoiceWarnings,
  invoiceValue,
  type InvoiceDraft,
  type InvoiceImport,
} from "@motominator/client";

vi.mock("../api", async (original) => ({
  ...(await original<typeof import("../api")>()),
  request: vi.fn(),
}));
const draft: InvoiceDraft = {
  invoice_number: "I-123",
  performed_on: "2025-06-01",
  odometer_km: null,
  title: "Oil change",
  notes: null,
  currency: "CHF",
  subtotal_amount: "70.00",
  tax_amount: "5.20",
  total_amount: "75.20",
  labor_minutes: 30,
  workshop: {
    name: "Local workshop",
    address: null,
    email: null,
    phone: null,
    tax_number: null,
  },
  items: [],
};
const record: InvoiceImport = {
  id: 7,
  motorcycle_id: 1,
  filename: "invoice.pdf",
  mime: "application/pdf",
  size: 100,
  status: "ready",
  version: 2,
  draft,
  error: null,
  provider: "openai",
  model: "test-model",
  created_at: "2026-10-09",
  maintenance_record_id: null,
  retry_available: false,
};
const page = (data: InvoiceImport[]) => ({
  data,
  meta: { current_page: 1, last_page: 1 },
});
beforeEach(() => {
  vi.mocked(request).mockReset();
});
it("preserves review edits after a failed confirmation and confirms with the selected version", async () => {
  let confirms = 0;
  const onConfirmed = vi.fn();
  vi.mocked(request).mockImplementation(async (path, method, data) => {
    if (path.endsWith("/confirm")) {
      if (++confirms === 1)
        throw new ApiError(422, {
          odometer_km: ["Enter the invoice mileage."],
        });
      return {
        data: {
          ...record,
          draft: (data as { draft: InvoiceDraft }).draft,
          status: "confirmed",
          maintenance_record_id: 9,
        },
      };
    }
    return method === "PUT" ? { data: record } : page([record]);
  });
  render(
    <InvoiceReview
      motorcycleId={1}
      verified
      record={record}
      onConfirmed={onConfirmed}
    />,
  );
  expect(screen.getByLabelText("Invoice mileage (km)")).toHaveValue("");
  fireEvent.change(screen.getByLabelText("Maintenance title"), {
    target: { value: "Reviewed oil change" },
  });
  fireEvent.click(
    screen.getByRole("button", { name: "Confirm and save maintenance" }),
  );
  await screen.findByText("Enter the invoice mileage.");
  expect(screen.getByLabelText("Maintenance title")).toHaveValue(
    "Reviewed oil change",
  );
  fireEvent.change(screen.getByLabelText("Invoice mileage (km)"), {
    target: { value: "15000" },
  });
  fireEvent.click(
    screen.getByRole("button", { name: "Confirm and save maintenance" }),
  );
  await screen.findByText(/Saved as maintenance record #9/);
  expect(onConfirmed).toHaveBeenCalledTimes(1);
  expect(request).toHaveBeenCalledWith(
    "/api/v1/motorcycles/1/invoice-imports/7/confirm",
    "POST",
    expect.objectContaining({
      version: 2,
      draft: expect.objectContaining({
        odometer_km: 15000,
        total_amount: "75.20",
      }),
    }),
  );
  expect(
    screen.queryByRole("button", { name: "Confirm and save maintenance" }),
  ).not.toBeInTheDocument();
});
it("uploads multipart data and waits for an explicit extraction request", async () => {
  const uploaded = {
    ...record,
    status: "uploaded" as const,
    draft: null,
    retry_available: true,
  };
  vi.mocked(request).mockImplementation(async (_path, method) =>
    method === "POST" ? { data: uploaded } : page([]),
  );
  const onUploaded = vi.fn();
  const { container } = render(
    <InvoiceUpload motorcycleId={1} verified onUploaded={onUploaded} />,
  );
  const file = new File(["%PDF-1.4"], "invoice.pdf", {
    type: "application/pdf",
  });
  fireEvent.change(container.querySelector('input[type="file"]')!, {
    target: { files: [file] },
  });
  fireEvent.click(screen.getByRole("button", { name: "Upload invoice" }));
  await waitFor(() => expect(onUploaded).toHaveBeenCalledWith(uploaded));
  const call = vi
    .mocked(request)
    .mock.calls.find(([, method]) => method === "POST")!;
  expect(call[2]).toBeInstanceOf(FormData);
  expect((call[2] as FormData).get("file")).toBe(file);
  expect(request).not.toHaveBeenCalledWith(
    expect.stringContaining("/extract"),
    expect.anything(),
  );
});
it("reloads the saved draft explicitly after a stale version conflict", async () => {
  vi.mocked(request).mockImplementation(async (path, method) => {
    if (method === "PUT") throw new ApiError(409, {}, "The draft changed.");
    if (path.endsWith("/7"))
      return {
        data: {
          ...record,
          version: 3,
          draft: { ...draft, title: "Newer saved review" },
        },
      };
    return page([record]);
  });
  render(
    <InvoiceReview
      motorcycleId={1}
      verified
      record={record}
      onConfirmed={vi.fn()}
    />,
  );
  fireEvent.click(screen.getByRole("button", { name: "Save review draft" }));
  await screen.findByText("The draft changed.");
  fireEvent.click(
    screen.getByRole("button", {
      name: "Reload saved draft (discard changes)",
    }),
  );
  await waitFor(() =>
    expect(screen.getByLabelText("Maintenance title")).toHaveValue(
      "Newer saved review",
    ),
  );
});
it("compares exact decimal amounts and leaves unknown values unknown", () => {
  expect(
    invoiceWarnings({
      ...draft,
      subtotal_amount: "0.10",
      tax_amount: "0.20",
      total_amount: "0.30",
    }),
  ).toHaveLength(1);
  expect(invoiceWarnings({ ...draft, total_amount: "75.21" })).toContain(
    "Subtotal plus tax differs from the invoice total.",
  );
  expect(invoiceWarnings({ ...draft, subtotal_amount: null })).toHaveLength(1);
});

it("preserves spaces while typing invoice descriptions and decimal text", () => {
  expect(invoiceValue("title", "Oil ")).toBe("Oil ");
  expect(invoiceValue("description", "Filter replacement ")).toBe(
    "Filter replacement ",
  );
  expect(invoiceValue("total_amount", "75.20")).toBe("75.20");
});
