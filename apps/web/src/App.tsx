import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type FormEvent,
} from "react";
import {
  ApiError,
  apiBaseUrl,
  invalidateAuthenticationRequests,
  request,
  type User,
} from "./api";
import { AccountPages } from "./AccountPages";
import {
  createBrowserRouter,
  RouterProvider,
  Route,
  Routes,
  Navigate,
  Link,
  useLocation,
  useNavigate,
} from "react-router";
import { Page } from "./ui/Page";
import {
  Alert,
  Card,
  SimpleGrid,
  Anchor,
  Button,
  Paper,
  Stack,
  Text,
  TextInput,
  Title,
} from "@mantine/core";
import { AppFrame } from "./ui/AppFrame";
import { CopilotProvider } from "@motominator/client/react";
import { client } from "./client";
import { CopilotPages } from "./copilot/CopilotPages";
import { Garage } from "./garage/Garage";

function Application() {
  const authGeneration = useRef(0);
  const invalidate = useCallback(() => {
    authGeneration.current++;
    invalidateAuthenticationRequests();
  }, []);
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
  const location = useLocation(),
    navigate = useNavigate();
  const page = location.pathname;
  const query = new URLSearchParams(location.search);
  const setPage = (path: string) => navigate(path);

  async function refresh() {
    const current = ++authGeneration.current;
    const result = await request<User>("/api/v1/user");
    if (current === authGeneration.current)
      setUser(typeof result.id === "number" ? result : null);
  }

  useEffect(() => {
    let alive = true;
    const currentGeneration = ++authGeneration.current;
    Promise.all([
      request<User>("/api/v1/user", "GET", undefined, false).catch(() => null),
      request<typeof config>("/api/v1/auth/config").catch(() => ({
        registration_enabled: false,
        providers: [],
      })),
    ]).then(([current, settings]) => {
      if (alive) {
        if (currentGeneration === authGeneration.current)
          setUser(current && typeof current.id === "number" ? current : null);
        setConfig(settings);
        setReady(true);
      }
    });
    const expired = () => {
      invalidate();
      setReady(true);
      setUser(null);
      setMessage("Your session expired. Please sign in again.");
    };
    window.addEventListener("auth-expired", expired);
    return () => {
      alive = false;
      invalidate();
      window.removeEventListener("auth-expired", expired);
    };
  }, [invalidate]);

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
      invalidate();
      const current = authGeneration.current;
      const result = await request<{ two_factor?: boolean }>(
        challenge
          ? "/two-factor-challenge"
          : page === "/register"
            ? "/register"
            : "/login",
        "POST",
        data,
      );
      if (current !== authGeneration.current) return;
      if (result.two_factor) {
        setChallenge(true);
        return;
      }
      await refresh();
      if (authGeneration.current !== current + 1) return;
      if (query.get("native") === "1") {
        window.location.assign(`${apiBaseUrl}/auth/mobile/complete`);
        return;
      }
      setChallenge(false);
      if (
        page !== "/verify-email" &&
        !page.startsWith("/garage") &&
        !page.startsWith("/account")
      )
        setPage("/");
    });
  }

  const field = (
    name: string,
    label: string,
    type = "text",
    defaultValue?: string,
  ) => (
    <TextInput
      key={name}
      label={label}
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
  );
  return (
    <AppFrame
      page={page}
      authenticated={user !== null}
      ready={ready}
      registrationEnabled={config.registration_enabled}
      busy={busy}
      onSignOut={() =>
        void action(async () => {
          invalidate();
          const current = authGeneration.current;
          await request("/logout", "POST");
          if (current !== authGeneration.current) return;
          setUser(null);
          setChallenge(false);
          setPage("/login");
        })
      }
    >
      {!ready ? (
        <p>Loading account…</p>
      ) : user ? (
        <CopilotProvider key={user.id} client={client}>
          <Routes>
            <Route
              path="/"
              element={
                <Page
                  title={`Welcome, ${user.name}`}
                  description="Where would you like to go?"
                >
                  <SimpleGrid cols={{ base: 1, sm: 2 }}>
                    <Card withBorder p="xl">
                      <Stack>
                        <Title order={2} size="h3">
                          Garage
                        </Title>
                        <Text c="dimmed">
                          Your motorcycles, maintenance and invoices.
                        </Text>
                        <Button component={Link} to="/garage" variant="outline">
                          Open garage
                        </Button>
                      </Stack>
                    </Card>
                    <Card withBorder p="xl">
                      <Stack>
                        <Title order={2} size="h3">
                          Copilot
                        </Title>
                        <Text c="dimmed">
                          Ask about your motorcycles and recorded history.
                        </Text>
                        <Button
                          component={Link}
                          to="/copilot"
                          variant="outline"
                        >
                          Open copilot
                        </Button>
                      </Stack>
                    </Card>
                    <Card withBorder p="xl">
                      <Stack>
                        <Title order={2} size="h3">
                          Account
                        </Title>
                        <Text c="dimmed">Your profile and preferences.</Text>
                        <Button
                          component={Link}
                          to="/account"
                          variant="outline"
                        >
                          Open account
                        </Button>
                      </Stack>
                    </Card>
                  </SimpleGrid>
                </Page>
              }
            />
            <Route
              path="/copilot/*"
              element={
                <CopilotPages verified={user.email_verified_at !== null} />
              }
            />
            <Route
              path="/garage/*"
              element={
                <Garage
                  key={user.id}
                  verified={user.email_verified_at !== null}
                />
              }
            />
            <Route
              path="/account/*"
              element={
                <AccountPages
                  user={user}
                  refresh={refresh}
                  providers={config.providers ?? []}
                  verification={
                    <>
                      {!user.email_verified_at && (
                        <Button
                          variant="outline"
                          w="fit-content"
                          disabled={busy}
                          onClick={() =>
                            void action(async () => {
                              await request(
                                "/email/verification-notification",
                                "POST",
                              );
                              setMessage("Verification email sent.");
                            })
                          }
                        >
                          Resend verification email
                        </Button>
                      )}
                    </>
                  }
                />
              }
            />
            <Route
              path="/verify-email"
              element={
                <Page
                  title="Verify email"
                  parent={{ to: "/account", label: "account" }}
                >
                  <>
                    {!user.email_verified_at && (
                      <Button
                        variant="outline"
                        w="fit-content"
                        disabled={busy}
                        onClick={() =>
                          void action(async () => {
                            await request(
                              "/email/verification-notification",
                              "POST",
                            );
                            setMessage("Verification email sent.");
                          })
                        }
                      >
                        Resend verification email
                      </Button>
                    )}
                  </>
                  <Button
                    disabled={busy}
                    w="fit-content"
                    onClick={() =>
                      void action(async () => {
                        const target = new URL(
                          query.get("url") ?? "",
                          apiBaseUrl,
                        );
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
                  </Button>
                </Page>
              }
            />
            {["login", "register", "forgot-password", "reset-password"].map(
              (path) => (
                <Route
                  key={path}
                  path={`/${path}`}
                  element={<Navigate to="/" replace />}
                />
              ),
            )}
            <Route
              path="*"
              element={
                <Page title="Page not found">
                  <Text>This page does not exist.</Text>
                  <Button component={Link} to="/" variant="outline">
                    Go home
                  </Button>
                </Page>
              }
            />
          </Routes>
        </CopilotProvider>
      ) : page === "/register" && !config.registration_enabled ? (
        <p>Account registration is closed.</p>
      ) : (
        <Paper
          component="section"
          aria-label="Authentication"
          withBorder
          p="xl"
          radius="md"
          maw={480}
          w="100%"
          mx="auto"
        >
          <Stack>
            <Title order={1} size="h2">
              {challenge
                ? "Two-factor authentication"
                : page === "/forgot-password"
                  ? "Forgot password?"
                  : page === "/reset-password"
                    ? "Reset password"
                    : page === "/register"
                      ? "Create account"
                      : "Sign in"}
            </Title>
            {page === "/verify-email" && (
              <p>Sign in to verify your email address.</p>
            )}
            <form onSubmit={authSubmit}>
              <Stack>
                {challenge ? (
                  <>
                    <TextInput
                      label="Authenticator code"
                      name="code"
                      autoComplete="one-time-code"
                      inputMode="numeric"
                    />
                    <TextInput
                      label="Or recovery code"
                      name="recovery_code"
                      autoComplete="off"
                    />
                  </>
                ) : (
                  <>
                    {page === "/register" && field("name", "Name")}
                    {page !== "/reset-password" &&
                      field("email", "Email", "email")}
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
                <Button type="submit" disabled={busy}>
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
                </Button>
              </Stack>
            </form>
            <Anchor component={Link} to="/forgot-password" size="sm">
              Forgot password?
            </Anchor>
            {config.providers?.map((provider) => (
              <p key={provider}>
                <Button
                  component="a"
                  variant="outline"
                  fullWidth
                  href={`${apiBaseUrl}/auth/${provider}/redirect`}
                >
                  Continue with {provider === "github" ? "GitHub" : "Google"}
                </Button>
              </p>
            ))}
          </Stack>
        </Paper>
      )}
      <Alert role="alert" color="blue" hidden={!message && !query.get("error")}>
        {message ||
          (query.get("error")
            ? "Social sign-in could not be completed. Please try again."
            : "")}
      </Alert>
      {Object.entries(errors).map(([name, messages]) => (
        <Alert role="alert" color="red" key={name}>
          {name}: {messages.join(" ")}
        </Alert>
      ))}
    </AppFrame>
  );
}

export default function App() {
  const [router] = useState(() =>
    createBrowserRouter([{ path: "*", element: <Application /> }]),
  );
  return <RouterProvider router={router} />;
}
