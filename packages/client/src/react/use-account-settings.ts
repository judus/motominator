import { useEffect, useRef, useState } from "react";
import { ApiError, failureMessage } from "../errors";
import type { createClient } from "../client";
import type {
  AccountDevice,
  PasswordInput,
  ProfileInput,
  TwoFactorOperation,
} from "../accounts";

/** Common mutations; navigation, credential storage and browser handoffs stay app-owned. */
export function useAccountSettings(
  client: ReturnType<typeof createClient>,
  refresh: () => Promise<void>,
) {
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [secret, setSecret] = useState<string | null>(null);
  const [codes, setCodes] = useState<string[]>([]);
  const [devices, setDevices] = useState<AccountDevice[]>([]);
  const pending = useRef(false),
    active = useRef(true);
  useEffect(() => {
    active.current = true;
    return () => {
      active.current = false;
    };
  }, []);

  async function perform(
    operation: () => Promise<unknown>,
    success = "Account updated.",
    refreshAccount = true,
  ) {
    if (pending.current) return false;
    pending.current = true;
    setBusy(true);
    setMessage("");
    setError("");
    try {
      await operation();
      if (refreshAccount && active.current) await refresh();
      if (active.current) setMessage(success);
      return active.current;
    } catch (failure) {
      if (active.current)
        setError(
          failure instanceof ApiError && failure.status === 403
            ? "This device needs account permissions. Sign out and sign in again, then retry."
            : failureMessage(
                failure,
                "Unable to update account. Please retry.",
              ),
        );
      return false;
    } finally {
      pending.current = false;
      if (active.current) setBusy(false);
    }
  }
  async function readDevices() {
    const result = await client.account.devices();
    if (active.current) setDevices(result);
  }
  return {
    busy,
    message,
    error,
    secret,
    codes,
    devices,
    perform,
    profile: (input: ProfileInput) =>
      perform(() => client.account.profile(input)),
    password: (input: PasswordInput) =>
      perform(() => client.account.password(input), "Password changed."),
    verification: () =>
      perform(client.account.verification, "Verification email sent.", false),
    loadDevices: () => perform(readDevices, "Device list updated.", false),
    revokeDevice: (id: number, password: string) =>
      perform(
        async () => {
          const result = await client.account.revokeDevice(id, password);
          if (result.current_device) await refresh();
          else if (active.current) await readDevices();
        },
        "Device revoked.",
        false,
      ),
    unlinkSocial: (provider: string, password: string) =>
      perform(() => client.account.unlinkSocial(provider, password)),
    twoFactor: (
      password: string,
      operation: TwoFactorOperation,
      code?: string,
    ) =>
      perform(async () => {
        const result = await client.account.twoFactor(
          password,
          operation,
          code,
        );
        if (active.current) {
          setSecret(result.secret);
          setCodes(result.codes);
        }
      }),
    hideRecovery: () => {
      setCodes([]);
      setSecret(null);
    },
  };
}
