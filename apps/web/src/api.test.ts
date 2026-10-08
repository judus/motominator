import { describe, expect, it, vi } from "vitest";
import { ApiError, request } from "./api";

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
