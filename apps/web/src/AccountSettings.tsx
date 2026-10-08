import { useState, type FormEvent } from "react";
import { apiBaseUrl, request, type User } from "./api";

export function AccountSettings({
  user,
  refresh,
  providers,
}: {
  user: User;
  refresh: () => Promise<void>;
  providers: string[];
}) {
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [secret, setSecret] = useState("");
  const [codes, setCodes] = useState<string[]>([]);
  const [devices, setDevices] = useState<
    { id: number; name: string; expires_at: string | null }[]
  >([]);

  async function submit(
    event: FormEvent<HTMLFormElement>,
    action: (data: Record<string, string>) => Promise<void>,
  ) {
    event.preventDefault();
    const data = Object.fromEntries(
      new FormData(event.currentTarget),
    ) as Record<string, string>;
    setBusy(true);
    setMessage("");
    try {
      await action(data);
      await refresh();
      setMessage("Account updated.");
    } catch (error) {
      setMessage(
        error instanceof Error ? error.message : "Unable to update account.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function confirmed(password: string, action: () => Promise<unknown>) {
    await request("/user/confirm-password", "POST", { password });
    await action();
  }

  return (
    <section aria-labelledby="settings-title">
      <h2 id="settings-title">Account settings</h2>
      <form
        onSubmit={(event) =>
          submit(event, async (data) => {
            await request("/user/profile-information", "PUT", data);
          })
        }
      >
        <label>
          Name
          <input
            name="name"
            autoComplete="name"
            defaultValue={user.name}
            required
          />
        </label>
        <label>
          Email
          <input
            name="email"
            type="email"
            autoComplete="email"
            defaultValue={user.email}
            required
          />
        </label>
        <button disabled={busy}>Save profile</button>
      </form>
      <form
        onSubmit={(event) =>
          submit(event, async (data) => {
            await request("/user/password", "PUT", data);
          })
        }
      >
        <label>
          Current password
          <input
            name="current_password"
            type="password"
            autoComplete="current-password"
            required
          />
        </label>
        <label>
          New password
          <input
            name="password"
            type="password"
            autoComplete="new-password"
            minLength={8}
            required
          />
        </label>
        <label>
          Confirm new password
          <input
            name="password_confirmation"
            type="password"
            autoComplete="new-password"
            required
          />
        </label>
        <button disabled={busy}>Change password</button>
      </form>
      <h3>Two-factor authentication</h3>
      <p>
        {user.two_factor_enabled
          ? "Enabled"
          : user.two_factor_pending
            ? "Awaiting confirmation"
            : "Disabled"}
      </p>
      <form
        onSubmit={(event) =>
          submit(event, async (data) =>
            confirmed(data.password, async () => {
              if (data.action === "enable") {
                await request("/user/two-factor-authentication", "POST");
                const result = await request<{ secretKey: string }>(
                  "/user/two-factor-secret-key",
                );
                setSecret(result.secretKey);
                setCodes(
                  await request<string[]>("/user/two-factor-recovery-codes"),
                );
              } else if (data.action === "disable") {
                await request("/user/two-factor-authentication", "DELETE");
                setSecret("");
                setCodes([]);
              } else {
                if (data.action === "regenerate")
                  await request("/user/two-factor-recovery-codes", "POST");
                setCodes(
                  await request<string[]>("/user/two-factor-recovery-codes"),
                );
              }
            }),
          )
        }
      >
        <label>
          Confirm password
          <input
            name="password"
            type="password"
            autoComplete="current-password"
            required
          />
        </label>
        <label>
          Action
          <select name="action">
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
          </select>
        </label>
        <button disabled={busy}>Update two-factor authentication</button>
      </form>
      {secret && (
        <p>
          Add this secret to your authenticator: <code>{secret}</code>
        </p>
      )}
      {user.two_factor_pending && (
        <form
          onSubmit={(event) =>
            submit(event, async (data) =>
              confirmed(data.password, async () => {
                await request(
                  "/user/confirmed-two-factor-authentication",
                  "POST",
                  { code: data.code },
                );
                setSecret("");
              }),
            )
          }
        >
          <label>
            Password
            <input
              name="password"
              type="password"
              autoComplete="current-password"
              required
            />
          </label>
          <label>
            Authenticator code
            <input
              name="code"
              autoComplete="one-time-code"
              inputMode="numeric"
              required
            />
          </label>
          <button disabled={busy}>Confirm authenticator</button>
        </form>
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
          <button onClick={() => setCodes([])}>Hide recovery codes</button>
        </div>
      )}
      {providers.length > 0 && (
        <>
          <h3>Social accounts</h3>
          <form
            onSubmit={(event) =>
              submit(event, async (data) =>
                confirmed(data.password, async () => {
                  if (data.action === "unlink") {
                    await request(`/auth/${data.provider}`, "DELETE");
                  } else
                    window.location.assign(
                      `${apiBaseUrl}/auth/${data.provider}/redirect?intent=link`,
                    );
                }),
              )
            }
          >
            <p>Linked: {user.providers?.join(", ") || "none"}</p>
            <label>
              Provider
              <select name="provider">
                {providers.map((provider) => (
                  <option key={provider} value={provider}>
                    {provider === "github" ? "GitHub" : "Google"}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Action
              <select name="action">
                <option value="link">Link account</option>
                <option value="unlink">Unlink account</option>
              </select>
            </label>
            <label>
              Password
              <input
                name="password"
                type="password"
                autoComplete="current-password"
                required
              />
            </label>
            <button disabled={busy}>Update social account</button>
          </form>
        </>
      )}
      <h3>Signed-in devices</h3>
      <form
        onSubmit={(event) =>
          submit(event, async () => {
            setDevices(await request("/user/devices"));
          })
        }
      >
        <button disabled={busy}>Load devices</button>
      </form>
      {devices.map((device) => (
        <form
          key={device.id}
          onSubmit={(event) =>
            submit(event, async (data) =>
              confirmed(data.password, async () => {
                await request(`/user/devices/${device.id}`, "DELETE");
                setDevices(await request("/user/devices"));
              }),
            )
          }
        >
          <p>
            {device.name} · expires {device.expires_at ?? "unknown"}
          </p>
          <label>
            Password to revoke device
            <input
              name="password"
              type="password"
              autoComplete="current-password"
              required
            />
          </label>
          <button disabled={busy}>Revoke {device.name}</button>
        </form>
      ))}
      <p role="status">{message}</p>
    </section>
  );
}
