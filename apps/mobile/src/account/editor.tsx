import { useAccountSettings } from "@motominator/client/react";
import { useAuth } from "@/auth/auth-context";
import { useClient } from "@/client";
import { Column, Text } from "@/ui/components";

export function useAccountEditor() {
  const auth = useAuth();
  return { user: auth.user, ...useAccountSettings(useClient(), auth.refresh) };
}
export function AccountFeedback({
  error,
  message,
}: {
  error: string;
  message: string;
}) {
  return (
    <Column gap="$2">
      {error && <Text accessibilityRole="alert">{error}</Text>}
      {message && <Text accessibilityLiveRegion="polite">{message}</Text>}
    </Column>
  );
}
