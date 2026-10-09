import { Link, router, useLocalSearchParams } from "expo-router";
import { useInvoiceImport, useInvoiceList } from "@motominator/client/react";
import type { Motorcycle } from "@motominator/client";
import { Button, Screen, Section, Text } from "@/ui/components";
import { RecordList } from "@/ui/record-list";
import { useClient } from "@/client";
import { bikePath, routeId } from "@/navigation/routes";
import { useRefreshOnFocus } from "@/navigation/use-refresh-on-focus";
import { MotorcycleRoute } from "@/garage/motorcycle-route";
import { LoadState, Pages, VerificationNotice } from "@/garage/components";
import { InvoiceReview, InvoiceUpload } from "./invoices";

export function InvoiceListScreen() {
  return (
    <MotorcycleRoute>
      {(bike, verified) => <InvoiceList bike={bike} verified={verified} />}
    </MotorcycleRoute>
  );
}
function InvoiceList({
  bike,
  verified,
}: {
  bike: Motorcycle;
  verified: boolean;
}) {
  const list = useInvoiceList(useClient(), bike.id),
    path = `${bikePath(bike.id)}/invoices` as const;
  useRefreshOnFocus(list.reload);
  return (
    <RecordList
      title="Invoice library"
      data={list.result?.data ?? []}
      refreshing={list.loading}
      onRefresh={list.reload}
      empty={
        <Text>
          {list.loading
            ? "Loading invoices…"
            : list.error
              ? "Invoices unavailable."
              : "No invoices uploaded yet."}
        </Text>
      }
      footer={
        <Pages
          page={list.page}
          last={list.result?.meta.last_page ?? 1}
          loading={list.loading}
          onChange={list.setPage}
        />
      }
      renderItem={({ item }) => (
        <Section>
          <Text>{item.filename}</Text>
          <Text color="$color11">{item.status}</Text>
          <Link href={`${path}/${item.id}`} asChild>
            <Button intent="secondary" label={`View ${item.filename}`} />
          </Link>
        </Section>
      )}
    >
      <Link href={`${path}/upload`} asChild>
        <Button disabled={!verified} label="Upload invoice" />
      </Link>
      {list.error && (
        <>
          <Text accessibilityRole="alert">{list.error}</Text>
          <Button
            intent="secondary"
            label="Retry invoices"
            onPress={list.reload}
          />
        </>
      )}
    </RecordList>
  );
}
export function InvoiceUploadScreen() {
  return (
    <MotorcycleRoute>
      {(bike, verified) => (
        <Screen title="Upload invoice">
          <VerificationNotice verified={verified} />
          {verified && (
            <InvoiceUpload
              motorcycleId={bike.id}
              verified
              onUploaded={(record) =>
                router.replace(`${bikePath(bike.id)}/invoices/${record.id}`)
              }
            />
          )}
        </Screen>
      )}
    </MotorcycleRoute>
  );
}
export function InvoiceReviewScreen() {
  const id = routeId(useLocalSearchParams().invoiceId);
  return (
    <MotorcycleRoute>
      {(bike, verified, reload) =>
        id === null ? (
          <Screen title="Invoice not found">
            <Text>Invalid invoice address.</Text>
          </Screen>
        ) : (
          <LoadedInvoice
            key={`${bike.id}:${id}`}
            id={id}
            bike={bike}
            verified={verified}
            onConfirmed={reload}
          />
        )
      }
    </MotorcycleRoute>
  );
}
function LoadedInvoice({
  bike,
  verified,
  id,
  onConfirmed,
}: {
  bike: Motorcycle;
  verified: boolean;
  id: number;
  onConfirmed: () => void;
}) {
  const record = useInvoiceImport(useClient(), bike.id, id);
  return (
    <Screen title={record.result?.filename || "Invoice"}>
      {record.result ? (
        <InvoiceReview
          key={record.result.id}
          motorcycleId={bike.id}
          verified={verified}
          record={record.result}
          onConfirmed={onConfirmed}
        />
      ) : (
        <LoadState {...record} retry={record.reload} />
      )}
    </Screen>
  );
}
