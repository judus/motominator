import { Link } from "expo-router";
import { Button, Column, H3, Screen, Section, Text } from "@/ui/components";
import { RecordList } from "@/ui/record-list";
import { useGarage } from "@motominator/client/react";
import { motorcycleName } from "@motominator/client";
import { useClient } from "@/client";
import { useAuth } from "@/auth/auth-context";
import { useRefreshOnFocus } from "@/navigation/use-refresh-on-focus";
import { bikePath } from "@/navigation/routes";
import { Pages, VerificationNotice } from "./components";
export function GarageScreen() {
  const verified = useAuth().user?.email_verified_at != null;
  return (
    <Screen
      title="Your garage"
      subtitle="A place for your motorcycles and their stories."
    >
      <VerificationNotice verified={verified} />
      <Section>
        <H3>Motorcycles</H3>
        <Text color="$color11">
          Find a bike, check its history or add an invoice.
        </Text>
        <Link href="/garage/motorcycles" asChild>
          <Button intent="secondary" label="Your motorcycles" />
        </Link>
      </Section>
      <Link href="/garage/motorcycles/new" asChild>
        <Button disabled={!verified} label="Add motorcycle" />
      </Link>
    </Screen>
  );
}
export function MotorcycleListScreen() {
  const client = useClient(),
    { bikes } = useGarage(client);
  const verified = useAuth().user?.email_verified_at != null;
  useRefreshOnFocus(bikes.reload);
  return (
    <RecordList
      title="Your motorcycles"
      data={bikes.result?.data ?? []}
      refreshing={bikes.loading}
      onRefresh={bikes.reload}
      empty={
        <Text>
          {bikes.loading
            ? "Loading motorcycles…"
            : bikes.error
              ? "Garage unavailable."
              : "No motorcycles yet. Add your first motorcycle."}
        </Text>
      }
      footer={
        <Pages
          page={bikes.page}
          last={bikes.result?.meta.last_page ?? 1}
          loading={bikes.loading}
          onChange={bikes.setPage}
        />
      }
      renderItem={({ item }) => (
        <Section>
          <H3>{motorcycleName(item)}</H3>
          <Text color="$color11">{`${item.odometer_km} km`}</Text>
          <Link href={bikePath(item.id)} asChild>
            <Button intent="secondary" label={motorcycleName(item)} />
          </Link>
        </Section>
      )}
    >
      <Column gap="$3">
        <VerificationNotice verified={verified} />
        <Link href="/garage/motorcycles/new" asChild>
          <Button disabled={!verified} label="Add motorcycle" />
        </Link>
        {bikes.error && (
          <>
            <Text accessibilityRole="alert">{bikes.error}</Text>
            <Button
              intent="secondary"
              label="Retry garage"
              onPress={bikes.reload}
            />
          </>
        )}
      </Column>
    </RecordList>
  );
}
