import { useRef, useEffect, useState } from "react";
import { Button, Column, Field, H3, Section, Text } from "@/ui/components";
import { useClient } from "@/client";
import { useInvoiceReview, useInvoiceUpload } from "@motominator/client/react";
import {
  type InvoiceImport,
  invoiceFields,
  workshopFields,
  itemFields,
  invoiceWarnings,
  failureMessage,
} from "@motominator/client";
import {
  pickInvoice,
  invoiceBody,
  clearPickedInvoice,
  shareInvoice,
  type PickedInvoice,
} from "./files";

export function InvoiceUpload({
  motorcycleId,
  verified,
  onUploaded,
}: {
  motorcycleId: number;
  verified: boolean;
  onUploaded: (record: InvoiceImport) => void;
}) {
  const invoices = useInvoiceUpload(useClient(), motorcycleId),
    { busy } = invoices;
  const [file, setFile] = useState<PickedInvoice | null>(null);
  const [fileError, setFileError] = useState("");
  const [picking, setPicking] = useState(false);
  const pending = useRef(false),
    active = useRef(true),
    selectedFile = useRef<PickedInvoice | null>(null),
    uploading = useRef(false);
  useEffect(() => {
    active.current = true;
    return () => {
      active.current = false;
      if (selectedFile.current && !uploading.current) {
        clearPickedInvoice(selectedFile.current);
        selectedFile.current = null;
      }
    };
  }, []);
  async function choose(kind: "document" | "camera" | "photo") {
    if (pending.current || uploading.current) return;
    pending.current = true;
    setPicking(true);
    setFileError("");
    try {
      const chosen = await pickInvoice(kind);
      if (chosen) {
        if (active.current) {
          if (selectedFile.current) clearPickedInvoice(selectedFile.current);
          selectedFile.current = chosen;
          setFile(chosen);
        } else clearPickedInvoice(chosen);
      }
    } catch (failure) {
      if (active.current)
        setFileError(failureMessage(failure, "Unable to choose an invoice."));
    } finally {
      pending.current = false;
      if (active.current) setPicking(false);
    }
  }
  async function upload() {
    if (!file || uploading.current || pending.current) return;
    uploading.current = true;
    setFileError("");
    try {
      const saved = await invoices.upload(invoiceBody(file));
      if (saved) {
        clearPickedInvoice(file);
        selectedFile.current = null;
        if (active.current) {
          setFile(null);
          onUploaded(saved);
        }
      }
    } catch (failure) {
      if (active.current)
        setFileError(
          failureMessage(failure, "Unable to read the invoice file."),
        );
    } finally {
      uploading.current = false;
      if (!active.current && selectedFile.current) {
        clearPickedInvoice(selectedFile.current);
        selectedFile.current = null;
      }
    }
  }
  return (
    <Section>
      <Text>Private PDF, JPEG or PNG files, up to 10 MB.</Text>
      <Button
        intent="secondary"
        label="Choose invoice file"
        disabled={!verified || busy || picking}
        onPress={() => void choose("document")}
      />
      <Button
        intent="secondary"
        label="Choose invoice photo"
        disabled={!verified || busy || picking}
        onPress={() => void choose("photo")}
      />
      <Button
        intent="secondary"
        label="Photograph invoice"
        disabled={!verified || busy || picking}
        onPress={() => void choose("camera")}
      />
      {file && <Text>{file.name}</Text>}
      <Button
        label="Upload invoice"
        disabled={!file || !verified || busy || picking}
        onPress={() => void upload()}
      />
      {fileError && <Text accessibilityRole="alert">{fileError}</Text>}
      {invoices.error && (
        <Text accessibilityRole="alert">{invoices.error}</Text>
      )}
    </Section>
  );
}
export function InvoiceReview({
  motorcycleId,
  verified,
  record,
  onConfirmed,
}: {
  motorcycleId: number;
  verified: boolean;
  record: InvoiceImport;
  onConfirmed: () => void;
}) {
  const client = useClient(),
    invoices = useInvoiceReview(client, motorcycleId, record, onConfirmed);
  const { selected, draft, busy } = invoices;
  const [fileError, setFileError] = useState("");
  const [picking, setPicking] = useState(false);
  const pending = useRef(false),
    active = useRef(true);
  useEffect(() => {
    active.current = true;
    return () => {
      active.current = false;
    };
  }, []);
  const locked = busy || !verified || selected.status === "confirmed";
  async function open() {
    if (!selected || pending.current) return;
    pending.current = true;
    setPicking(true);
    setFileError("");
    try {
      await shareInvoice(
        client.invoices.downloadPath(motorcycleId, selected.id),
        selected,
      );
    } catch (failure) {
      if (active.current)
        setFileError(failureMessage(failure, "Unable to open the original."));
    } finally {
      pending.current = false;
      if (active.current) setPicking(false);
    }
  }
  return (
    <Section>
      {fileError && <Text accessibilityRole="alert">{fileError}</Text>}
      {invoices.error && (
        <Text accessibilityRole="alert">{invoices.error}</Text>
      )}
      {invoices.message && <Text>{invoices.message}</Text>}
      <Column gap="$3">
        <H3>{selected.filename}</H3>
        <Text>Status: {selected.status}</Text>
        <Button
          intent="secondary"
          label="View / share original invoice"
          disabled={picking}
          onPress={() => void open()}
        />
        {selected.error && (
          <Text accessibilityRole="alert">{selected.error}</Text>
        )}
        {selected.retry_available && (
          <>
            <Text>
              Extraction sends this file to your saved BYOK provider and may
              incur charges. Review the result before saving maintenance.
            </Text>
            <Button
              label="Extract with my AI provider"
              disabled={!verified || busy}
              onPress={() => void invoices.extract()}
            />
          </>
        )}
        {["queued", "processing"].includes(selected.status) && (
          <Text>
            Extraction is {selected.status}. Status updates automatically. Retry
            becomes available after three minutes if interrupted.
          </Text>
        )}
        {draft && (
          <>
            {selected.status === "ready" && (
              <Button
                intent="danger"
                label="Reload saved draft (discard changes)"
                disabled={busy}
                onPress={() => void invoices.reloadSelected()}
              />
            )}
            <H3>
              {selected.status === "confirmed"
                ? "Confirmed invoice"
                : "Review extracted invoice"}
            </H3>
            {selected.provider && (
              <Text>
                Extracted by {selected.provider} / {selected.model}
              </Text>
            )}
            {invoiceWarnings(draft).map((warning) => (
              <Text key={warning}>{warning}</Text>
            ))}
            {invoiceFields.map(([key, label]) => (
              <Field
                key={key}
                id={key}
                label={label}
                value={String(draft[key] ?? "")}
                onChangeText={(text) => invoices.changeField(key, text)}
                disabled={locked}
                multiline={key === "notes"}
              />
            ))}
            <H3>Workshop</H3>
            <Text>
              Matching name and address update a workshop in your account. Blank
              contact details preserve saved details.
            </Text>
            {workshopFields.map(([key, label]) => (
              <Field
                key={key}
                id={key}
                label={label}
                value={draft.workshop[key] ?? ""}
                onChangeText={(text) => invoices.changeWorkshop(key, text)}
                disabled={locked}
                multiline={key === "address"}
              />
            ))}
            <H3>Cost positions</H3>
            {draft.items.map((item, index) => (
              <Section key={index}>
                <Text>Position {index + 1}</Text>
                {itemFields.map(([key, label]) => (
                  <Field
                    key={key}
                    id={`${key}-${index}`}
                    label={`${label} — position ${index + 1}`}
                    value={String(item[key] ?? "")}
                    onChangeText={(text) =>
                      invoices.changeItem(index, key, text)
                    }
                    disabled={locked}
                  />
                ))}
                {selected.status !== "confirmed" && (
                  <Button
                    intent="danger"
                    label={`Remove position ${index + 1}`}
                    disabled={locked}
                    onPress={() => invoices.removeItem(index)}
                  />
                )}
              </Section>
            ))}
            {selected.status === "ready" && (
              <>
                <Button
                  intent="secondary"
                  label="Add cost position"
                  disabled={locked || draft.items.length >= 100}
                  onPress={invoices.addItem}
                />
                <Button
                  intent="secondary"
                  label="Save review draft"
                  disabled={locked}
                  onPress={() => void invoices.save()}
                />
                <Button
                  label="Confirm and save maintenance"
                  disabled={locked}
                  onPress={() => void invoices.confirm()}
                />
              </>
            )}
            {selected.status === "confirmed" && (
              <Text>
                Saved as maintenance record #{selected.maintenance_record_id}.
              </Text>
            )}
          </>
        )}
      </Column>{" "}
    </Section>
  );
}
