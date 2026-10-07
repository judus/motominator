import { fireEvent, render, screen } from "@testing-library/react-native";

import { ServerStatus } from "../src/components/server-status";

describe("server connection", () => {
  const originalFetch = globalThis.fetch;

  afterEach(() => {
    globalThis.fetch = originalFetch;
  });

  it("connects to a valid Motominator server", async () => {
    const fetchMock = jest.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ name: "Motominator", status: "ok" }),
    });
    globalThis.fetch = fetchMock;
    await render(<ServerStatus />);

    await fireEvent.press(screen.getByRole("button", { name: "Check server" }));

    expect(
      await screen.findByText("Connected to Motominator."),
    ).toBeOnTheScreen();
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
      globalThis.fetch = jest.fn().mockResolvedValue(response);
      await render(<ServerStatus />);

      await fireEvent.press(
        screen.getByRole("button", { name: "Check server" }),
      );

      expect(await screen.findByText(message)).toBeOnTheScreen();
      expect(
        screen.getByRole("button", { name: "Check server" }),
      ).toBeEnabled();
    },
  );

  it("shows network failures and allows retry", async () => {
    globalThis.fetch = jest
      .fn()
      .mockRejectedValue(new Error("Network unavailable."));
    await render(<ServerStatus />);

    await fireEvent.press(screen.getByRole("button", { name: "Check server" }));

    expect(await screen.findByText("Network unavailable.")).toBeOnTheScreen();
    expect(screen.getByRole("button", { name: "Check server" })).toBeEnabled();
  });
});
