export interface Motorcycle {
  id: number;
  make: string;
  model: string;
  year: number;
  nickname: string | null;
  odometer_km: number;
}
export interface MaintenanceRecord {
  id: number;
  motorcycle_id: number;
  performed_on: string;
  odometer_km: number;
  title: string;
  notes: string | null;
  cost_amount: string | null;
  currency: string | null;
}
export interface Page<T> {
  data: T[];
  meta: { current_page: number; last_page: number };
}
export function motorcycleName(bike: Motorcycle): string {
  return `${bike.make} ${bike.model} (${bike.year})${bike.nickname ? ` — ${bike.nickname}` : ""}`;
}
export function localDate(): string {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
}

export interface AiSettings {
  data: { provider: string; model: string; key_hint: string } | null;
  providers: { id: string; label: string }[];
}

export type GarageData = Record<string, string | null>;
export interface UserIdentity {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
}

export interface AccountUser extends UserIdentity {
  two_factor_enabled: boolean;
  two_factor_pending: boolean;
  providers: string[];
}
