import { Button, Column, Field, Section, Text, XStack } from "@/ui/components";
import { useAiSettings } from "@motominator/client/react";
import { useClient, aiSettingsLoadError } from "@/client";
import { useAuth } from "@/auth/auth-context";

export function AiSettings() {
  const { user } = useAuth();
  const verified = !!user?.email_verified_at;
  const client = useClient();
  const {
    settings,
    provider,
    model,
    key,
    revision,
    busy,
    message,
    error,
    changed,
    needsKey,
    perform,
    retry,
    changeProvider,
    changeModel,
    changeKey,
  } = useAiSettings(client, verified, aiSettingsLoadError);
  return (
    <Section>
      <Text color="$color11">
        Use your own provider account. Keys are stored encrypted on the server.
        Manual maintenance entry works without AI.
      </Text>
      {!settings ? (
        error ? (
          <Button
            intent="secondary"
            label="Retry AI settings"
            onPress={retry}
          />
        ) : (
          <Text>Loading AI settings…</Text>
        )
      ) : (
        <>
          <Text>
            {settings.data
              ? `Saved key: ${settings.data.key_hint}`
              : "No AI key configured."}
          </Text>
          {!verified && (
            <Text>Verify your email before saving or testing AI settings.</Text>
          )}
          <Column gap="$2">
            <Text>AI provider</Text>
            <XStack
              gap="$2"
              flexWrap="wrap"
              accessibilityRole="radiogroup"
              accessibilityLabel="AI provider"
            >
              {settings.providers.map((value) => (
                <Button
                  key={value.id}
                  testID={`ai-provider-${value.id}`}
                  label={value.label}
                  intent={provider === value.id ? "primary" : "secondary"}
                  accessibilityRole="radio"
                  accessibilityState={{
                    checked: provider === value.id,
                    disabled: busy || !verified,
                  }}
                  disabled={busy || !verified}
                  onPress={() => changeProvider(value.id)}
                />
              ))}
            </XStack>
          </Column>
          <Field
            id="ai-model"
            label="AI model"
            testID="ai-model"
            placeholder="Model ID from your provider"
            value={model}
            onChangeText={changeModel}
            disabled={busy || !verified}
            autoCorrect={false}
            autoCapitalize="none"
            maxLength={100}
          />
          <Field
            key={`key-${revision}-${provider}`}
            id="ai-key"
            label={needsKey ? "API key" : "Replace API key"}
            testID="ai-key"
            placeholder={
              needsKey
                ? "Enter your provider API key"
                : "Leave blank to keep the saved key"
            }
            value={key}
            secureTextEntry
            autoComplete="off"
            autoCorrect={false}
            autoCapitalize="none"
            maxLength={512}
            onChangeText={changeKey}
            disabled={busy || !verified}
          />
          <Button
            label={busy ? "Working…" : "Save AI settings"}
            disabled={
              busy || !verified || !model.trim() || (needsKey && !key.trim())
            }
            onPress={() => void perform("save")}
          />
          {settings.data && (
            <>
              <Text color="$color11">
                Testing sends a small text request and may incur provider
                charges. It does not check invoice-reading support.
              </Text>
              {changed && <Text>Save your changes before testing.</Text>}
              <Button
                intent="secondary"
                label="Test AI connection"
                disabled={busy || !verified || changed}
                onPress={() => void perform("test")}
              />
              <Button
                intent="danger"
                label="Remove AI key"
                disabled={busy}
                onPress={() => void perform("remove")}
              />
            </>
          )}
        </>
      )}
      {error ? <Text accessibilityRole="alert">{error}</Text> : null}
      {message ? <Text accessibilityLiveRegion="polite">{message}</Text> : null}
    </Section>
  );
}
