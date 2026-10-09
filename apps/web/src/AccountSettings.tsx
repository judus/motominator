import {
  Alert,
  Button,
  Fieldset,
  NativeSelect,
  Paper,
  Stack,
  Text,
  TextInput,
} from "@mantine/core";
import { useState, type ReactNode } from "react";
import { useAccountSettings } from "@motominator/client/react";
import { apiBaseUrl, request, type User } from "./api";
import { client } from "./client";

function SettingsForm({
  busy,
  reset = true,
  onSubmit,
  children,
}: {
  busy: boolean;
  reset?: boolean;
  children: ReactNode;
  onSubmit: (data: Record<string, string>) => Promise<boolean>;
}) {
  return (
    <Paper
      component="form"
      withBorder
      p="lg"
      radius="md"
      onSubmit={async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = Object.fromEntries(
          [...new FormData(form)].filter(
            (entry): entry is [string, string] => typeof entry[1] === "string",
          ),
        );
        if ((await onSubmit(data)) && reset) form.reset();
      }}
    >
      <Fieldset disabled={busy} variant="unstyled">
        <Stack>{children}</Stack>
      </Fieldset>
    </Paper>
  );
}

export function AccountSettings({
  user,
  refresh,
  providers,
  section,
}: {
  user: User;
  refresh: () => Promise<void>;
  providers: string[];
  section: "profile" | "password" | "two-factor" | "social" | "devices";
}) {
  const settings = useAccountSettings(client, refresh);
  const [profileEmail, setProfileEmail] = useState(user.email);
  const [profilePassword, setProfilePassword] = useState("");
  const changingEmail =
    profileEmail.trim().toLowerCase() !== user.email.toLowerCase();
  const { busy, secret, codes, devices, message, error } = settings;
  const availableProviders = [
    ...new Set([...providers, ...(user.providers ?? [])]),
  ];
  return (
    <Stack
      component="section"
      aria-label="Account settings"
      gap="lg"
      maw={720}
      w="100%"
    >
      {section === "profile" && (
        <SettingsForm
          busy={busy}
          reset={false}
          onSubmit={async (data) => {
            const saved = await settings.profile({
              name: data.name,
              email: profileEmail,
              ...(changingEmail ? { current_password: profilePassword } : {}),
            });
            if (saved) setProfilePassword("");
            return saved;
          }}
        >
          <TextInput
            label="Name"
            name="name"
            autoComplete="name"
            defaultValue={user.name}
            required
          />
          <TextInput
            label="Email"
            name="email"
            type="email"
            autoComplete="email"
            value={profileEmail}
            onChange={(event) => {
              setProfileEmail(event.currentTarget.value);
              setProfilePassword("");
            }}
            required
          />
          {changingEmail && (
            <TextInput
              label="Current password to change email"
              name="current_password"
              type="password"
              autoComplete="current-password"
              value={profilePassword}
              onChange={(event) =>
                setProfilePassword(event.currentTarget.value)
              }
              required
            />
          )}
          <Text size="sm" c="dimmed">
            Changing your email requires your current password and verifying the
            new address.
          </Text>
          <Button type="submit" disabled={busy} w="fit-content">
            Save profile
          </Button>
        </SettingsForm>
      )}
      {section === "password" && (
        <SettingsForm
          busy={busy}
          onSubmit={(data) =>
            settings.password({
              current_password: data.current_password,
              password: data.password,
              password_confirmation: data.password_confirmation,
            })
          }
        >
          <TextInput
            label="Current password"
            name="current_password"
            type="password"
            autoComplete="current-password"
            required
          />
          <TextInput
            label="New password"
            name="password"
            type="password"
            autoComplete="new-password"
            minLength={8}
            required
          />
          <TextInput
            label="Confirm new password"
            name="password_confirmation"
            type="password"
            autoComplete="new-password"
            required
          />
          <Text size="sm" c="dimmed">
            All mobile devices will be signed out.
          </Text>
          <Button type="submit" disabled={busy} w="fit-content">
            Change password
          </Button>
        </SettingsForm>
      )}
      {section === "two-factor" && (
        <>
          <Text>
            {user.two_factor_enabled
              ? "Enabled"
              : user.two_factor_pending
                ? "Awaiting confirmation"
                : "Disabled"}
          </Text>
          <SettingsForm
            busy={busy}
            onSubmit={(data) =>
              settings.twoFactor(
                data.password,
                data.action === "disable" ||
                  data.action === "regenerate" ||
                  data.action === "codes"
                  ? data.action
                  : "enable",
              )
            }
          >
            <TextInput
              label="Confirm password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
            />
            <NativeSelect label="Action" name="action">
              {!user.two_factor_enabled && (
                <option value="enable">Set up authenticator</option>
              )}
              {(user.two_factor_enabled || user.two_factor_pending) && (
                <>
                  <option value="codes">Show recovery codes</option>
                  <option value="regenerate">Regenerate recovery codes</option>
                  <option value="disable">
                    Disable two-factor authentication
                  </option>
                </>
              )}
            </NativeSelect>
            <Button type="submit" disabled={busy} w="fit-content">
              Update two-factor authentication
            </Button>
          </SettingsForm>
          {secret && (
            <p>
              Add this secret to your authenticator: <code>{secret}</code>
            </p>
          )}
          {user.two_factor_pending && (
            <SettingsForm
              busy={busy}
              onSubmit={(data) =>
                settings.twoFactor(data.password, "confirm", data.code)
              }
            >
              <TextInput
                label="Password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
              />
              <TextInput
                label="Authenticator code"
                name="code"
                autoComplete="one-time-code"
                inputMode="numeric"
                required
              />
              <Button type="submit" disabled={busy} w="fit-content">
                Confirm authenticator
              </Button>
            </SettingsForm>
          )}
          {codes.length > 0 && (
            <div>
              <p>Store these recovery codes securely. Each can be used once.</p>
              <ul>
                {codes.map((code) => (
                  <li key={code}>
                    <code>{code}</code>
                  </li>
                ))}
              </ul>
              <Button variant="outline" onClick={settings.hideRecovery}>
                Hide recovery codes
              </Button>
            </div>
          )}
        </>
      )}
      {section === "social" && (
        <>
          <Text>Linked: {user.providers?.join(", ") || "none"}</Text>
          {availableProviders.length === 0 ? (
            <Text>No social sign-in providers are configured yet.</Text>
          ) : (
            <SettingsForm
              busy={busy}
              onSubmit={(data) =>
                data.action === "unlink"
                  ? settings.unlinkSocial(data.provider, data.password)
                  : settings.perform(async () => {
                      await request("/user/confirm-password", "POST", {
                        password: data.password,
                      });
                      window.location.assign(
                        `${apiBaseUrl}/auth/${data.provider}/redirect?intent=link`,
                      );
                    })
              }
            >
              <NativeSelect label="Provider" name="provider">
                {availableProviders.map((provider) => (
                  <option key={provider} value={provider}>
                    {provider === "github" ? "GitHub" : "Google"}
                  </option>
                ))}
              </NativeSelect>
              <NativeSelect label="Action" name="action">
                <option value="link">Link account</option>
                <option value="unlink">Unlink account</option>
              </NativeSelect>
              <TextInput
                label="Password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
              />
              <Button type="submit" disabled={busy} w="fit-content">
                Update social account
              </Button>
            </SettingsForm>
          )}
        </>
      )}
      {section === "devices" && (
        <>
          <Text size="sm" c="dimmed">
            Mobile API tokens; browser sessions are managed separately.
          </Text>
          <Button
            variant="outline"
            disabled={busy}
            w="fit-content"
            onClick={() => void settings.loadDevices()}
          >
            Load devices
          </Button>
          {message === "Device list updated." && devices.length === 0 && (
            <Text>No signed-in mobile devices.</Text>
          )}
          {devices.map((device) => (
            <SettingsForm
              key={device.id}
              busy={busy}
              onSubmit={(data) =>
                settings.revokeDevice(device.id, data.password)
              }
            >
              <Text>
                {device.name} · expires {device.expires_at ?? "unknown"}
              </Text>
              <TextInput
                label="Password to revoke device"
                name="password"
                type="password"
                autoComplete="current-password"
                required
              />
              <Button
                color="red"
                variant="outline"
                type="submit"
                disabled={busy}
                w="fit-content"
              >
                Revoke {device.name}
              </Button>
            </SettingsForm>
          ))}
        </>
      )}
      {message && <Alert role="status">{message}</Alert>}
      {error && (
        <Alert role="alert" color="red">
          {error}
        </Alert>
      )}
    </Stack>
  );
}
