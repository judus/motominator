import {
  Button,
  Card,
  Paper,
  SimpleGrid,
  Stack,
  Text,
  Title,
} from "@mantine/core";
import { Link, Outlet, useNavigate, useParams } from "react-router";
import { useMotorcycle } from "@motominator/client/react";
import { motorcycleName } from "@motominator/client";
import { client } from "../client";
import { LoadState, VerificationNotice } from "./components";
import { GarageForm } from "./GarageForm";
import { bikePath, routeId } from "../navigation/routes";
import { useBike, type MotorcycleContext } from "./use-bike";
import { ActionLink } from "../ui/ActionLink";
import { Page } from "../ui/Page";

export function MotorcycleLayout({ verified }: { verified: boolean }) {
  const id = routeId(useParams().motorcycleId);
  return id === null ? (
    <Page
      title="Motorcycle not found"
      parent={{ to: "/garage/motorcycles", label: "motorcycles" }}
    >
      <Text>Invalid motorcycle address.</Text>
    </Page>
  ) : (
    <LoadedMotorcycle key={id} id={id} verified={verified} />
  );
}
function LoadedMotorcycle({ id, verified }: { id: number; verified: boolean }) {
  const resource = useMotorcycle(client, id);
  if (!resource.result)
    return (
      <Page
        title="Motorcycle"
        parent={{ to: "/garage/motorcycles", label: "motorcycles" }}
      >
        <LoadState {...resource} retry={resource.reload} />
      </Page>
    );
  return (
    <Outlet
      context={
        {
          bike: resource.result,
          verified,
          reload: resource.reload,
        } satisfies MotorcycleContext
      }
    />
  );
}
export function MotorcycleOverview() {
  const { bike, verified } = useBike(),
    path = bikePath(bike.id);
  return (
    <Page
      title={motorcycleName(bike)}
      parent={{ to: "/garage/motorcycles", label: "motorcycles" }}
      actions={
        <ActionLink to={`${path}/edit`} variant="outline" disabled={!verified}>
          Edit motorcycle
        </ActionLink>
      }
    >
      <Paper withBorder p="lg">
        <Text c="dimmed" size="sm">
          Current mileage
        </Text>
        <Title order={2}>{bike.odometer_km.toLocaleString()} km</Title>
      </Paper>
      <SimpleGrid cols={{ base: 1, sm: 2 }}>
        <Card withBorder p="lg">
          <Stack>
            <Title order={2} size="h3">
              Maintenance
            </Title>
            <Text c="dimmed">
              Work carried out, with or without an invoice.
            </Text>
            <Button
              component={Link}
              to={`${path}/maintenance`}
              variant="outline"
            >
              Maintenance history
            </Button>
            <ActionLink to={`${path}/maintenance/new`} disabled={!verified}>
              Record maintenance
            </ActionLink>
          </Stack>
        </Card>
        <Card withBorder p="lg">
          <Stack>
            <Title order={2} size="h3">
              Invoices
            </Title>
            <Text c="dimmed">
              Private documents and their reviewed records.
            </Text>
            <Button component={Link} to={`${path}/invoices`} variant="outline">
              Invoice library
            </Button>
            <ActionLink to={`${path}/invoices/upload`} disabled={!verified}>
              Upload invoice
            </ActionLink>
          </Stack>
        </Card>
      </SimpleGrid>
    </Page>
  );
}
export function EditMotorcycle() {
  const { bike, verified, reload } = useBike(),
    navigate = useNavigate(),
    path = bikePath(bike.id);
  return (
    <Page title="Edit motorcycle" parent={{ to: path, label: "motorcycle" }}>
      <VerificationNotice verified={verified} />
      {verified && (
        <GarageForm
          key={bike.id}
          kind="motorcycle"
          initial={bike}
          onCancel={() => navigate(path)}
          onSave={async (data) => {
            await client.garage.saveMotorcycle(data, bike.id);
            reload();
            navigate(path, { replace: true });
          }}
        />
      )}
    </Page>
  );
}
