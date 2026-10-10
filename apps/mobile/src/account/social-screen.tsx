import { useCallback, useState } from "react";
import { useRecord } from "@motominator/client/react";
import { Button, Field, Screen, Section, Text } from "@/ui/components";
import { api, linkSocialAccount } from "@/auth/client";
import { AccountFeedback, useAccountEditor } from "./editor";

export default function SocialScreen() {
  const account = useAccountEditor(),
    [password, setPassword] = useState("");
  const load = useCallback(
    () => api<{ providers: string[] }>("/api/v1/auth/config"),
    [],
  );
  const config = useRecord(load);
  const linked = account.user?.providers ?? [];
  const providers = [
    ...new Set([...(config.result?.providers ?? []), ...linked]),
  ];
  async function link(provider: string) {
    if (
      await account.perform(async () => {
        const completed = await linkSocialAccount(provider, password);
        if (!completed) throw new Error("Account linking was cancelled.");
      })
    )
      setPassword("");
  }
  async function unlink(provider: string) {
    if (await account.unlinkSocial(provider, password)) setPassword("");
  }
  return (
    <Screen title="Social accounts" subtitle="Manage linked sign-in providers">
      <Section>
        <Text>Linked: {linked.join(", ") || "none"}</Text>
        <Text color="$color11">
          Linking opens your provider sign-in page. Native callbacks require a
          development build; Expo Go cannot complete this flow.
        </Text>
        <Field
          id="social-password"
          label="Confirm password"
          secureTextEntry
          autoComplete="current-password"
          value={password}
          onChangeText={setPassword}
          disabled={account.busy}
        />
        {config.loading && <Text>Loading providers…</Text>}
        {config.error && (
          <>
            <Text accessibilityRole="alert">{config.error}</Text>
            <Button
              intent="secondary"
              label="Retry providers"
              onPress={config.reload}
            />
          </>
        )}
        {config.result && providers.length === 0 && (
          <Text>No social sign-in providers are configured yet.</Text>
        )}
      </Section>
      {providers.map((provider) => (
        <Section key={provider}>
          <Text fontWeight="600">
            {provider === "github" ? "GitHub" : "Google"}
          </Text>
          {linked.includes(provider) ? (
            <Button
              intent="danger"
              label={`Unlink ${provider}`}
              disabled={account.busy || !password}
              onPress={() => void unlink(provider)}
            />
          ) : (
            <Button
              label={`Link ${provider}`}
              disabled={account.busy || !password}
              onPress={() => void link(provider)}
            />
          )}
        </Section>
      ))}
      <AccountFeedback {...account} />
    </Screen>
  );
}
