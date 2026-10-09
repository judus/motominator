import { Button, Column, H3, Screen, Section, Text } from "@/ui/components";
import { Link } from "expo-router";
import { useAuth } from "@/auth/auth-context";
export default function HomeScreen() {
  const { user } = useAuth();
  return (
    <Screen
      standalone
      title={`Welcome, ${user?.name || "rider"}`}
      subtitle="Where would you like to go?"
    >
      <Column gap="$4" $md={{ flexDirection: "row" }}>
        <Section flex={1}>
          <H3>Garage</H3>
          <Text color="$color11">
            Your motorcycles, maintenance and invoices.
          </Text>
          <Link href="/garage" asChild>
            <Button intent="secondary" label="Open garage" />
          </Link>
        </Section>
        <Section flex={1}>
          <H3>Account</H3>
          <Text color="$color11">Your profile and preferences.</Text>
          <Link href="/account" asChild>
            <Button intent="secondary" label="Open account" />
          </Link>
        </Section>
      </Column>
    </Screen>
  );
}
