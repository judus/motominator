import { Alert, Button, Card, Paper, Stack, Text, Title } from "@mantine/core";
import { Link, useNavigate, useParams } from "react-router";
import {
  useMaintenanceHistory,
  useMaintenanceRecord,
} from "@motominator/client/react";
import { type MaintenanceRecord } from "@motominator/client";
import { client } from "../client";
import { LoadState, Pagination, VerificationNotice } from "./components";
import { GarageForm } from "./GarageForm";
import { bikePath, routeId } from "../navigation/routes";
import { useBike } from "./use-bike";
import { ActionLink } from "../ui/ActionLink";
import { Page } from "../ui/Page";

export function MaintenanceList() {
  const { bike, verified } = useBike(),
    path = `${bikePath(bike.id)}/maintenance`;
  const history = useMaintenanceHistory(client, bike.id);
  return (
    <Page
      title="Maintenance history"
      parent={{ to: bikePath(bike.id), label: "motorcycle" }}
      actions={
        <ActionLink to={`${path}/new`} disabled={!verified}>
          Record maintenance
        </ActionLink>
      }
    >
      {history.loading && <Text role="status">Loading maintenance…</Text>}
      {history.error && (
        <Alert role="alert" color="red">
          {history.error}
          <Button variant="outline" onClick={history.reload}>
            Retry history
          </Button>
        </Alert>
      )}
      {history.result && (
        <>
          {!history.result.data.length && (
            <Text c="dimmed">No maintenance recorded yet.</Text>
          )}
          <Stack component="ol" p={0} m={0} style={{ listStyle: "none" }}>
            {history.result.data.map((item) => (
              <Card component="li" key={item.id} withBorder p="lg">
                <Stack gap="xs">
                  <Title order={2} size="h4">
                    {item.title}
                  </Title>
                  <Text size="sm" c="dimmed">
                    {item.performed_on} · {item.odometer_km} km
                  </Text>
                  <Text>
                    {item.cost_amount === null
                      ? "No cost recorded"
                      : `${item.cost_amount} ${item.currency}`}
                  </Text>
                  <Button
                    component={Link}
                    to={`${path}/${item.id}`}
                    variant="outline"
                    w="fit-content"
                  >
                    View {item.title}
                  </Button>
                </Stack>
              </Card>
            ))}
          </Stack>
          <Pagination
            page={history.page}
            last={history.result.meta.last_page}
            loading={history.loading}
            onChange={history.setPage}
          />
        </>
      )}
    </Page>
  );
}
export function NewMaintenance() {
  const { bike, verified, reload } = useBike(),
    navigate = useNavigate(),
    path = `${bikePath(bike.id)}/maintenance`;
  return (
    <Page
      title="Record maintenance"
      parent={{ to: path, label: "maintenance history" }}
    >
      <VerificationNotice verified={verified} />
      {verified && (
        <GarageForm
          kind="maintenance"
          defaultMileage={bike.odometer_km}
          onCancel={() => navigate(path)}
          onSave={async (data) => {
            await client.garage.saveMaintenance(bike.id, data);
            reload();
            navigate(path, { replace: true });
          }}
        />
      )}
    </Page>
  );
}
export function MaintenanceDetails() {
  return <MaintenanceRecordScreen editing={false} />;
}
export function EditMaintenance() {
  return <MaintenanceRecordScreen editing />;
}
function MaintenanceRecordScreen({ editing }: { editing: boolean }) {
  const id = routeId(useParams().recordId);
  const { bike } = useBike();
  return id === null ? (
    <Page
      title="Maintenance record"
      parent={{
        to: `${bikePath(bike.id)}/maintenance`,
        label: "maintenance history",
      }}
    >
      <Text role="alert">Invalid maintenance address.</Text>
    </Page>
  ) : (
    <LoadedMaintenance
      key={`${bike.id}:${id}:${editing}`}
      id={id}
      editing={editing}
    />
  );
}
function LoadedMaintenance({ id, editing }: { id: number; editing: boolean }) {
  const { bike, verified, reload } = useBike(),
    navigate = useNavigate(),
    path = `${bikePath(bike.id)}/maintenance`;
  const record = useMaintenanceRecord(client, bike.id, id);
  const item = record.result;
  return (
    <Page
      title={editing ? "Edit maintenance" : item?.title || "Maintenance record"}
      parent={{
        to: editing ? `${path}/${id}` : path,
        label: editing ? "maintenance record" : "maintenance history",
      }}
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
              onCancel={() => navigate(`${path}/${id}`)}
              onSave={async (data) => {
                await client.garage.saveMaintenance(bike.id, data, id);
                reload();
                navigate(`${path}/${id}`, { replace: true });
              }}
            />
          )}
        </>
      ) : (
        <MaintenanceSummary
          item={item}
          editPath={`${path}/${id}/edit`}
          verified={verified}
        />
      )}
    </Page>
  );
}
function MaintenanceSummary({
  item,
  editPath,
  verified,
}: {
  item: MaintenanceRecord;
  editPath: string;
  verified: boolean;
}) {
  return (
    <Paper withBorder p="lg">
      <Stack>
        <Text>
          {item.performed_on} · {item.odometer_km} km
        </Text>
        <Text>
          {item.cost_amount === null
            ? "No cost recorded"
            : `${item.cost_amount} ${item.currency}`}
        </Text>
        {item.notes && (
          <Text style={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>
            {item.notes}
          </Text>
        )}
        <ActionLink
          to={editPath}
          variant="outline"
          disabled={!verified}
          w="fit-content"
        >
          Edit maintenance
        </ActionLink>
      </Stack>
    </Paper>
  );
}
