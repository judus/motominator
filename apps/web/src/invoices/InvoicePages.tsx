import { Badge, Button, Card, Group, Stack, Text } from "@mantine/core";
import { Link, useNavigate, useParams } from "react-router";
import { useInvoiceImport, useInvoiceList } from "@motominator/client/react";
import { client } from "../client";
import { useBike } from "../garage/use-bike";
import { bikePath, routeId } from "../navigation/routes";
import {
  LoadState,
  Pagination,
  VerificationNotice,
} from "../garage/components";
import { ActionLink } from "../ui/ActionLink";
import { Page } from "../ui/Page";
import { InvoiceReview, InvoiceUpload } from "./Invoices";

export function InvoiceListPage() {
  const { bike, verified } = useBike(),
    path = `${bikePath(bike.id)}/invoices`;
  const list = useInvoiceList(client, bike.id);
  return (
    <Page
      title="Invoice library"
      parent={{ to: bikePath(bike.id), label: "motorcycle" }}
      actions={
        <ActionLink to={`${path}/upload`} disabled={!verified}>
          Upload invoice
        </ActionLink>
      }
    >
      {list.loading && <Text role="status">Loading invoices…</Text>}
      {list.error && (
        <LoadState loading={false} error={list.error} retry={list.reload} />
      )}
      {list.result && (
        <>
          {!list.result.data.length && (
            <Text c="dimmed">No invoices uploaded yet.</Text>
          )}
          <Stack>
            {list.result.data.map((record) => (
              <Card key={record.id} withBorder p="lg">
                <Group justify="space-between">
                  <Button
                    component={Link}
                    to={`${path}/${record.id}`}
                    variant="outline"
                  >
                    {record.filename}
                  </Button>
                  <Badge>{record.status}</Badge>
                </Group>
              </Card>
            ))}
          </Stack>
          <Pagination
            page={list.page}
            last={list.result.meta.last_page}
            loading={list.loading}
            onChange={list.setPage}
          />
        </>
      )}
    </Page>
  );
}
export function InvoiceUploadPage() {
  const { bike, verified } = useBike(),
    navigate = useNavigate(),
    path = `${bikePath(bike.id)}/invoices`;
  return (
    <Page title="Upload invoice" parent={{ to: path, label: "invoices" }}>
      <VerificationNotice verified={verified} />
      {verified && (
        <InvoiceUpload
          motorcycleId={bike.id}
          verified
          onUploaded={(record) =>
            navigate(`${path}/${record.id}`, { replace: true })
          }
        />
      )}
    </Page>
  );
}
export function InvoiceReviewPage() {
  const id = routeId(useParams().invoiceId),
    { bike } = useBike();
  return id === null ? (
    <Page
      title="Invoice not found"
      parent={{ to: `${bikePath(bike.id)}/invoices`, label: "invoices" }}
    >
      <Text>Invalid invoice address.</Text>
    </Page>
  ) : (
    <LoadedInvoice key={`${bike.id}:${id}`} id={id} />
  );
}
function LoadedInvoice({ id }: { id: number }) {
  const { bike, verified, reload } = useBike();
  const record = useInvoiceImport(client, bike.id, id);
  return (
    <Page
      title={record.result?.filename || "Invoice"}
      parent={{ to: `${bikePath(bike.id)}/invoices`, label: "invoices" }}
    >
      {record.result ? (
        <InvoiceReview
          key={record.result.id}
          motorcycleId={bike.id}
          verified={verified}
          record={record.result}
          onConfirmed={reload}
        />
      ) : (
        <LoadState {...record} retry={record.reload} />
      )}
    </Page>
  );
}
