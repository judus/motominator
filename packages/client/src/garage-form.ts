export type GarageField = {
  name: string;
  label: string;
  type?: "number" | "date";
  required?: boolean;
  min?: number;
  max?: number;
  step?: string;
  maxLength?: number;
};
const motorcycleFields: GarageField[] = [
  { name: "make", label: "Make", required: true, maxLength: 100 },
  { name: "model", label: "Model", required: true, maxLength: 100 },
  {
    name: "year",
    label: "Year",
    type: "number",
    required: true,
    min: 1885,
    max: new Date().getFullYear() + 1,
  },
  { name: "nickname", label: "Nickname", maxLength: 100 },
  {
    name: "odometer_km",
    label: "Mileage (km)",
    type: "number",
    required: true,
    min: 0,
    max: 4294967295,
  },
];
const maintenanceFields: GarageField[] = [
  { name: "performed_on", label: "Date", type: "date", required: true },
  {
    name: "odometer_km",
    label: "Mileage (km)",
    type: "number",
    required: true,
    min: 0,
    max: 4294967295,
  },
  { name: "title", label: "Work performed", required: true, maxLength: 255 },
  { name: "notes", label: "Notes", maxLength: 10000 },
  {
    name: "cost_amount",
    label: "Cost (up to 2 decimals)",
    type: "number",
    min: 0,
    step: "0.01",
  },
  { name: "currency", label: "Currency code (e.g. CHF)", maxLength: 3 },
];

export type GarageKind = "motorcycle" | "maintenance";
export function garageFields(kind: GarageKind): readonly GarageField[] {
  return kind === "motorcycle" ? motorcycleFields : maintenanceFields;
}
