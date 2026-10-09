import { useState } from "react";
import type { TwoFactorOperation } from "@motominator/client";
import { Button, Field, Screen, Section, Text } from "@/ui/components";
import { AccountFeedback, useAccountEditor } from "./editor";

export default function TwoFactorScreen() {
  const account = useAccountEditor();
  const [password, setPassword] = useState(""),
    [code, setCode] = useState("");
  const enabled = account.user?.two_factor_enabled,
    pending = account.user?.two_factor_pending;
  async function run(operation: TwoFactorOperation) {
    if (await account.twoFactor(password, operation, code)) {
      setPassword("");
      setCode("");
    }
  }
  const action = (operation: TwoFactorOperation, label: string) => (
    <Button
      intent={
        operation === "disable"
          ? "danger"
          : operation === "codes"
            ? "secondary"
            : "primary"
      }
      label={label}
      disabled={account.busy || !password}
      onPress={() => void run(operation)}
    />
  );
  return (
    <Screen
      title="Two-factor authentication"
      subtitle="Authenticator and recovery codes"
    >
      <Section>
        <Text>
          {enabled ? "Enabled" : pending ? "Awaiting confirmation" : "Disabled"}
        </Text>
        <Field
          id="two-factor-password"
          label="Confirm password"
          secureTextEntry
          autoComplete="current-password"
          value={password}
          onChangeText={setPassword}
          disabled={account.busy}
        />
        {!enabled &&
          action(
            "enable",
            pending ? "Show setup secret" : "Set up authenticator",
          )}
        {pending && (
          <>
            <Field
              id="two-factor-code"
              label="Authenticator code"
              value={code}
              onChangeText={setCode}
              disabled={account.busy}
              keyboardType="number-pad"
              autoComplete="one-time-code"
            />
            {action("confirm", "Confirm authenticator")}
          </>
        )}
        {(enabled || pending) && (
          <>
            {action("codes", "Show recovery codes")}
            {action("regenerate", "Regenerate recovery codes")}
            {action("disable", "Disable two-factor authentication")}
          </>
        )}
      </Section>
      {account.secret && (
        <Section>
          <Text>
            Add this secret to your authenticator, then enter its current code
            to confirm.
          </Text>
          <Text selectable>{account.secret}</Text>
        </Section>
      )}
      {account.codes.length > 0 && (
        <Section>
          <Text>
            Store these recovery codes securely. Each can be used once.
          </Text>
          {account.codes.map((value) => (
            <Text selectable key={value}>
              {value}
            </Text>
          ))}
          <Button
            intent="secondary"
            label="Hide recovery codes"
            onPress={account.hideRecovery}
          />
        </Section>
      )}
      <AccountFeedback {...account} />
    </Screen>
  );
}
