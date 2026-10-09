import { useState } from "react";
import { Link } from "expo-router";
import { useAuth } from "@/auth/auth-context";
import { Button, Column, H3, Screen, Section, Text } from "@/ui/components";

export default function AccountScreen() {
  const auth = useAuth();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  async function logout() {
    setBusy(true);
    setError("");
    try {
      await auth.logout();
    } catch {
      setError(
        "Unable to revoke this device. Check your connection and retry.",
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <Screen title="Account" subtitle="Your profile and preferences">
      <Section>
        <H3>{auth.user?.name}</H3>
        <Text color="$color11">{auth.user?.email}</Text>
        <Text>
          {auth.user?.email_verified_at
            ? "Email verified"
            : "Email not verified"}
        </Text>
      </Section>
      {(
        [
          ["profile", "Profile", "Name and email address"],
          ["password", "Password", "Change your sign-in password"],
          [
            "two-factor",
            "Two-factor authentication",
            "Authenticator and recovery codes",
          ],
          ["social", "Social accounts", "Manage linked sign-in providers"],
          ["devices", "Signed-in devices", "Review and revoke device access"],
        ] as const
      ).map(([section, label, description]) => (
        <Section key={section}>
          <H3>{label}</H3>
          <Text color="$color11">{description}</Text>
          <Link href={`/account/${section}`} asChild>
            <Button intent="secondary" label={label} />
          </Link>
        </Section>
      ))}
      <Section>
        <H3>AI settings</H3>
        <Text color="$color11">Your provider, model and private API key.</Text>
        <Link href="/account/ai" asChild>
          <Button intent="secondary" label="AI settings" />
        </Link>
      </Section>
      <Column gap="$2">
        <Button
          intent="secondary"
          label={busy ? "Signing out…" : "Sign out"}
          disabled={busy}
          onPress={() => void logout()}
        />
        {error || auth.error ? (
          <Text accessibilityRole="alert">{error || auth.error}</Text>
        ) : null}
      </Column>
    </Screen>
  );
}
