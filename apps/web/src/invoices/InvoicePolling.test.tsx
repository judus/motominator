import { act, renderHook, waitFor } from "@testing-library/react";
import { expect, it, vi } from "vitest";
import {
  createClient,
  type Request,
  type InvoiceImport,
} from "@motominator/client";
import { useInvoiceReview } from "@motominator/client/react";

const record: InvoiceImport = {
  id: 7,
  motorcycle_id: 1,
  filename: "bill.pdf",
  mime: "application/pdf",
  size: 100,
  status: "uploaded",
  version: 0,
  draft: null,
  error: null,
  provider: null,
  model: null,
  created_at: "2026-10-09",
  maintenance_record_id: null,
  retry_available: true,
};
it("polls status reads after one explicit extraction and cancels polling on unmount", async () => {
  const scheduled: {
    callback: () => void;
    cancel: ReturnType<typeof vi.fn>;
  }[] = [];
  const request = vi.fn(async (path: string, method?: string) => {
    if (method === "POST")
      return { data: { ...record, status: "queued", retry_available: false } };
    if (path.endsWith("/7"))
      return {
        data: { ...record, status: "processing", retry_available: false },
      };
    return { data: [record], meta: { current_page: 1, last_page: 1 } };
  });
  const client = createClient(request as Request, (callback) => {
    const cancel = vi.fn();
    scheduled.push({ callback, cancel });
    return cancel;
  });
  const { result, unmount } = renderHook(() =>
    useInvoiceReview(client, 1, record, vi.fn()),
  );
  await act(async () => {
    await result.current.extract();
  });
  expect(scheduled).toHaveLength(1);
  await act(async () => {
    scheduled[0].callback();
  });
  await waitFor(() =>
    expect(result.current.selected?.status).toBe("processing"),
  );
  expect(
    request.mock.calls.filter(([, method]) => method === "POST"),
  ).toHaveLength(1);
  expect(request).toHaveBeenCalledWith(
    "/api/v1/motorcycles/1/invoice-imports/7",
  );
  unmount();
  expect(scheduled.at(-1)!.cancel).toHaveBeenCalled();
});
it("ignores a late poll after the old routed editor unmounts", async () => {
  let resolvePoll: (value: unknown) => void = () => undefined;
  const response = new Promise((resolve) => {
    resolvePoll = resolve;
  });
  const request = vi.fn(async () => response);
  let poll: () => void = () => undefined;
  const client = createClient(request as Request, (callback) => {
    poll = callback;
    return vi.fn();
  });
  const old = renderHook(() =>
    useInvoiceReview(client, 1, { ...record, status: "queued" }, vi.fn()),
  );
  act(() => poll());
  old.unmount();
  const current = renderHook(() =>
    useInvoiceReview(client, 1, { ...record, id: 8 }, vi.fn()),
  );
  await act(async () => {
    resolvePoll({ data: { ...record, status: "ready" } });
    await response;
  });
  expect(current.result.current.selected.id).toBe(8);
  expect(current.result.current.selected.status).toBe("uploaded");
});
