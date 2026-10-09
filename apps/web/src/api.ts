import { ApiError } from "@motominator/client";
export const apiBaseUrl = (
  import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000"
).replace(/\/$/, "");

let authenticationGeneration = 0;
export function invalidateAuthenticationRequests() {
  authenticationGeneration++;
}

export { ApiError } from "@motominator/client";

export async function request<T = Record<string, unknown>>(
  path: string,
  method = "GET",
  data?: unknown,
  notifyExpired = true,
): Promise<T> {
  const current = authenticationGeneration;
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
    if (current !== authenticationGeneration)
      throw new ApiError(409, {}, "Your account changed. Please retry.");
    const cookie = document.cookie
      .split("; ")
      .find((value) => value.startsWith("XSRF-TOKEN="));
    if (cookie)
      headers["X-XSRF-TOKEN"] = decodeURIComponent(
        cookie.slice("XSRF-TOKEN=".length),
      );
    if (!(data instanceof FormData))
      headers["Content-Type"] = "application/json";
  }
  const response = await fetch(`${apiBaseUrl}${path}`, {
    method,
    credentials: "include",
    headers,
    ...(data === undefined
      ? {}
      : { body: data instanceof FormData ? data : JSON.stringify(data) }),
  });
  const body =
    response.status === 204 ? {} : await response.json().catch(() => ({}));
  if (!response.ok) {
    if (
      notifyExpired &&
      current === authenticationGeneration &&
      (response.status === 401 || response.status === 419)
    )
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

export type { AccountUser as User } from "@motominator/client";
