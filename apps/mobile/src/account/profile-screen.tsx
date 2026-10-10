import { useState } from "react";
import { Button, Field, Screen, Section, Text } from "@/ui/components";
import { AccountFeedback, useAccountEditor } from "./editor";

export default function ProfileScreen() {
  const account = useAccountEditor();
  const [name, setName] = useState(account.user?.name ?? ""),
    [email, setEmail] = useState(account.user?.email ?? ""),
    [password, setPassword] = useState("");
  const changingEmail =
    email.trim().toLowerCase() !== account.user?.email.toLowerCase();
  async function save() {
    if (
      await account.profile({
        name,
        email,
        ...(changingEmail ? { current_password: password } : {}),
      })
    )
      setPassword("");
  }
  return (
    <Screen title="Profile" subtitle="Your name and email address">
      <Section>
        <Field
          id="account-name"
          label="Name"
          value={name}
          onChangeText={setName}
          disabled={account.busy}
          autoComplete="name"
        />
        <Field
          id="account-email"
          label="Email"
          value={email}
          onChangeText={(value) => {
            setEmail(value);
            setPassword("");
          }}
          disabled={account.busy}
          autoComplete="email"
          keyboardType="email-address"
          autoCapitalize="none"
        />
        {changingEmail && (
          <Field
            id="profile-password"
            label="Current password to change email"
            value={password}
            onChangeText={setPassword}
            disabled={account.busy}
            secureTextEntry
            autoComplete="current-password"
            autoCapitalize="none"
            autoCorrect={false}
          />
        )}
        <Text color="$color11">
          Changing your email requires your current password and verifying the
          new address.
        </Text>
        <Button
          label={account.busy ? "Saving…" : "Save profile"}
          disabled={account.busy}
          onPress={() => void save()}
        />
      </Section>
      {!account.user?.email_verified_at && (
        <Section>
          <Text>
            Email not verified. Follow the link in your verification email.
          </Text>
          <Button
            intent="secondary"
            label="Resend verification email"
            disabled={account.busy}
            onPress={() => void account.verification()}
          />
        </Section>
      )}
      <AccountFeedback {...account} />
    </Screen>
  );
}
