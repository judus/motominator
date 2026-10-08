import { fetch } from "expo/fetch";
import * as SecureStore from "expo-secure-store";
import * as WebBrowser from "expo-web-browser";
import { api, readToken, saveToken, socialSignIn } from "../src/auth/client";

jest.mock("expo/fetch", () => ({ fetch: jest.fn() }));
jest.mock("expo-secure-store", () => ({
  getItemAsync: jest.fn(),
  setItemAsync: jest.fn(),
  deleteItemAsync: jest.fn(),
  WHEN_UNLOCKED_THIS_DEVICE_ONLY: 7,
}));
jest.mock("expo-crypto", () => ({
  getRandomBytesAsync: jest.fn(async () => new Uint8Array(32).fill(1)),
  digestStringAsync: jest.fn(async () => "a".repeat(64)),
  CryptoDigestAlgorithm: { SHA256: "SHA-256" },
}));
jest.mock("expo-web-browser", () => ({ openAuthSessionAsync: jest.fn() }));

const token = {
  token: "opaque-device-token",
  expires_at: "2099-01-01T00:00:00Z",
};
const response = (body: unknown, status = 200) =>
  ({ ok: status < 400, status, json: async () => body }) as Awaited<
    ReturnType<typeof fetch>
  >;

beforeEach(() => {
  jest.clearAllMocks();
});

it("stores device tokens only in native secure storage", async () => {
  await saveToken(token);
  expect(SecureStore.setItemAsync).toHaveBeenCalledWith(
    "motominator.device-token",
    JSON.stringify(token),
    { keychainAccessible: 7 },
  );
});

it("loads an unexpired token", async () => {
  jest
    .mocked(SecureStore.getItemAsync)
    .mockResolvedValue(JSON.stringify(token));
  expect(await readToken()).toEqual(token);
});

it.each([
  JSON.stringify({ ...token, expires_at: "2000-01-01T00:00:00Z" }),
  "invalid-json",
])("clears expired or damaged stored credentials", async (stored) => {
  jest.mocked(SecureStore.getItemAsync).mockResolvedValue(stored);
  expect(await readToken()).toBeNull();
  expect(SecureStore.deleteItemAsync).toHaveBeenCalledWith(
    "motominator.device-token",
  );
});

it("sends bearer tokens without cookies", async () => {
  jest.mocked(fetch).mockResolvedValue(response({ id: 1 }));
  await api("/api/v1/user", "GET", undefined, token.token);
  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/\/api\/v1\/user$/),
    expect.objectContaining({
      credentials: "omit",
      headers: expect.objectContaining({
        Authorization: `Bearer ${token.token}`,
      }),
    }),
  );
});

it("reports revoked tokens as unauthorized", async () => {
  jest
    .mocked(fetch)
    .mockResolvedValue(response({ message: "Unauthenticated." }, 401));
  await expect(
    api("/api/v1/user", "GET", undefined, token.token),
  ).rejects.toMatchObject({ status: 401 });
});

it("does not exchange a cancelled browser login", async () => {
  jest.mocked(fetch).mockResolvedValue(
    response({
      code: "c".repeat(64),
      url: "https://api.example.test/auth/google/redirect",
    }),
  );
  jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({
    type: "cancel",
  } as WebBrowser.WebBrowserAuthSessionResult);
  expect(await socialSignIn("google", "Android")).toBeNull();
  expect(fetch).toHaveBeenCalledTimes(1);
});

it("exchanges the matching handoff code with the private verifier", async () => {
  const code = "c".repeat(64);
  jest
    .mocked(fetch)
    .mockResolvedValueOnce(
      response({ code, url: "https://api.example.test/auth/google/redirect" }),
    )
    .mockResolvedValueOnce(response(token, 201));
  jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({
    type: "success",
    url: `motominator://auth-return?code=${code}`,
  } as WebBrowser.WebBrowserAuthSessionResult);
  expect(await socialSignIn("google", "Android")).toEqual(token);
  const options = jest.mocked(fetch).mock.calls[1][1];
  expect(JSON.parse(options!.body as string)).toEqual({
    code,
    verifier: "01".repeat(32),
  });
});

it("rejects a substituted callback code before exchange", async () => {
  jest.mocked(fetch).mockResolvedValue(
    response({
      code: "c".repeat(64),
      url: "https://api.example.test/auth/google/redirect",
    }),
  );
  jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({
    type: "success",
    url: `motominator://auth-return?code=${"x".repeat(64)}`,
  } as WebBrowser.WebBrowserAuthSessionResult);
  await expect(socialSignIn("google", "Android")).rejects.toThrow(
    "could not be completed",
  );
  expect(fetch).toHaveBeenCalledTimes(1);
});
