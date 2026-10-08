export const apiBaseUrl = (
  import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000"
).replace(/\/$/, "");

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

export async function request<T = Record<string, unknown>>(
  path: string,
  method = "GET",
  data?: unknown,
  notifyExpired = true,
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (method !== "GET") {
    const csrf = await fetch(`${apiBaseUrl}/sanctum/csrf-cookie`, {
      credentials: "include",
      headers,
    });
    if (!csrf.ok)
      throw new ApiError(
        csrf.status,
        {},
        "Unable to initialize a secure session.",
      );
    const cookie = document.cookie
      .split("; ")
      .find((value) => value.startsWith("XSRF-TOKEN="));
    if (cookie)
      headers["X-XSRF-TOKEN"] = decodeURIComponent(
        cookie.slice("XSRF-TOKEN=".length),
      );
    headers["Content-Type"] = "application/json";
  }
  const response = await fetch(`${apiBaseUrl}${path}`, {
    method,
    credentials: "include",
    headers,
    ...(data === undefined ? {} : { body: JSON.stringify(data) }),
  });
  const body =
    response.status === 204 ? {} : await response.json().catch(() => ({}));
  if (!response.ok) {
    if (notifyExpired && (response.status === 401 || response.status === 419))
      window.dispatchEvent(new Event("auth-expired"));
    throw new ApiError(
      response.status,
      body.errors,
      response.status === 419
        ? "Your session expired. Please sign in again."
        : (body.message ?? `Request failed (${response.status}).`),
    );
  }
  return body as T;
}

export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  two_factor_enabled: boolean;
  two_factor_pending: boolean;
  providers?: string[];
}
