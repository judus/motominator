import { fetch } from "expo/fetch";
import * as SecureStore from "expo-secure-store";
import * as WebBrowser from "expo-web-browser";
import {
  api,
  readToken,
  clearToken,
  saveToken,
  socialSignIn,
  linkSocialAccount,
  authenticatedApi,
} from "../src/auth/client";

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

it("authenticates feature requests using the stored device token", async () => {
  jest
    .mocked(SecureStore.getItemAsync)
    .mockResolvedValue(JSON.stringify(token));
  jest.mocked(fetch).mockResolvedValue(response({ data: [] }));
  await authenticatedApi("/api/v1/motorcycles");
  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/\/api\/v1\/motorcycles$/),
    expect.objectContaining({
      credentials: "omit",
      headers: expect.objectContaining({
        Authorization: `Bearer ${token.token}`,
      }),
    }),
  );
});
it("discards a revoked device token after a feature request returns unauthorized", async () => {
  jest
    .mocked(SecureStore.getItemAsync)
    .mockResolvedValue(JSON.stringify(token));
  jest
    .mocked(fetch)
    .mockResolvedValue(response({ message: "Unauthenticated." }, 401));
  await expect(authenticatedApi("/api/v1/motorcycles")).rejects.toMatchObject({
    status: 401,
  });
  expect(SecureStore.deleteItemAsync).toHaveBeenCalledWith(
    "motominator.device-token",
  );
});

it("sends native multipart uploads with bearer auth and no JSON content type", async () => {
  jest.mocked(fetch).mockResolvedValue(response({ data: { id: 1 } }, 201));
  const body = new FormData();
  await api("/api/v1/motorcycles/1/invoice-imports", "POST", body, token.token);
  const options = jest.mocked(fetch).mock.calls[0][1]!;
  expect(options.body).toBe(body);
  expect(options.headers).toMatchObject({
    Authorization: `Bearer ${token.token}`,
  });
  expect(options.headers).not.toHaveProperty("Content-Type");
});

it("links a provider with proof while keeping the bearer token out of the browser URL", async () => {
  const code = "l".repeat(64);
  jest
    .mocked(SecureStore.getItemAsync)
    .mockResolvedValue(JSON.stringify(token));
  jest
    .mocked(fetch)
    .mockResolvedValueOnce(
      response({
        code,
        url: `http://localhost/auth/google/redirect?native_link=${code}`,
      }),
    )
    .mockResolvedValueOnce(response({}));
  jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({
    type: "success",
    url: `motominator://auth-return?link_code=${code}`,
  });
  expect(await linkSocialAccount("google", "password")).toBe(true);
  expect(WebBrowser.openAuthSessionAsync).toHaveBeenCalledWith(
    expect.not.stringContaining(token.token),
    "motominator://auth-return",
  );
  expect(fetch).toHaveBeenLastCalledWith(
    expect.stringMatching(/account\/social\/link\/complete$/),
    expect.objectContaining({
      body: JSON.stringify({ code, verifier: "01".repeat(32) }),
    }),
  );
});
it("rejects a mismatched social-link callback before consuming the proof", async () => {
  jest
    .mocked(SecureStore.getItemAsync)
    .mockResolvedValue(JSON.stringify(token));
  jest.mocked(fetch).mockResolvedValue(
    response({
      code: "l".repeat(64),
      url: "http://localhost/auth/google/redirect",
    }),
  );
  jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({
    type: "success",
    url: "motominator://auth-return?link_code=wrong",
  });
  await expect(linkSocialAccount("google", "password")).rejects.toThrow(
    "Social account linking could not be completed.",
  );
  expect(fetch).toHaveBeenCalledTimes(1);
});

it("keeps a replacement token when an obsolete request returns 401", async () => {
  let stored = JSON.stringify(token);
  jest.mocked(SecureStore.getItemAsync).mockImplementation(async () => stored);
  jest
    .mocked(SecureStore.setItemAsync)
    .mockImplementation(async (_key, value) => {
      stored = value;
    });
  jest.mocked(SecureStore.deleteItemAsync).mockImplementation(async () => {
    stored = "";
  });
  let resolve!: (value: Awaited<ReturnType<typeof fetch>>) => void;
  jest.mocked(fetch).mockImplementation(
    () =>
      new Promise((done) => {
        resolve = done;
      }),
  );
  const oldRequest = authenticatedApi("/api/v1/user").catch((error) => error);
  while (!resolve) await Promise.resolve();
  const replacement = { ...token, token: "replacement-device-token" };
  await saveToken(replacement);
  resolve(response({}, 401));
  await oldRequest;
  expect(await readToken()).toEqual(replacement);
  expect(SecureStore.deleteItemAsync).not.toHaveBeenCalled();
});

it("serializes replacement storage behind an in-progress owned deletion", async () => {
  let stored = JSON.stringify(token);
  let finish!: () => void;
  jest.mocked(SecureStore.getItemAsync).mockImplementation(async () => stored);
  jest.mocked(SecureStore.deleteItemAsync).mockImplementation(
    () =>
      new Promise((resolve) => {
        finish = () => {
          stored = "";
          resolve();
        };
      }),
  );
  jest
    .mocked(SecureStore.setItemAsync)
    .mockImplementation(async (_key, value) => {
      stored = value;
    });
  const removal = clearToken(token.token);
  for (let attempt = 0; attempt < 20 && !finish; attempt++)
    await Promise.resolve();
  expect(finish).toBeDefined();
  const replacement = { ...token, token: "replacement-token" };
  const saving = saveToken(replacement);
  await Promise.resolve();
  expect(SecureStore.setItemAsync).not.toHaveBeenCalled();
  finish();
  await Promise.all([removal, saving]);
  expect(await readToken()).toEqual(replacement);
});
