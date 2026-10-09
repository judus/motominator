import {
  Alert,
  Badge,
  Button,
  Card,
  Paper,
  SimpleGrid,
  Stack,
  Text,
  Title,
} from "@mantine/core";
import { Link, useNavigate } from "react-router";
import { useGarage } from "@motominator/client/react";
import { motorcycleName } from "@motominator/client";
import { client } from "../client";
import { Pagination, VerificationNotice } from "./components";
import { GarageForm } from "./GarageForm";
import { bikePath } from "../navigation/routes";
import { ActionLink } from "../ui/ActionLink";
import { Page } from "../ui/Page";

export function GarageOverview({ verified }: { verified: boolean }) {
  return (
    <Page
      title="Your garage"
      description="A place for your motorcycles and their stories."
      actions={
        <ActionLink to="/garage/motorcycles/new" disabled={!verified}>
          Add motorcycle
        </ActionLink>
      }
    >
      <VerificationNotice verified={verified} />
      <SimpleGrid cols={{ base: 1, sm: 2 }}>
        <Card withBorder p="xl" radius="md">
          <Stack>
            <Title order={2} size="h3">
              Motorcycles
            </Title>
            <Text c="dimmed">
              Find a bike, check its history or add an invoice.
            </Text>
            <Button component={Link} to="/garage/motorcycles" variant="outline">
              Your motorcycles
            </Button>
          </Stack>
        </Card>
      </SimpleGrid>
    </Page>
  );
}
export function MotorcycleList({ verified }: { verified: boolean }) {
  const { bikes } = useGarage(client);
  return (
    <Page
      title="Your motorcycles"
      parent={{ to: "/garage", label: "garage" }}
      actions={
        <ActionLink to="/garage/motorcycles/new" disabled={!verified}>
          Add motorcycle
        </ActionLink>
      }
    >
      <VerificationNotice verified={verified} />
      {bikes.loading && <Text role="status">Loading motorcycles…</Text>}
      {bikes.error && (
        <Alert role="alert" color="red">
          <Stack>
            {bikes.error}
            <Button variant="outline" onClick={bikes.reload}>
              Retry garage
            </Button>
          </Stack>
        </Alert>
      )}
      {bikes.result && (
        <>
          {!bikes.result.data.length && (
            <Paper withBorder p="xl">
              <Text>No motorcycles yet. Add your first motorcycle.</Text>
            </Paper>
          )}
          <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }}>
            {bikes.result.data.map((bike) => (
              <Card key={bike.id} withBorder p="lg">
                <Stack>
                  <Badge w="fit-content" variant="outline">
                    {bike.year}
                  </Badge>
                  <Title order={2} size="h4">
                    {bike.make} {bike.model}
                  </Title>
                  {bike.nickname && <Text c="dimmed">{bike.nickname}</Text>}
                  <Text>{bike.odometer_km} km</Text>
                  <Button
                    component={Link}
                    to={bikePath(bike.id)}
                    variant="outline"
                    aria-label={motorcycleName(bike)}
                  >
                    View motorcycle
                  </Button>
                </Stack>
              </Card>
            ))}
          </SimpleGrid>
          <Pagination
            page={bikes.page}
            last={bikes.result.meta.last_page}
            loading={bikes.loading}
            onChange={bikes.setPage}
          />
        </>
      )}
    </Page>
  );
}
export function NewMotorcycle({ verified }: { verified: boolean }) {
  const navigate = useNavigate();
  return (
    <Page
      title="Add motorcycle"
      parent={{ to: "/garage/motorcycles", label: "motorcycles" }}
    >
      <VerificationNotice verified={verified} />
      {verified && (
        <GarageForm
          kind="motorcycle"
          onCancel={() => navigate("/garage/motorcycles")}
          onSave={async (data) => {
            const saved = await client.garage.saveMotorcycle(data);
            navigate(bikePath(saved.id), { replace: true });
          }}
        />
      )}
    </Page>
  );
}
