import { Button, Column, Host, Text, TextInput } from "@expo/ui";
import * as Device from "expo-device";
import { useEffect, useState } from "react";
import { Platform, ScrollView } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { api, socialSignIn, type Token } from "@/auth/client";
import { useAuth } from "@/auth/auth-context";

export function SignIn() {
  const auth = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [recoveryCode, setRecoveryCode] = useState("");
  const [challenge, setChallenge] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [providers, setProviders] = useState<string[]>([]);
  const deviceName = Device.deviceName ?? `${Platform.OS} device`;
  useEffect(() => {
    api<{ providers: string[] }>("/api/v1/auth/config")
      .then((config) => setProviders(config.providers))
      .catch(() => undefined);
  }, []);

  async function act(callback: () => Promise<void>) {
    setBusy(true);
    setMessage("");
    try {
      await callback();
    } catch (failure) {
      setMessage(
        failure instanceof Error ? failure.message : "Unable to sign in.",
      );
    } finally {
      setBusy(false);
    }
  }

  if (Platform.OS === "web")
    return (
      <Host matchContents>
        <Text>
          Use the Motominator browser application to sign in on the web.
        </Text>
      </Host>
    );
  return (
    <SafeAreaView style={{ flex: 1 }}>
      <ScrollView
        contentContainerStyle={{ padding: 24 }}
        keyboardShouldPersistTaps="handled"
      >
        <Host matchContents>
          <Column spacing={16}>
            <Text textStyle={{ fontSize: 28 }}>Motominator</Text>
            <Text>Sign in to your account</Text>
            <TextInput
              placeholder="Email"
              autoComplete="email"
              autoCapitalize="none"
              keyboardType="email-address"
              onChangeText={setEmail}
              testID="login-email"
            />
            <TextInput
              placeholder="Password"
              autoComplete="current-password"
              secureTextEntry
              onChangeText={setPassword}
              testID="login-password"
            />
            {challenge && (
              <>
                <TextInput
                  placeholder="Authenticator code"
                  autoComplete="one-time-code"
                  keyboardType="number-pad"
                  onChangeText={setCode}
                />
                <TextInput
                  placeholder="Or recovery code"
                  autoCapitalize="none"
                  onChangeText={setRecoveryCode}
                />
              </>
            )}
            <Button
              disabled={busy}
              testID="login-submit"
              onPress={() =>
                void act(async () => {
                  const result = await api<Token & { two_factor?: boolean }>(
                    "/api/v1/auth/tokens",
                    "POST",
                    {
                      email,
                      password,
                      device_name: deviceName,
                      code,
                      recovery_code: recoveryCode,
                    },
                  );
                  if (result.two_factor) {
                    setChallenge(true);
                    return;
                  }
                  await auth.accept(result);
                })
              }
              label={
                busy
                  ? "Signing in…"
                  : challenge
                    ? "Verify and sign in"
                    : "Sign in"
              }
            />
            {providers.map((provider) => (
              <Button
                key={provider}
                disabled={busy}
                onPress={() =>
                  void act(async () => {
                    const token = await socialSignIn(provider, deviceName);
                    if (token) await auth.accept(token);
                    else setMessage("Sign-in cancelled.");
                  })
                }
                label={`Continue with ${provider === "github" ? "GitHub" : "Google"}`}
              />
            ))}
            {auth.error ? (
              <>
                <Text>{auth.error}</Text>
                <Button label="Retry" onPress={() => void auth.refresh()} />
              </>
            ) : null}
            {message ? <Text>{message}</Text> : null}
          </Column>
        </Host>
      </ScrollView>
    </SafeAreaView>
  );
}
