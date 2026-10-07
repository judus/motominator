import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import App from "./App";

describe("server connection", () => {
  it("connects to a valid Motominator server", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ name: "Motominator", status: "ok" }),
    });
    vi.stubGlobal("fetch", fetchMock);
    render(<App />);

    fireEvent.click(screen.getByRole("button", { name: "Check server" }));

    expect(
      await screen.findByText("Connected to Motominator."),
    ).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringMatching(/\/api\/v1\/status$/),
      { headers: { Accept: "application/json" } },
    );
    expect(screen.getByRole("button", { name: "Check server" })).toBeEnabled();
  });

  it.each([
    [{ ok: false, status: 503 }, "Server returned HTTP 503."],
    [
      { ok: true, json: async () => ({ name: "Other", status: "ok" }) },
      "Unexpected server response.",
    ],
  ])(
    "shows an unsuccessful response and allows retry",
    async (response, message) => {
      vi.stubGlobal("fetch", vi.fn().mockResolvedValue(response));
      render(<App />);

      fireEvent.click(screen.getByRole("button", { name: "Check server" }));

      expect(await screen.findByText(message)).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: "Check server" }),
      ).toBeEnabled();
    },
  );

  it("shows network failures and allows retry", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockRejectedValue(new Error("Network unavailable.")),
    );
    render(<App />);

    fireEvent.click(screen.getByRole("button", { name: "Check server" }));

    expect(await screen.findByText("Network unavailable.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Check server" })).toBeEnabled();
  });
});
