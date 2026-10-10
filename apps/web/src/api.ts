import {
  ApiError,
  decodeStream,
  type StreamConnection,
} from "@motominator/client";
export const apiBaseUrl = (
  import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000"
).replace(/\/$/, "");

let authenticationGeneration = 0;
export function invalidateAuthenticationRequests() {
  authenticationGeneration++;
}

export { ApiError } from "@motominator/client";

async function openRequest(
  path: string,
  method: string,
  data?: unknown,
  signal?: AbortSignal,
): Promise<{ response: Response; current: number }> {
  const current = authenticationGeneration;
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (method !== "GET") {
    const csrf = await fetch(`${apiBaseUrl}/sanctum/csrf-cookie`, {
      credentials: "include",
      headers,
      signal,
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
    signal,
    ...(data === undefined
      ? {}
      : { body: data instanceof FormData ? data : JSON.stringify(data) }),
  });
  return { response, current };
}

async function rejectResponse(
  response: Response,
  current: number,
  notifyExpired: boolean,
): Promise<never> {
  const body = await response.json().catch(() => ({}));
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

export async function request<T = Record<string, unknown>>(
  path: string,
  method = "GET",
  data?: unknown,
  notifyExpired = true,
): Promise<T> {
  const { response, current } = await openRequest(path, method, data);
  if (!response.ok) return rejectResponse(response, current, notifyExpired);
  return (
    response.status === 204 ? {} : await response.json().catch(() => ({}))
  ) as T;
}

export function streamRequest(path: string, data: unknown): StreamConnection {
  const controller = new AbortController();
  async function* chunks() {
    const { response, current } = await openRequest(
      path,
      "POST",
      data,
      controller.signal,
    );
    if (!response.ok) return rejectResponse(response, current, true);
    if (current !== authenticationGeneration)
      throw new ApiError(409, {}, "Your account changed. Please retry.");
    if (
      !response.body ||
      !response.headers.get("Content-Type")?.startsWith("text/event-stream")
    )
      throw new Error("The server did not return a chat stream.");
    yield* decodeStream(response.body, new TextDecoder());
  }
  return { chunks: chunks(), cancel: () => controller.abort() };
}

export type { AccountUser as User } from "@motominator/client";
