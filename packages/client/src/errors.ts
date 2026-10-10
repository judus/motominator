export class ApiError extends Error {
  status: number;
  errors: Record<string, string[]>;
  constructor(
    status: number,
    errors: Record<string, string[]> = {},
    message = "Request failed.",
  ) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}
export function fieldErrors(failure: unknown): Record<string, string[]> {
  return failure instanceof ApiError ? failure.errors : {};
}
export function failureMessage(failure: unknown, fallback: string): string {
  return (
    Object.values(fieldErrors(failure)).flat().join(" ") ||
    (failure instanceof Error ? failure.message : fallback)
  );
}
