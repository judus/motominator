import { Link } from "expo-router";
import { Screen, Button, Text } from "@/ui/components";
export default function NotFound() {
  return (
    <Screen title="Page not found">
      <Text>This page does not exist.</Text>
      <Link href="/" asChild>
        <Button intent="secondary" label="Go home" />
      </Link>
    </Screen>
  );
}
