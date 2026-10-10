import {
  ApiError,
  createClient,
  decodeStream,
  type StreamConnection,
} from "@motominator/client";
import { fetch } from "expo/fetch";
import * as SecureStore from "expo-secure-store";
import * as Crypto from "expo-crypto";
import * as WebBrowser from "expo-web-browser";
import { Platform } from "react-native";

export const apiBaseUrl = (
  process.env.EXPO_PUBLIC_API_BASE_URL ??
  (Platform.OS === "android" ? "http://10.0.2.2:8000" : "http://localhost:8000")
).replace(/\/$/, "");
const storageKey = "motominator.device-token";
export interface Token {
  token: string;
  expires_at: string;
}
export type { AccountUser as User } from "@motominator/client";
export class AuthError extends ApiError {
  constructor(message: string, status = 0) {
    super(status, {}, message);
  }
}

export async function api<T>(
  path: string,
  method = "GET",
  data?: unknown,
  token?: string,
): Promise<T> {
  const response = await apiResponse(path, method, data, token);
  const result = await response.json().catch(() => ({}));
  return result as T;
}

async function apiResponse(
  path: string,
  method: string,
  data?: unknown,
  token?: string,
  signal?: AbortSignal,
) {
  const response = await fetch(`${apiBaseUrl}${path}`, {
    method,
    credentials: "omit",
    signal,
    headers: {
      Accept: "application/json",
      ...(data instanceof FormData
        ? {}
        : { "Content-Type": "application/json" }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    ...(data === undefined
      ? {}
      : { body: data instanceof FormData ? data : JSON.stringify(data) }),
  });
  if (!response.ok) {
    const result = await response.json().catch(() => ({}));
    throw new AuthError(
      Object.values(result.errors ?? {})
        .flat()
        .join(" ") ||
        result.message ||
        "Unable to complete authentication.",
      response.status,
    );
  }
  return response;
}

export function authenticatedStream(
  path: string,
  data: unknown,
  onExpired: () => void,
): StreamConnection {
  const controller = new AbortController();
  async function* chunks() {
    const token = await readToken();
    if (!token) {
      onExpired();
      throw new AuthError("Your session expired. Please sign in again.", 401);
    }
    try {
      const response = await apiResponse(
        path,
        "POST",
        data,
        token.token,
        controller.signal,
      );
      if (
        !response.body ||
        !response.headers.get("Content-Type")?.startsWith("text/event-stream")
      )
        throw new Error("The server did not return a chat stream.");
      yield* decodeStream(response.body, new TextDecoder());
    } catch (failure) {
      if (failure instanceof AuthError && failure.status === 401) {
        await clearToken(token.token);
        onExpired();
      }
      throw failure;
    }
  }
  return { chunks: chunks(), cancel: () => controller.abort() };
}

// Serialize reads and mutations: comparison and deletion must be one operation.
// Otherwise a delayed unauthorized response can erase a newer sign-in.
let storageOperations: Promise<unknown> = Promise.resolve();
function withTokenStorage<T>(operation: () => Promise<T>): Promise<T> {
  const result = storageOperations.then(operation);
  storageOperations = result.catch(() => undefined);
  return result;
}

export function readToken(): Promise<Token | null> {
  return withTokenStorage(async () => {
    if (Platform.OS === "web") return null;
    const stored = await SecureStore.getItemAsync(storageKey);
    if (!stored) return null;
    try {
      const value = JSON.parse(stored) as Token;
      if (
        typeof value.token === "string" &&
        Date.parse(value.expires_at) > Date.now()
      )
        return value;
    } catch {
      /* Discard an invalid or obsolete stored value. */
    }
    await SecureStore.deleteItemAsync(storageKey);
    return null;
  });
}

export function saveToken(value: Token): Promise<void> {
  return withTokenStorage(async () => {
    if (Platform.OS === "web")
      throw new AuthError("Use the browser application for web sign-in.");
    await SecureStore.setItemAsync(storageKey, JSON.stringify(value), {
      keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
    });
  });
}
export function clearToken(expectedToken?: string): Promise<boolean> {
  return withTokenStorage(async () => {
    if (Platform.OS === "web") return false;
    if (expectedToken !== undefined) {
      const stored = await SecureStore.getItemAsync(storageKey);
      if (!stored) return false;
      try {
        if ((JSON.parse(stored) as Token).token !== expectedToken) return false;
      } catch {
        return false;
      }
    }
    await SecureStore.deleteItemAsync(storageKey);
    return true;
  });
}

export async function socialSignIn(
  provider: string,
  deviceName: string,
): Promise<Token | null> {
  const verifier = Array.from(await Crypto.getRandomBytesAsync(32), (byte) =>
    byte.toString(16).padStart(2, "0"),
  ).join("");
  const challenge = await Crypto.digestStringAsync(
    Crypto.CryptoDigestAlgorithm.SHA256,
    verifier,
  );
  const intent = await api<{ code: string; url: string }>(
    "/api/v1/auth/native",
    "POST",
    { provider, device_name: deviceName, challenge },
  );
  const result = await WebBrowser.openAuthSessionAsync(
    intent.url,
    "motominator://auth-return",
  );
  if (result.type !== "success") return null;
  const returned = new URL(result.url);
  if (
    returned.protocol !== "motominator:" ||
    returned.hostname !== "auth-return" ||
    returned.searchParams.get("code") !== intent.code
  )
    throw new AuthError(
      "Social sign-in was cancelled or could not be completed.",
    );
  return api<Token>("/api/v1/auth/native/exchange", "POST", {
    code: intent.code,
    verifier,
  });
}

export async function authenticatedApi<T>(
  path: string,
  method = "GET",
  data?: unknown,
): Promise<T> {
  const token = await readToken();
  if (!token)
    throw new AuthError("Your session expired. Please sign in again.", 401);
  try {
    return await api<T>(path, method, data, token.token);
  } catch (failure) {
    if (failure instanceof AuthError && failure.status === 401)
      await clearToken(token.token);
    throw failure;
  }
}

// The proof verifier stays in memory. Device tokens never enter browser URLs.
export async function linkSocialAccount(
  provider: string,
  password: string,
): Promise<boolean> {
  const verifier = Array.from(await Crypto.getRandomBytesAsync(32), (byte) =>
    byte.toString(16).padStart(2, "0"),
  ).join("");
  const challenge = await Crypto.digestStringAsync(
    Crypto.CryptoDigestAlgorithm.SHA256,
    verifier,
  );
  const account = createClient(authenticatedApi).account;
  const intent = await account.startSocialLink(provider, password, challenge);
  const result = await WebBrowser.openAuthSessionAsync(
    intent.url,
    "motominator://auth-return",
  );
  if (result.type !== "success") return false;
  const returned = new URL(result.url);
  if (
    returned.protocol !== "motominator:" ||
    returned.hostname !== "auth-return" ||
    returned.searchParams.get("link_code") !== intent.code
  )
    throw new AuthError("Social account linking could not be completed.");
  await account.completeSocialLink(intent.code, verifier);
  return true;
}
