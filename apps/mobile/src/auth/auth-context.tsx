import {
  createContext,
  useContext,
  useEffect,
  useRef,
  useState,
  useCallback,
  type ReactNode,
} from "react";
import { AppState } from "react-native";
import {
  api,
  AuthError,
  clearToken,
  readToken,
  saveToken,
  type Token,
  type User,
} from "./client";

interface AuthContextValue {
  user: User | null;
  ready: boolean;
  error: string;
  accept: (token: Token) => Promise<void>;
  refresh: () => Promise<void>;
  logout: () => Promise<void>;
}
const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [ready, setReady] = useState(false);
  const [error, setError] = useState("");
  const generation = useRef(0);

  const refresh = useCallback(async () => {
    const current = ++generation.current;
    try {
      const token = await readToken();
      const user = token
        ? await api<User>("/api/v1/user", "GET", undefined, token.token)
        : null;
      if (current === generation.current) {
        setUser(user);
        setError("");
      }
    } catch (failure) {
      if (current !== generation.current) return;
      if (failure instanceof AuthError && failure.status === 401) {
        await clearToken();
        setUser(null);
      } else
        setError(
          "Unable to load your account. Check the connection and retry.",
        );
    } finally {
      if (current === generation.current) setReady(true);
    }
  }, []);
  useEffect(() => {
    let active = true;
    const current = ++generation.current;
    readToken()
      .then((token) =>
        token ? api<User>("/api/v1/user", "GET", undefined, token.token) : null,
      )
      .then((user) => {
        if (active && current === generation.current) setUser(user);
      })
      .catch(async (failure) => {
        if (!active || current !== generation.current) return;
        if (failure instanceof AuthError && failure.status === 401)
          await clearToken();
        else
          setError(
            "Unable to load your account. Check the connection and retry.",
          );
      })
      .finally(() => {
        if (active) setReady(true);
      });
    const subscription = AppState.addEventListener("change", (state) => {
      if (state === "active") void refresh();
    });
    return () => {
      active = false;
      subscription.remove();
    };
  }, [refresh]);

  async function accept(token: Token) {
    try {
      await saveToken(token);
    } catch (failure) {
      await api("/api/v1/auth/token", "DELETE", undefined, token.token).catch(
        () => undefined,
      );
      throw failure;
    }
    await refresh();
  }
  async function logout() {
    generation.current++;
    const token = await readToken();
    if (token)
      await api("/api/v1/auth/token", "DELETE", undefined, token.token).catch(
        (failure) => {
          if (!(failure instanceof AuthError && failure.status === 401))
            throw failure;
        },
      );
    await clearToken();
    setUser(null);
  }
  return (
    <AuthContext value={{ user, ready, error, accept, refresh, logout }}>
      {children}
    </AuthContext>
  );
}
export function useAuth() {
  const value = useContext(AuthContext);
  if (!value) throw new Error("AuthProvider is required.");
  return value;
}
