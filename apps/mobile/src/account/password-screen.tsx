import { useState } from "react";
import { Button, Field, Screen, Section, Text } from "@/ui/components";
import { AccountFeedback, useAccountEditor } from "./editor";

export default function PasswordScreen() {
  const account = useAccountEditor();
  const [current, setCurrent] = useState(""),
    [password, setPassword] = useState(""),
    [confirmation, setConfirmation] = useState("");
  async function save() {
    if (
      await account.password({
        current_password: current,
        password,
        password_confirmation: confirmation,
      })
    ) {
      setCurrent("");
      setPassword("");
      setConfirmation("");
    }
  }
  return (
    <Screen title="Password" subtitle="Change your sign-in password">
      <Section>
        <Text>
          Changing your password signs out all mobile devices, including this
          one. Sign in again with the new password.
        </Text>
        <Field
          id="account-current-password"
          label="Current password"
          value={current}
          onChangeText={setCurrent}
          disabled={account.busy}
          secureTextEntry
          autoComplete="current-password"
        />
        <Field
          id="account-password"
          label="New password"
          value={password}
          onChangeText={setPassword}
          disabled={account.busy}
          secureTextEntry
          autoComplete="new-password"
        />
        <Field
          id="account-password-confirmation"
          label="Confirm new password"
          value={confirmation}
          onChangeText={setConfirmation}
          disabled={account.busy}
          secureTextEntry
          autoComplete="new-password"
        />
        <Button
          label={account.busy ? "Saving…" : "Change password"}
          disabled={account.busy}
          onPress={() => void save()}
        />
      </Section>
      <AccountFeedback {...account} />
    </Screen>
  );
}
