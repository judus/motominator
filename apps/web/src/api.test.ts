import { describe, expect, it, vi } from "vitest";
import { ApiError, invalidateAuthenticationRequests, request } from "./api";

describe("cookie authentication", () => {
  it("initializes CSRF and submits credentialed mutations without bearer tokens", async () => {
    document.cookie = "XSRF-TOKEN=test%3Dtoken; path=/";
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({ ok: true, status: 204 });
    vi.stubGlobal("fetch", fetchMock);
    await request("/login", "POST", {
      email: "rider@example.test",
      password: "secret",
    });
    expect(fetchMock.mock.calls[0][0]).toMatch(/\/sanctum\/csrf-cookie$/);
    expect(fetchMock.mock.calls[1][1]).toMatchObject({
      credentials: "include",
      headers: { "X-XSRF-TOKEN": "test=token" },
    });
    expect(fetchMock.mock.calls[1][1].headers.Authorization).toBeUndefined();
  });

  it("reports validation errors", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
        json: async () => ({
          message: "Invalid credentials.",
          errors: { email: ["Invalid credentials."] },
        }),
      }),
    );
    await expect(request("/api/v1/user")).rejects.toMatchObject({
      status: 422,
      errors: { email: ["Invalid credentials."] },
    });
  });

  it.each([401, 419])(
    "notifies the app when the session fails with %s",
    async (status) => {
      const listener = vi.fn();
      window.addEventListener("auth-expired", listener);
      vi.stubGlobal(
        "fetch",
        vi
          .fn()
          .mockResolvedValue({ ok: false, status, json: async () => ({}) }),
      );
      await expect(request("/api/v1/user")).rejects.toBeInstanceOf(ApiError);
      expect(listener).toHaveBeenCalledOnce();
      window.removeEventListener("auth-expired", listener);
    },
  );
});

it("sends multipart uploads with CSRF and lets fetch supply the boundary", async () => {
  const fetchMock = vi
    .fn()
    .mockResolvedValueOnce({ ok: true })
    .mockResolvedValueOnce({ ok: true, status: 204 });
  vi.stubGlobal("fetch", fetchMock);
  const body = new FormData();
  body.append(
    "file",
    new File(["invoice"], "invoice.pdf", { type: "application/pdf" }),
  );
  await request("/api/v1/motorcycles/1/invoice-imports", "POST", body);
  expect(fetchMock.mock.calls[1][1].body).toBe(body);
  expect(fetchMock.mock.calls[1][1].credentials).toBe("include");
  expect(fetchMock.mock.calls[1][1].headers["Content-Type"]).toBeUndefined();
});

it("does not expire a newer session for an obsolete unauthorized response", async () => {
  const listener = vi.fn();
  window.addEventListener("auth-expired", listener);
  let finish!: (value: unknown) => void;
  vi.stubGlobal(
    "fetch",
    vi.fn(
      () =>
        new Promise((resolve) => {
          finish = resolve;
        }),
    ),
  );
  const oldRequest = request("/api/v1/user").catch((error) => error);
  invalidateAuthenticationRequests();
  finish({ ok: false, status: 401, json: async () => ({}) });
  await oldRequest;
  expect(listener).not.toHaveBeenCalled();
  window.removeEventListener("auth-expired", listener);
});

it("does not send a mutation under replacement cookies after stale CSRF initialization", async () => {
  let finish!: (value: unknown) => void;
  const transport = vi.fn(
    () =>
      new Promise((resolve) => {
        finish = resolve;
      }),
  );
  vi.stubGlobal("fetch", transport);
  const oldRequest = request("/api/v1/account/profile", "PUT", {
    name: "Old account",
  }).catch((error) => error);
  invalidateAuthenticationRequests();
  finish({ ok: true });
  expect(await oldRequest).toMatchObject({ status: 409 });
  expect(transport).toHaveBeenCalledTimes(1);
});
