export interface InvoiceWorkshop {
  name: string | null;
  address: string | null;
  email: string | null;
  phone: string | null;
  tax_number: string | null;
}
export interface InvoiceItem {
  description: string | null;
  quantity: string | null;
  unit_price: string | null;
  net_amount: string | null;
  tax_rate: string | null;
  tax_amount: string | null;
  total_amount: string | null;
  labor_minutes: number | null;
}
export interface InvoiceDraft {
  invoice_number: string | null;
  performed_on: string | null;
  odometer_km: number | null;
  title: string | null;
  notes: string | null;
  currency: string | null;
  subtotal_amount: string | null;
  tax_amount: string | null;
  total_amount: string | null;
  labor_minutes: number | null;
  workshop: InvoiceWorkshop;
  items: InvoiceItem[];
}
export interface InvoiceImport {
  id: number;
  motorcycle_id: number;
  filename: string;
  mime: string;
  size: number;
  status:
    "uploaded" | "queued" | "processing" | "ready" | "failed" | "confirmed";
  version: number;
  draft: InvoiceDraft | null;
  error: string | null;
  provider: string | null;
  model: string | null;
  created_at: string;
  maintenance_record_id: number | null;
  retry_available: boolean;
}
export const invoiceFields = [
  ["invoice_number", "Invoice number"],
  ["performed_on", "Date (YYYY-MM-DD)"],
  ["odometer_km", "Invoice mileage (km)"],
  ["title", "Maintenance title"],
  ["notes", "Notes / uncertainty"],
  ["currency", "Currency (ISO code)"],
  ["subtotal_amount", "Subtotal"],
  ["tax_amount", "Tax amount"],
  ["total_amount", "Total"],
  ["labor_minutes", "Labor (minutes)"],
] as const;
export const workshopFields = [
  ["name", "Workshop name"],
  ["address", "Workshop address"],
  ["email", "Workshop email"],
  ["phone", "Workshop phone"],
  ["tax_number", "Workshop tax number"],
] as const;
export const itemFields = [
  ["description", "Description"],
  ["quantity", "Quantity"],
  ["unit_price", "Unit price"],
  ["net_amount", "Net amount"],
  ["tax_rate", "Tax rate (%)"],
  ["tax_amount", "Tax amount"],
  ["total_amount", "Gross amount"],
  ["labor_minutes", "Labor (minutes)"],
] as const;
export function blankInvoiceItem(): InvoiceItem {
  return {
    description: null,
    quantity: null,
    unit_price: null,
    net_amount: null,
    tax_rate: null,
    tax_amount: null,
    total_amount: null,
    labor_minutes: null,
  };
}
export function invoiceValue(
  key: string,
  text: string,
): string | number | null {
  const value = text;
  if (!value) return null;
  if (key === "labor_minutes" || key === "odometer_km")
    return /^\d+$/.test(value) ? Number(value) : value;
  return key === "currency" ? value.toUpperCase() : value;
}
// Amounts remain decimal strings; integer cents avoid float rounding in review warnings.
function cents(value: string | null): bigint | null {
  if (value === null || !/^-?\d+(\.\d{1,2})?$/.test(value)) return null;
  const negative = value.startsWith("-");
  const [whole, fraction = ""] = value.replace("-", "").split(".");
  const amount = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, "0"));
  return negative ? -amount : amount;
}
export function invoiceWarnings(draft: InvoiceDraft): string[] {
  const warnings = [
    "AI extraction can be wrong. Check every value against the original invoice; blanks mean unknown.",
  ];
  const subtotal = cents(draft.subtotal_amount),
    tax = cents(draft.tax_amount),
    total = cents(draft.total_amount);
  if (
    subtotal !== null &&
    tax !== null &&
    total !== null &&
    subtotal + tax !== total
  )
    warnings.push("Subtotal plus tax differs from the invoice total.");
  const amounts = draft.items.map((item) => cents(item.total_amount));
  if (
    total !== null &&
    amounts.length &&
    amounts.every((amount) => amount !== null) &&
    amounts.reduce<bigint>((sum, amount) => sum + (amount ?? 0n), 0n) !== total
  )
    warnings.push(
      "The gross line amounts differ from the invoice total. Discounts, rounding or missing positions may explain this.",
    );
  return warnings;
}
