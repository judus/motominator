import { AiSettings } from "@/ai/ai-settings";
import { Screen } from "@/ui/components";
import { useAuth } from "@/auth/auth-context";
export default function AiSettingsScreen() {
  const { user } = useAuth();
  return (
    <Screen
      title="AI settings"
      subtitle="Your provider, model and private API key"
    >
      <AiSettings key={user?.id} />
    </Screen>
  );
}
