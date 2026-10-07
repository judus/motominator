import { useState } from "react";
import { Button } from "react-native";

import { ThemedText } from "@/components/themed-text";
import { ThemedView } from "@/components/themed-view";

const apiBaseUrl =
  process.env.EXPO_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000";

export function ServerStatus() {
  const [status, setStatus] = useState("Ready to connect.");
  const [checking, setChecking] = useState(false);

  async function checkServer() {
    setChecking(true);
    setStatus("Connecting…");

    try {
      const response = await fetch(`${apiBaseUrl}/api/v1/status`, {
        headers: { Accept: "application/json" },
      });
      if (!response.ok)
        throw new Error(`Server returned HTTP ${response.status}.`);
      const data = await response.json();
      if (data.name !== "Motominator" || data.status !== "ok") {
        throw new Error("Unexpected server response.");
      }
      setStatus("Connected to Motominator.");
    } catch (error) {
      setStatus(
        error instanceof Error ? error.message : "Unable to reach the server.",
      );
    } finally {
      setChecking(false);
    }
  }

  return (
    <ThemedView>
      <Button
        title={checking ? "Connecting…" : "Check server"}
        disabled={checking}
        onPress={checkServer}
      />
      <ThemedText accessibilityLiveRegion="polite">{status}</ThemedText>
    </ThemedView>
  );
}
