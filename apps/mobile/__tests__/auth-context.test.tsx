import { act, fireEvent, render, screen } from "@testing-library/react-native";
import { useState } from "react";
import { AppState, Button, Text, View } from "react-native";
import { AuthProvider, useAuth } from "../src/auth/auth-context";
import {
  api,
  AuthError,
  clearToken,
  readToken,
  saveToken,
  type Token,
} from "../src/auth/client";

jest.mock("../src/auth/client", () => ({
  api: jest.fn(),
  readToken: jest.fn(),
  saveToken: jest.fn(),
  clearToken: jest.fn(),
  AuthError: class extends Error {
    status: number;
    constructor(message: string, code: number) {
      super(message);
      this.status = code;
    }
  },
}));
const token: Token = { token: "token-A", expires_at: "2099-01-01" };
const replacement = { ...token, token: "token-B" };
const rider = { id: 1, name: "Rider A" };
const nextRider = { id: 2, name: "Rider B" };
function Probe() {
  const auth = useAuth();
  const [error, setError] = useState("");
  return (
    <View>
      <Text>{auth.user?.name ?? "Signed out"}</Text>
      <Button title="Refresh" onPress={() => void auth.refresh()} />
      <Button title="Accept" onPress={() => void auth.accept(replacement)} />
      <Button
        title="Logout"
        onPress={() =>
          void auth.logout().catch(() => setError("Logout failed"))
        }
      />
      <Text>{error}</Text>
    </View>
  );
}
beforeEach(() => {
  jest.resetAllMocks();
  jest
    .spyOn(AppState, "addEventListener")
    .mockReturnValue({ remove: jest.fn() });
  jest.mocked(readToken).mockResolvedValue(token);
  jest.mocked(saveToken).mockResolvedValue();
  jest.mocked(clearToken).mockResolvedValue(true);
});
it("does not clear a replacement user after an obsolete 401 cleanup completes", async () => {
  let finishDelete!: (value: boolean) => void;
  jest
    .mocked(api)
    .mockResolvedValueOnce(rider)
    .mockRejectedValueOnce(new AuthError("Expired", 401))
    .mockResolvedValueOnce(nextRider);
  jest.mocked(clearToken).mockImplementation(
    () =>
      new Promise((resolve) => {
        finishDelete = resolve;
      }),
  );
  await render(
    <AuthProvider>
      <Probe />
    </AuthProvider>,
  );
  await screen.findByText("Rider A");
  await fireEvent.press(screen.getByRole("button", { name: "Refresh" }));
  expect(clearToken).toHaveBeenCalledWith(token.token);
  jest.mocked(readToken).mockResolvedValue(replacement);
  await fireEvent.press(screen.getByRole("button", { name: "Accept" }));
  await screen.findByText("Rider B");
  await act(async () => {
    finishDelete(true);
  });
  expect(screen.getByText("Rider B")).toBeOnTheScreen();
});
it("ignores initial account responses after logout", async () => {
  let finish!: (value: unknown) => void;
  jest.mocked(api).mockImplementation(async (path) =>
    path === "/api/v1/user"
      ? new Promise((resolve) => {
          finish = resolve;
        })
      : {},
  );
  await render(
    <AuthProvider>
      <Probe />
    </AuthProvider>,
  );
  await fireEvent.press(screen.getByRole("button", { name: "Logout" }));
  await act(async () => {
    finish(rider);
  });
  expect(screen.getByText("Signed out")).toBeOnTheScreen();
});

it("keeps the account and token available after failed revocation so logout can be retried", async () => {
  jest
    .mocked(api)
    .mockResolvedValueOnce(rider)
    .mockRejectedValueOnce(new AuthError("Offline"))
    .mockResolvedValueOnce({});
  await render(
    <AuthProvider>
      <Probe />
    </AuthProvider>,
  );
  await screen.findByText("Rider A");
  await fireEvent.press(screen.getByRole("button", { name: "Logout" }));
  expect(screen.getByText("Logout failed")).toBeOnTheScreen();
  expect(screen.getByText("Rider A")).toBeOnTheScreen();
  expect(clearToken).not.toHaveBeenCalled();

  await fireEvent.press(screen.getByRole("button", { name: "Logout" }));
  expect(screen.getByText("Signed out")).toBeOnTheScreen();
  expect(clearToken).toHaveBeenCalledWith(token.token);
  expect(api).toHaveBeenLastCalledWith(
    "/api/v1/auth/token",
    "DELETE",
    undefined,
    token.token,
  );
});

it("clears a token that the server has already revoked", async () => {
  jest
    .mocked(api)
    .mockResolvedValueOnce(rider)
    .mockRejectedValueOnce(new AuthError("Revoked", 401));
  await render(
    <AuthProvider>
      <Probe />
    </AuthProvider>,
  );
  await screen.findByText("Rider A");
  await fireEvent.press(screen.getByRole("button", { name: "Logout" }));
  expect(clearToken).toHaveBeenCalledWith(token.token);
  expect(screen.getByText("Signed out")).toBeOnTheScreen();
  expect(screen.queryByText("Logout failed")).toBeNull();
});

it("waits for revocation and preserves a replacement account installed during logout", async () => {
  let finish!: (value: unknown) => void;
  jest
    .mocked(api)
    .mockResolvedValueOnce(rider)
    .mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          finish = resolve;
        }),
    )
    .mockResolvedValueOnce(nextRider);
  await render(
    <AuthProvider>
      <Probe />
    </AuthProvider>,
  );
  await screen.findByText("Rider A");
  await fireEvent.press(screen.getByRole("button", { name: "Logout" }));
  expect(clearToken).not.toHaveBeenCalled();
  expect(screen.getByText("Rider A")).toBeOnTheScreen();
  jest.mocked(readToken).mockResolvedValue(replacement);
  jest.mocked(clearToken).mockResolvedValue(false);
  await fireEvent.press(screen.getByRole("button", { name: "Accept" }));
  await screen.findByText("Rider B");
  await act(async () => {
    finish({});
  });
  expect(clearToken).toHaveBeenCalledWith(token.token);
  expect(screen.getByText("Rider B")).toBeOnTheScreen();
});

it("does not restore the logged-out account from a foreground refresh started during revocation", async () => {
  let finishLogout!: (value: unknown) => void;
  let finishRefresh!: (value: unknown) => void;
  jest
    .mocked(api)
    .mockResolvedValueOnce(rider)
    .mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          finishLogout = resolve;
        }),
    )
    .mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          finishRefresh = resolve;
        }),
    );
  jest.mocked(clearToken).mockImplementation(async () => {
    jest.mocked(readToken).mockResolvedValue(null);
    return true;
  });
  await render(
    <AuthProvider>
      <Probe />
    </AuthProvider>,
  );
  await screen.findByText("Rider A");
  await fireEvent.press(screen.getByRole("button", { name: "Logout" }));
  await fireEvent.press(screen.getByRole("button", { name: "Refresh" }));
  await act(async () => {
    finishLogout({});
  });
  await act(async () => {
    finishRefresh(rider);
  });
  expect(screen.getByText("Signed out")).toBeOnTheScreen();
});
