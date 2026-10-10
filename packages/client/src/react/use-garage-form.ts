import { useRef, useState } from "react";
import { garageFields, type GarageKind } from "../garage-form";
import { failureMessage } from "../errors";
import {
  localDate,
  type GarageData,
  type MaintenanceRecord,
  type Motorcycle,
} from "../models";

export interface GarageFormOptions {
  kind: GarageKind;
  initial?: Motorcycle | MaintenanceRecord;
  defaultMileage?: number;
  onSave: (data: GarageData) => Promise<void>;
}
export function useGarageForm({
  kind,
  initial,
  defaultMileage = 0,
  onSave,
}: GarageFormOptions) {
  const fields = garageFields(kind);
  const [values, setValues] = useState<Record<string, string>>(() => {
    const defaults: Record<string, unknown> = {
      performed_on: localDate(),
      odometer_km: defaultMileage,
      ...initial,
    };
    return Object.fromEntries(
      fields.map(({ name }) => [name, String(defaults[name] ?? "")]),
    );
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const pending = useRef(false);
  async function save() {
    if (pending.current) return;
    pending.current = true;
    setBusy(true);
    setError("");
    try {
      await onSave(
        Object.fromEntries(
          fields.map(({ name }) => [name, values[name]?.trim() || null]),
        ),
      );
    } catch (failure) {
      setError(failureMessage(failure, "Unable to save. Please retry."));
    } finally {
      pending.current = false;
      setBusy(false);
    }
  }
  return {
    fields,
    values,
    busy,
    error,
    save,
    setValue: (name: string, value: string) =>
      setValues((previous) => ({ ...previous, [name]: value })),
  };
}
