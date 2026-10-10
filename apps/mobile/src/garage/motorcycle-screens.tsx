import { Link, router, useLocalSearchParams } from "expo-router";
import {
  useMaintenanceHistory,
  useMaintenanceRecord,
} from "@motominator/client/react";
import { motorcycleName, type Motorcycle } from "@motominator/client";
import { Button, Column, H3, Screen, Section, Text } from "@/ui/components";
import { RecordList } from "@/ui/record-list";
import { useClient } from "@/client";
import { useAuth } from "@/auth/auth-context";
import { bikePath, routeId } from "@/navigation/routes";
import { useRefreshOnFocus } from "@/navigation/use-refresh-on-focus";
import { MotorcycleRoute } from "./motorcycle-route";
import { GarageForm } from "./garage-form";
import { LoadState, Pages, VerificationNotice } from "./components";

export function NewMotorcycleScreen() {
  const client = useClient(),
    verified = useAuth().user?.email_verified_at != null;
  return (
    <Screen title="Add motorcycle">
      <VerificationNotice verified={verified} />
      {verified && (
        <GarageForm
          kind="motorcycle"
          onCancel={() => router.dismissTo("/garage/motorcycles")}
          onSave={async (data) => {
            const saved = await client.garage.saveMotorcycle(data);
            router.replace(bikePath(saved.id));
          }}
        />
      )}
    </Screen>
  );
}
export function MotorcycleOverviewScreen() {
  return (
    <MotorcycleRoute>
      {(bike, verified) => (
        <MotorcycleOverview bike={bike} verified={verified} />
      )}
    </MotorcycleRoute>
  );
}
function MotorcycleOverview({
  bike,
  verified,
}: {
  bike: Motorcycle;
  verified: boolean;
}) {
  const path = bikePath(bike.id);
  return (
    <Screen title={motorcycleName(bike)}>
      <Section>
        <Text color="$color11">Current mileage</Text>
        <H3>{`${bike.odometer_km.toLocaleString()} km`}</H3>
        <Link href={`${path}/edit`} asChild>
          <Button
            intent="secondary"
            disabled={!verified}
            label="Edit motorcycle"
          />
        </Link>
      </Section>
      <Column gap="$4" $md={{ flexDirection: "row" }}>
        <Section flex={1}>
          <H3>Maintenance</H3>
          <Text color="$color11">
            Work carried out, with or without an invoice.
          </Text>
          <Link href={`${path}/maintenance`} asChild>
            <Button intent="secondary" label="Maintenance history" />
          </Link>
          <Link href={`${path}/maintenance/new`} asChild>
            <Button disabled={!verified} label="Record maintenance" />
          </Link>
        </Section>
        <Section flex={1}>
          <H3>Invoices</H3>
          <Text color="$color11">
            Private documents and their reviewed records.
          </Text>
          <Link href={`${path}/invoices`} asChild>
            <Button intent="secondary" label="Invoice library" />
          </Link>
          <Link href={`${path}/invoices/upload`} asChild>
            <Button disabled={!verified} label="Upload invoice" />
          </Link>
        </Section>
      </Column>
    </Screen>
  );
}
export function EditMotorcycleScreen() {
  return (
    <MotorcycleRoute>
      {(bike, verified) => <EditMotorcycle bike={bike} verified={verified} />}
    </MotorcycleRoute>
  );
}
function EditMotorcycle({
  bike,
  verified,
}: {
  bike: Motorcycle;
  verified: boolean;
}) {
  const client = useClient(),
    path = bikePath(bike.id);
  return (
    <Screen title="Edit motorcycle">
      <VerificationNotice verified={verified} />
      {verified && (
        <GarageForm
          kind="motorcycle"
          initial={bike}
          onCancel={() => router.dismissTo(path)}
          onSave={async (data) => {
            await client.garage.saveMotorcycle(data, bike.id);
            router.dismissTo(path);
          }}
        />
      )}
    </Screen>
  );
}
export function MaintenanceListScreen() {
  return (
    <MotorcycleRoute>
      {(bike, verified) => <MaintenanceList bike={bike} verified={verified} />}
    </MotorcycleRoute>
  );
}
function MaintenanceList({
  bike,
  verified,
}: {
  bike: Motorcycle;
  verified: boolean;
}) {
  const history = useMaintenanceHistory(useClient(), bike.id),
    path = `${bikePath(bike.id)}/maintenance` as const;
  useRefreshOnFocus(history.reload);
  return (
    <RecordList
      title="Maintenance history"
      data={history.result?.data ?? []}
      refreshing={history.loading}
      onRefresh={history.reload}
      empty={
        <Text>
          {history.loading
            ? "Loading maintenance…"
            : history.error
              ? "History unavailable."
              : "No maintenance recorded yet."}
        </Text>
      }
      footer={
        <Pages
          page={history.page}
          last={history.result?.meta.last_page ?? 1}
          loading={history.loading}
          onChange={history.setPage}
        />
      }
      renderItem={({ item }) => (
        <Section>
          <H3>{item.title}</H3>
          <Text>{`${item.performed_on} · ${item.odometer_km} km`}</Text>
          <Text>
            {item.cost_amount === null
              ? "No cost recorded"
              : `${item.cost_amount} ${item.currency}`}
          </Text>
          <Link href={`${path}/${item.id}`} asChild>
            <Button intent="secondary" label={`View ${item.title}`} />
          </Link>
        </Section>
      )}
    >
      <Link href={`${path}/new`} asChild>
        <Button disabled={!verified} label="Record maintenance" />
      </Link>
      {history.error && (
        <>
          <Text accessibilityRole="alert">{history.error}</Text>
          <Button
            intent="secondary"
            label="Retry history"
            onPress={history.reload}
          />
        </>
      )}
    </RecordList>
  );
}
export function NewMaintenanceScreen() {
  return (
    <MotorcycleRoute>
      {(bike, verified) => <NewMaintenance bike={bike} verified={verified} />}
    </MotorcycleRoute>
  );
}
function NewMaintenance({
  bike,
  verified,
}: {
  bike: Motorcycle;
  verified: boolean;
}) {
  const client = useClient(),
    path = `${bikePath(bike.id)}/maintenance` as const;
  return (
    <Screen title="Record maintenance">
      <VerificationNotice verified={verified} />
      {verified && (
        <GarageForm
          kind="maintenance"
          defaultMileage={bike.odometer_km}
          onCancel={() => router.dismissTo(path)}
          onSave={async (data) => {
            await client.garage.saveMaintenance(bike.id, data);
            router.dismissTo(path);
          }}
        />
      )}
    </Screen>
  );
}
export function MaintenanceDetailsScreen() {
  return <MaintenanceRecordRoute editing={false} />;
}
export function EditMaintenanceScreen() {
  return <MaintenanceRecordRoute editing />;
}
function MaintenanceRecordRoute({ editing }: { editing: boolean }) {
  const id = routeId(useLocalSearchParams().recordId);
  return (
    <MotorcycleRoute>
      {(bike, verified) =>
        id === null ? (
          <Screen title="Maintenance not found">
            <Text>Invalid maintenance address.</Text>
          </Screen>
        ) : (
          <MaintenanceRecord
            key={`${id}:${editing}`}
            bike={bike}
            verified={verified}
            id={id}
            editing={editing}
          />
        )
      }
    </MotorcycleRoute>
  );
}
function MaintenanceRecord({
  bike,
  verified,
  id,
  editing,
}: {
  bike: Motorcycle;
  verified: boolean;
  id: number;
  editing: boolean;
}) {
  const client = useClient(),
    record = useMaintenanceRecord(client, bike.id, id),
    path = `${bikePath(bike.id)}/maintenance/${id}` as const;
  useRefreshOnFocus(record.reload);
  const item = record.result;
  return (
    <Screen
      title={editing ? "Edit maintenance" : item?.title || "Maintenance record"}
    >
      {!item ? (
        <LoadState {...record} retry={record.reload} />
      ) : editing ? (
        <>
          <VerificationNotice verified={verified} />
          {verified && (
            <GarageForm
              kind="maintenance"
              initial={item}
              onCancel={() => router.dismissTo(path)}
              onSave={async (data) => {
                await client.garage.saveMaintenance(bike.id, data, id);
                router.dismissTo(path);
              }}
            />
          )}
        </>
      ) : (
        <Section>
          <Text>{`${item.performed_on} · ${item.odometer_km} km`}</Text>
          <Text>
            {item.cost_amount === null
              ? "No cost recorded"
              : `${item.cost_amount} ${item.currency}`}
          </Text>
          {item.notes && <Text>{item.notes}</Text>}
          <Link href={`${path}/edit`} asChild>
            <Button
              intent="secondary"
              disabled={!verified}
              label="Edit maintenance"
            />
          </Link>
        </Section>
      )}
    </Screen>
  );
}
