import {
  Alert,
  Button,
  Fieldset,
  Group,
  NativeSelect,
  Paper,
  Stack,
  Text,
  TextInput,
} from "@mantine/core";
import { type FormEvent } from "react";
import { client } from "../client";
import { useAiSettings } from "@motominator/client/react";

export function AiSettings({ verified }: { verified: boolean }) {
  const {
    settings,
    provider,
    model,
    key,
    busy,
    message,
    error,
    errors,
    changed,
    needsKey,
    perform,
    retry,
    changeProvider,
    changeModel,
    changeKey,
  } = useAiSettings(client, verified);
  return (
    <Paper
      component="section"
      withBorder
      p="lg"
      radius="md"
      aria-label="AI provider settings"
    >
      <Stack>
        <Text size="sm" c="dimmed">
          Use your own provider account. Keys are stored encrypted on the
          server. Manual maintenance entry works without AI.
        </Text>
        {!settings ? (
          error ? (
            <Button variant="outline" onClick={retry}>
              Retry AI settings
            </Button>
          ) : (
            <Text role="status">Loading AI settings…</Text>
          )
        ) : (
          <>
            <Text>
              {settings.data
                ? `Saved key: ${settings.data.key_hint}`
                : "No AI key configured."}
            </Text>
            {!verified && (
              <Alert>
                Verify your email before saving or testing AI settings.
              </Alert>
            )}
            <form
              onSubmit={(event: FormEvent) => {
                event.preventDefault();
                void perform("save");
              }}
            >
              <Fieldset disabled={busy || !verified} variant="unstyled">
                <Stack>
                  <NativeSelect
                    label="AI provider"
                    value={provider}
                    onChange={(event) =>
                      changeProvider(event.currentTarget.value)
                    }
                    data={settings.providers.map((value) => ({
                      value: value.id,
                      label: value.label,
                    }))}
                    error={errors.provider?.[0]}
                  />
                  <TextInput
                    label="AI model"
                    description="Enter a model ID available to your provider account."
                    value={model}
                    onChange={(event) => changeModel(event.currentTarget.value)}
                    required
                    maxLength={100}
                    error={errors.model?.[0]}
                  />
                  <TextInput
                    label={needsKey ? "API key" : "Replace API key"}
                    type="password"
                    autoComplete="off"
                    value={key}
                    onChange={(event) => changeKey(event.currentTarget.value)}
                    required={needsKey}
                    maxLength={512}
                    description={
                      needsKey
                        ? "Enter your provider API key."
                        : "Leave blank to keep the saved key."
                    }
                    error={errors.api_key?.[0]}
                  />
                  <Button type="submit" w="fit-content">
                    Save AI settings
                  </Button>
                </Stack>
              </Fieldset>
            </form>
            {settings.data && (
              <>
                <Text size="sm" c="dimmed">
                  Testing sends a small text request and may incur provider
                  charges. It does not check invoice-reading support.
                </Text>
                {changed && (
                  <Text size="sm">Save your changes before testing.</Text>
                )}
                <Group>
                  <Button
                    variant="outline"
                    disabled={busy || !verified || changed}
                    onClick={() => void perform("test")}
                  >
                    Test AI connection
                  </Button>
                  <Button
                    color="red"
                    variant="outline"
                    disabled={busy}
                    onClick={() => void perform("remove")}
                  >
                    Remove AI key
                  </Button>
                </Group>
              </>
            )}
          </>
        )}
        {error && (
          <Alert color="red" role="alert">
            {error}
          </Alert>
        )}
        {message && <Alert role="status">{message}</Alert>}
      </Stack>
    </Paper>
  );
}
