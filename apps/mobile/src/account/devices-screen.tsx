import { useState } from "react";
import { Button, Field, Screen, Section, Text } from "@/ui/components";
import { AccountFeedback, useAccountEditor } from "./editor";

export default function DevicesScreen() {
  const account = useAccountEditor(),
    [password, setPassword] = useState("");
  async function revoke(id: number) {
    if (await account.revokeDevice(id, password)) setPassword("");
  }
  return (
    <Screen
      title="Signed-in devices"
      subtitle="Review and revoke mobile device access"
    >
      <Section>
        <Text>
          This list contains mobile API tokens. Revoking the token for this
          device signs you out.
        </Text>
        <Button
          intent="secondary"
          label="Load devices"
          disabled={account.busy}
          onPress={() => void account.loadDevices()}
        />
        <Field
          id="device-password"
          label="Password to revoke device"
          secureTextEntry
          autoComplete="current-password"
          value={password}
          onChangeText={setPassword}
          disabled={account.busy}
        />
      </Section>
      {account.message === "Device list updated." &&
        account.devices.length === 0 && (
          <Text>No signed-in mobile devices.</Text>
        )}
      {account.devices.map((device) => (
        <Section key={device.id}>
          <Text fontWeight="600">
            {device.name}
            {device.current_device ? " (this device)" : ""}
          </Text>
          <Text>Last used: {device.last_used_at ?? "Not used yet"}</Text>
          <Text>Expires: {device.expires_at ?? "Unknown"}</Text>
          <Button
            intent="danger"
            label={`Revoke ${device.name}`}
            disabled={account.busy || !password}
            onPress={() => void revoke(device.id)}
          />
        </Section>
      ))}
      <AccountFeedback {...account} />
    </Screen>
  );
}
