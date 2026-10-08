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
export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
}
export class AuthError extends Error {
  constructor(
    message: string,
    public status = 0,
  ) {
    super(message);
  }
}

export async function api<T>(
  path: string,
  method = "GET",
  data?: unknown,
  token?: string,
): Promise<T> {
  const response = await fetch(`${apiBaseUrl}${path}`, {
    method,
    credentials: "omit",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    ...(data === undefined ? {} : { body: JSON.stringify(data) }),
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok)
    throw new AuthError(
      Object.values(result.errors ?? {})
        .flat()
        .join(" ") ||
        result.message ||
        "Unable to complete authentication.",
      response.status,
    );
  return result as T;
}

export async function readToken(): Promise<Token | null> {
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
  await clearToken();
  return null;
}

export async function saveToken(value: Token): Promise<void> {
  if (Platform.OS === "web")
    throw new AuthError("Use the browser application for web sign-in.");
  await SecureStore.setItemAsync(storageKey, JSON.stringify(value), {
    keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
  });
}
export async function clearToken(): Promise<void> {
  if (Platform.OS !== "web") await SecureStore.deleteItemAsync(storageKey);
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
