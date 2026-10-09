import { useLocalSearchParams, Link } from "expo-router";
import type { ReactNode } from "react";
import { useMotorcycle } from "@motominator/client/react";
import type { Motorcycle } from "@motominator/client";
import { useClient } from "@/client";
import { useAuth } from "@/auth/auth-context";
import { useRefreshOnFocus } from "@/navigation/use-refresh-on-focus";
import { routeId } from "@/navigation/routes";
import { Button, Screen, Text } from "@/ui/components";
import { LoadState } from "./components";
export function MotorcycleRoute({
  children,
}: {
  children: (
    bike: Motorcycle,
    verified: boolean,
    reload: () => void,
  ) => ReactNode;
}) {
  const id = routeId(useLocalSearchParams().motorcycleId);
  return id === null ? (
    <Screen title="Motorcycle not found">
      <Text>Invalid motorcycle address.</Text>
      <Link href="/garage/motorcycles" asChild>
        <Button intent="secondary" label="Your motorcycles" />
      </Link>
    </Screen>
  ) : (
    <LoadedMotorcycle key={id} id={id}>
      {children}
    </LoadedMotorcycle>
  );
}
function LoadedMotorcycle({
  children,
  id,
}: {
  id: number;
  children: (
    bike: Motorcycle,
    verified: boolean,
    reload: () => void,
  ) => ReactNode;
}) {
  const resource = useMotorcycle(useClient(), id);
  useRefreshOnFocus(resource.reload);
  const verified = useAuth().user?.email_verified_at != null;
  return resource.result ? (
    children(resource.result, verified, resource.reload)
  ) : (
    <Screen title="Motorcycle">
      <LoadState {...resource} retry={resource.reload} />
    </Screen>
  );
}
