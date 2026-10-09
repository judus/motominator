import { useState } from "react";
import {
  Alert,
  Badge,
  Button,
  FileInput,
  Group,
  Paper,
  Stack,
  Text,
  TextInput,
  Title,
} from "@mantine/core";
import { useInvoiceReview, useInvoiceUpload } from "@motominator/client/react";
import {
  invoiceFields,
  workshopFields,
  itemFields,
  invoiceWarnings,
  type InvoiceImport,
} from "@motominator/client";
import { client } from "../client";
import { apiBaseUrl } from "../api";

export function InvoiceUpload({
  motorcycleId,
  verified,
  onUploaded,
}: {
  motorcycleId: number;
  verified: boolean;
  onUploaded: (record: InvoiceImport) => void;
}) {
  const invoices = useInvoiceUpload(client, motorcycleId);
  const [file, setFile] = useState<File | null>(null);
  async function upload() {
    if (!file) return;
    const body = new FormData();
    body.append("file", file);
    const saved = await invoices.upload(body);
    if (saved) {
      setFile(null);
      onUploaded(saved);
    }
  }
  return (
    <Paper withBorder p="lg">
      <Stack>
        <Text c="dimmed">
          Upload a PDF, JPEG or PNG (up to 10 MB). Files stay private to your
          account.
        </Text>
        <FileInput
          label="Invoice file"
          accept="application/pdf,image/jpeg,image/png"
          value={file}
          onChange={setFile}
          disabled={!verified || invoices.busy}
          clearable
        />
        <Button
          disabled={!verified || invoices.busy || !file}
          loading={invoices.busy}
          onClick={() => void upload()}
        >
          Upload invoice
        </Button>
        {invoices.error && (
          <Alert color="red" role="alert">
            {invoices.error}
          </Alert>
        )}
      </Stack>
    </Paper>
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
  const invoices = useInvoiceReview(client, motorcycleId, record, onConfirmed);
  const { selected, draft, busy } = invoices;
  return (
    <Paper withBorder p="lg">
      <Stack>
        {invoices.error && (
          <Alert color="red" role="alert">
            {invoices.error}
          </Alert>
        )}
        {invoices.message && <Text role="status">{invoices.message}</Text>}
        <Stack>
          <Group justify="space-between">
            <Title order={4}>{selected.filename}</Title>
            <Badge>{selected.status}</Badge>
          </Group>
          <Button
            component="a"
            variant="outline"
            href={`${apiBaseUrl}${client.invoices.downloadPath(motorcycleId, selected.id)}`}
          >
            Download original
          </Button>
          {selected.error && <Alert color="red">{selected.error}</Alert>}
          {selected.retry_available && (
            <>
              <Text size="sm">
                Extraction sends this file to the provider in your saved BYOK
                settings and may incur charges. The result will need your
                review.
              </Text>
              <Button
                disabled={!verified || busy}
                onClick={() => void invoices.extract()}
              >
                Extract with my AI provider
              </Button>
            </>
          )}
          {["queued", "processing"].includes(selected.status) && (
            <Text role="status">
              Extraction is {selected.status}. Status updates automatically. If
              interrupted, retry becomes available after three minutes.
            </Text>
          )}
          {draft && (
            <>
              {selected.status === "ready" && (
                <Button
                  color="red"
                  variant="outline"
                  disabled={busy}
                  onClick={() => void invoices.reloadSelected()}
                >
                  Reload saved draft (discard changes)
                </Button>
              )}
              <Title order={4}>
                {selected.status === "confirmed"
                  ? "Confirmed invoice"
                  : "Review extracted invoice"}
              </Title>
              {selected.provider && (
                <Text size="sm" c="dimmed">
                  Extracted by {selected.provider} / {selected.model}
                </Text>
              )}
              {invoiceWarnings(draft).map((warning) => (
                <Alert color="yellow" key={warning}>
                  {warning}
                </Alert>
              ))}
              {invoiceFields.map(([key, label]) => (
                <TextInput
                  key={key}
                  label={label}
                  value={String(draft[key] ?? "")}
                  onChange={(event) =>
                    invoices.changeField(key, event.currentTarget.value)
                  }
                  disabled={
                    busy || selected.status === "confirmed" || !verified
                  }
                />
              ))}
              <Title order={5}>Workshop</Title>
              <Text size="sm">
                A workshop with the same name and address in your account will
                be updated. Blank contact details preserve previously saved
                details.
              </Text>
              {workshopFields.map(([key, label]) => (
                <TextInput
                  key={key}
                  label={label}
                  value={draft.workshop[key] ?? ""}
                  onChange={(event) =>
                    invoices.changeWorkshop(key, event.currentTarget.value)
                  }
                  disabled={
                    busy || selected.status === "confirmed" || !verified
                  }
                />
              ))}
              <Title order={5}>Cost positions</Title>
              {draft.items.map((item, index) => (
                <Paper key={index} withBorder p="sm">
                  <Stack>
                    <Title order={6}>Position {index + 1}</Title>
                    {itemFields.map(([key, label]) => (
                      <TextInput
                        key={key}
                        label={`${label} — position ${index + 1}`}
                        value={String(item[key] ?? "")}
                        onChange={(event) =>
                          invoices.changeItem(
                            index,
                            key,
                            event.currentTarget.value,
                          )
                        }
                        disabled={
                          busy || selected.status === "confirmed" || !verified
                        }
                      />
                    ))}
                    {selected.status !== "confirmed" && (
                      <Button
                        color="red"
                        variant="outline"
                        disabled={busy || !verified}
                        onClick={() => invoices.removeItem(index)}
                      >
                        Remove position {index + 1}
                      </Button>
                    )}
                  </Stack>
                </Paper>
              ))}
              {selected.status === "ready" && (
                <>
                  <Button
                    variant="outline"
                    disabled={busy || !verified || draft.items.length >= 100}
                    onClick={invoices.addItem}
                  >
                    Add cost position
                  </Button>
                  <Group>
                    <Button
                      variant="outline"
                      disabled={busy || !verified}
                      onClick={() => void invoices.save()}
                    >
                      Save review draft
                    </Button>
                    <Button
                      disabled={busy || !verified}
                      onClick={() => void invoices.confirm()}
                    >
                      Confirm and save maintenance
                    </Button>
                  </Group>
                </>
              )}
              {selected.status === "confirmed" && (
                <Text>
                  Saved as maintenance record #{selected.maintenance_record_id}.
                  This invoice cannot be confirmed twice.
                </Text>
              )}
            </>
          )}
        </Stack>
      </Stack>
    </Paper>
  );
}
