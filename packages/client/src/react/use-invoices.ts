import { useCallback, useEffect, useRef, useState } from "react";
import type { Client } from "../client";
import type {
  InvoiceDraft,
  InvoiceImport,
  InvoiceItem,
  InvoiceWorkshop,
} from "../invoices";
import { blankInvoiceItem, invoiceValue } from "../invoices";
import { failureMessage } from "../errors";
import { usePage } from "./use-page";
import { useRecord } from "./use-record";

export function useInvoiceList(client: Client, motorcycleId: number) {
  const load = useCallback(
    (page: number) => client.invoices.list(motorcycleId, page),
    [client, motorcycleId],
  );
  return usePage(load);
}
export function useInvoiceImport(
  client: Client,
  motorcycleId: number,
  id: number,
) {
  const load = useCallback(
    () => client.invoices.get(motorcycleId, id),
    [client, motorcycleId, id],
  );
  return useRecord(load);
}

/** Mount an editor per import identity. Routing belongs to the consuming app. */
export function useInvoiceReview(
  client: Client,
  motorcycleId: number,
  initial: InvoiceImport,
  onConfirmed: () => void,
) {
  const [selected, setSelected] = useState<InvoiceImport>(initial);
  const [draft, setDraft] = useState<InvoiceDraft | null>(initial.draft);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const mounted = useRef(true),
    pending = useRef(false);
  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);
  const selectedId = selected.id,
    selectedStatus = selected.status;
  useEffect(() => {
    if (
      selectedId === undefined ||
      !selectedStatus ||
      !["queued", "processing"].includes(selectedStatus)
    )
      return;
    let active = true;
    let cancel: (() => void) | undefined;
    const schedule = client.schedule;
    if (!schedule) return;
    const poll = async () => {
      try {
        const record = await client.invoices.get(motorcycleId, selectedId);
        if (!active) return;
        setSelected(record);
        setError("");
        if (record.status === "ready" || record.status === "confirmed")
          setDraft(record.draft);
        if (["queued", "processing"].includes(record.status))
          cancel = schedule(() => {
            void poll();
          }, 3000);
      } catch (failure) {
        if (active) {
          setError(
            failureMessage(failure, "Unable to refresh extraction status."),
          );
          cancel = schedule(() => {
            void poll();
          }, 5000);
        }
      }
    };
    cancel = schedule(() => {
      void poll();
    }, 1500);
    return () => {
      active = false;
      cancel?.();
    };
  }, [client, motorcycleId, selectedId, selectedStatus]);
  async function run(operation: () => Promise<InvoiceImport>, success: string) {
    if (pending.current) return false;
    pending.current = true;
    setBusy(true);
    setError("");
    setMessage("");
    try {
      const record = await operation();
      if (mounted.current) {
        setSelected(record);
        setDraft(record.draft);
        setMessage(success);
      }
      return true;
    } catch (failure) {
      if (mounted.current)
        setError(
          failureMessage(failure, "Unable to complete the invoice operation."),
        );
      return false;
    } finally {
      pending.current = false;
      if (mounted.current) setBusy(false);
    }
  }
  function changeField(key: string, text: string) {
    setDraft((value) =>
      value ? { ...value, [key]: invoiceValue(key, text) } : null,
    );
  }
  function changeWorkshop(key: keyof InvoiceWorkshop, text: string) {
    setDraft((value) =>
      value
        ? { ...value, workshop: { ...value.workshop, [key]: text || null } }
        : null,
    );
  }
  function changeItem(index: number, key: keyof InvoiceItem, text: string) {
    setDraft((value) =>
      value
        ? {
            ...value,
            items: value.items.map((item, at) =>
              at === index ? { ...item, [key]: invoiceValue(key, text) } : item,
            ),
          }
        : null,
    );
  }
  function addItem() {
    setDraft((value) =>
      value && value.items.length < 100
        ? { ...value, items: [...value.items, blankInvoiceItem()] }
        : value,
    );
  }
  function removeItem(index: number) {
    setDraft((value) =>
      value
        ? { ...value, items: value.items.filter((_, at) => at !== index) }
        : null,
    );
  }
  async function confirm() {
    if (!selected || !draft) return;
    if (
      await run(
        () =>
          client.invoices.confirm(
            motorcycleId,
            selected.id,
            draft,
            selected.version,
          ),
        "Maintenance saved from this invoice.",
      )
    ) {
      if (mounted.current) onConfirmed();
    }
  }
  return {
    selected,
    draft,
    busy,
    error,
    message,
    changeField,
    changeWorkshop,
    changeItem,
    addItem,
    removeItem,
    confirm,
    reloadSelected: () =>
      selected &&
      run(
        () => client.invoices.get(motorcycleId, selected.id),
        "Saved invoice reloaded. Unsaved changes were discarded.",
      ),
    extract: () =>
      selected &&
      run(
        () => client.invoices.extract(motorcycleId, selected.id),
        "Extraction queued. You can leave this screen and return later.",
      ),
    save: () =>
      selected &&
      draft &&
      run(
        () =>
          client.invoices.save(
            motorcycleId,
            selected.id,
            draft,
            selected.version,
          ),
        "Draft saved.",
      ),
  };
}

export function useInvoiceUpload(client: Client, motorcycleId: number) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const pending = useRef(false),
    mounted = useRef(true);
  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);
  async function upload(body: unknown): Promise<InvoiceImport | null> {
    if (pending.current) return null;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      const saved = await client.invoices.upload(motorcycleId, body);
      return mounted.current ? saved : null;
    } catch (failure) {
      if (mounted.current)
        setError(failureMessage(failure, "Unable to upload this invoice."));
      return null;
    } finally {
      pending.current = false;
      if (mounted.current) setBusy(false);
    }
  }
  return { upload, busy, error };
}
