import { useCallback } from "react";
import type { Client } from "../client";
import { usePage } from "./use-page";
import { useRecord } from "./use-record";

export function useGarage(client: Client) {
  const load = useCallback(
    (page: number) => client.garage.motorcycles(page),
    [client],
  );
  return { bikes: usePage(load) };
}
export function useMotorcycle(client: Client, id: number) {
  const load = useCallback(() => client.garage.motorcycle(id), [client, id]);
  return useRecord(load);
}
export function useMaintenanceHistory(client: Client, motorcycleId: number) {
  const load = useCallback(
    (page: number) => client.garage.maintenance(motorcycleId, page),
    [client, motorcycleId],
  );
  return usePage(load);
}
export function useMaintenanceRecord(
  client: Client,
  motorcycleId: number,
  id: number,
) {
  const load = useCallback(
    () => client.garage.maintenanceRecord(motorcycleId, id),
    [client, motorcycleId, id],
  );
  return useRecord(load);
}
