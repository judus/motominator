import { useEffect, useState, type FormEvent } from "react";
import { ApiError, apiBaseUrl, request, type User } from "./api";
import { AccountSettings } from "./AccountSettings";
import "./App.css";

function App() {
  const [user, setUser] = useState<User | null>(null);
  const [config, setConfig] = useState<{
    registration_enabled: boolean;
    providers: string[];
  }>({ registration_enabled: false, providers: [] });
  const [ready, setReady] = useState(false);
  const [message, setMessage] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [busy, setBusy] = useState(false);
  const [challenge, setChallenge] = useState(
    new URLSearchParams(window.location.search).get("two_factor") === "1",
  );
  const [page, setPage] = useState(window.location.pathname);
  const query = new URLSearchParams(window.location.search);

  async function refresh() {
    const result = await request<User>("/api/v1/user");
    setUser(typeof result.id === "number" ? result : null);
  }

  useEffect(() => {
    let alive = true;
    Promise.all([
      request<User>("/api/v1/user", "GET", undefined, false).catch(() => null),
      request<typeof config>("/api/v1/auth/config").catch(() => ({
        registration_enabled: false,
        providers: [],
      })),
    ]).then(([current, settings]) => {
      if (alive) {
        setUser(current && typeof current.id === "number" ? current : null);
        setConfig(settings);
        setReady(true);
      }
    });
    const expired = () => {
      setUser(null);
      setMessage("Your session expired. Please sign in again.");
    };
    window.addEventListener("auth-expired", expired);
    return () => {
      alive = false;
      window.removeEventListener("auth-expired", expired);
    };
  }, []);

  async function action(callback: () => Promise<void>) {
    setBusy(true);
    setMessage("");
    setErrors({});
    try {
      await callback();
    } catch (error) {
      setMessage(
        error instanceof Error ? error.message : "Unable to complete request.",
      );
      if (error instanceof ApiError) setErrors(error.errors);
    } finally {
      setBusy(false);
    }
  }

  function authSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget));
    void action(async () => {
      if (page === "/forgot-password") {
        await request("/forgot-password", "POST", data);
        setMessage(
          "If an account exists, a password reset link has been sent.",
        );
        return;
      }
      if (page === "/reset-password") {
        await request("/reset-password", "POST", {
          ...data,
          token: query.get("token"),
          email: query.get("email"),
        });
        setPage("/login");
        setMessage("Password reset. You can now sign in.");
        return;
      }
      const result = await request<{ two_factor?: boolean }>(
        challenge
          ? "/two-factor-challenge"
          : page === "/register"
            ? "/register"
            : "/login",
        "POST",
        data,
      );
      if (result.two_factor) {
        setChallenge(true);
        return;
      }
      await refresh();
      if (query.get("native") === "1") {
        window.location.assign(`${apiBaseUrl}/auth/mobile/complete`);
        return;
      }
      setChallenge(false);
      if (page !== "/verify-email") setPage("/account");
    });
  }

  const field = (
    name: string,
    label: string,
    type = "text",
    defaultValue?: string,
  ) => (
    <label key={name}>
      {label}
      <input
        name={name}
        type={type}
        defaultValue={defaultValue}
        required
        autoComplete={
          name.includes("password")
            ? page === "/reset-password" || page === "/register"
              ? "new-password"
              : "current-password"
            : name === "email"
              ? "email"
              : name === "code"
                ? "one-time-code"
                : "name"
        }
      />
    </label>
  );
  const [status, setStatus] = useState("Ready to connect.");
  const [checking, setChecking] = useState(false);

  async function checkServer() {
    setChecking(true);
    setStatus("Connecting…");

    try {
      const response = await fetch(`${apiBaseUrl}/api/v1/status`, {
        headers: { Accept: "application/json" },
      });
      if (!response.ok)
        throw new Error(`Server returned HTTP ${response.status}.`);
      const data = await response.json();
      if (data.name !== "Motominator" || data.status !== "ok") {
        throw new Error("Unexpected server response.");
      }
      setStatus("Connected to Motominator.");
    } catch (error) {
      setStatus(
        error instanceof Error ? error.message : "Unable to reach the server.",
      );
    } finally {
      setChecking(false);
    }
  }

  return (
    <main>
      <h1>Motominator</h1>
      <p>A place to explore motorcycles, data and ideas.</p>
      <nav>
        <a href="/account">Account</a> · <a href="/login">Sign in</a>
        {config.registration_enabled && (
          <>
            {" "}
            · <a href="/register">Create account</a>
          </>
        )}
      </nav>
      {!ready ? (
        <p>Loading account…</p>
      ) : user ? (
        <section aria-label="Your account">
          <h2>Welcome, {user.name}</h2>
          <p>{user.email}</p>
          <p>
            {user.email_verified_at ? "Email verified" : "Email not verified"}
          </p>
          {!user.email_verified_at && (
            <button
              disabled={busy}
              onClick={() =>
                void action(async () => {
                  await request("/email/verification-notification", "POST");
                  setMessage("Verification email sent.");
                })
              }
            >
              Resend verification email
            </button>
          )}
          {page === "/verify-email" && (
            <button
              disabled={busy}
              onClick={() =>
                void action(async () => {
                  const target = new URL(query.get("url") ?? "", apiBaseUrl);
                  if (
                    target.origin !== new URL(apiBaseUrl).origin ||
                    !target.pathname.startsWith("/email/verify/")
                  )
                    throw new Error("Invalid verification link.");
                  await request(target.pathname + target.search);
                  await refresh();
                  setMessage("Email verified.");
                })
              }
            >
              Verify email
            </button>
          )}
          <button
            disabled={busy}
            onClick={() =>
              void action(async () => {
                await request("/logout", "POST");
                setUser(null);
                setPage("/login");
              })
            }
          >
            Sign out
          </button>
          <AccountSettings
            key={user.id}
            user={user}
            refresh={refresh}
            providers={config.providers ?? []}
          />
        </section>
      ) : page === "/register" && !config.registration_enabled ? (
        <p>Account registration is closed.</p>
      ) : (
        <section aria-label="Authentication">
          <h2>
            {challenge
              ? "Two-factor authentication"
              : page === "/forgot-password"
                ? "Forgot password?"
                : page === "/reset-password"
                  ? "Reset password"
                  : page === "/register"
                    ? "Create account"
                    : "Sign in"}
          </h2>
          {page === "/verify-email" && (
            <p>Sign in to verify your email address.</p>
          )}
          <form onSubmit={authSubmit}>
            {challenge ? (
              <>
                <label>
                  Authenticator code
                  <input
                    name="code"
                    autoComplete="one-time-code"
                    inputMode="numeric"
                  />
                </label>
                <label>
                  Or recovery code
                  <input name="recovery_code" autoComplete="off" />
                </label>
              </>
            ) : (
              <>
                {page === "/register" && field("name", "Name")}
                {page !== "/reset-password" && field("email", "Email", "email")}
                {page !== "/forgot-password" &&
                  field("password", "Password", "password")}
                {(page === "/register" || page === "/reset-password") &&
                  field(
                    "password_confirmation",
                    "Confirm password",
                    "password",
                  )}
              </>
            )}
            <button disabled={busy}>
              {busy
                ? "Working…"
                : challenge
                  ? "Verify code"
                  : page === "/forgot-password"
                    ? "Send reset link"
                    : page === "/reset-password"
                      ? "Reset password"
                      : page === "/register"
                        ? "Create account"
                        : "Sign in"}
            </button>
          </form>
          <a href="/forgot-password">Forgot password?</a>
          {config.providers?.map((provider) => (
            <p key={provider}>
              <a href={`${apiBaseUrl}/auth/${provider}/redirect`}>
                Continue with {provider === "github" ? "GitHub" : "Google"}
              </a>
            </p>
          ))}
        </section>
      )}
      <p role="alert">
        {message ||
          (query.get("error")
            ? "Social sign-in could not be completed. Please try again."
            : "")}
      </p>
      {Object.entries(errors).map(([name, messages]) => (
        <p role="alert" key={name}>
          {name}: {messages.join(" ")}
        </p>
      ))}
      <button disabled={checking} onClick={checkServer}>
        {checking ? "Connecting…" : "Check server"}
      </button>
      <p role="status">{status}</p>
    </main>
  );
}

export default App;
