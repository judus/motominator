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
  const invalidate = useCallback(() => {
    generation.current++;
  }, []);

  const refresh = useCallback(async () => {
    const current = ++generation.current;
    let token: Token | null = null;
    try {
      token = await readToken();
      if (current !== generation.current) return;
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
        if (token) await clearToken(token.token);
        if (current === generation.current) setUser(null);
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
    void Promise.resolve().then(() => {
      if (active) void refresh();
    });
    const subscription = AppState.addEventListener("change", (state) => {
      if (state === "active") void refresh();
    });
    return () => {
      active = false;
      invalidate();
      subscription.remove();
    };
  }, [refresh, invalidate]);

  async function accept(token: Token) {
    const current = ++generation.current;
    try {
      await saveToken(token);
    } catch (failure) {
      await api("/api/v1/auth/token", "DELETE", undefined, token.token).catch(
        () => undefined,
      );
      throw failure;
    }
    if (current === generation.current) await refresh();
  }
  async function logout() {
    const current = ++generation.current;
    const token = await readToken();
    if (current !== generation.current) return;
    if (token)
      await api("/api/v1/auth/token", "DELETE", undefined, token.token).catch(
        (failure) => {
          if (!(failure instanceof AuthError && failure.status === 401))
            throw failure;
        },
      );
    const cleared = token ? await clearToken(token.token) : true;
    if (cleared && current === generation.current) {
      setUser(null);
      setError("");
      setReady(true);
    } else if (cleared) {
      // A foreground refresh may have advanced the generation without replacing
      // the token. Reconcile storage and invalidate its obsolete account response.
      await refresh();
    }
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
