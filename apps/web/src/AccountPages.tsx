import { Badge, Button, Card, SimpleGrid, Stack, Text } from "@mantine/core";
import { Link, Route, Routes } from "react-router";
import type { User } from "./api";
import { AccountSettings } from "./AccountSettings";
import { AiSettings } from "./ai/AiSettings";
import { Page } from "./ui/Page";

const settings = [
  ["profile", "Profile", "Name and email address"],
  ["password", "Password", "Change your sign-in password"],
  [
    "two-factor",
    "Two-factor authentication",
    "Authenticator and recovery codes",
  ],
  ["social", "Social accounts", "Manage linked sign-in providers"],
  ["devices", "Signed-in devices", "Review and revoke device access"],
] as const;
export function AccountPages({
  user,
  refresh,
  providers,
  verification,
}: {
  user: User;
  refresh: () => Promise<void>;
  providers: string[];
  verification: React.ReactNode;
}) {
  return (
    <Routes>
      <Route
        index
        element={
          <Page title="Account" description={user.email}>
            <Badge
              w="fit-content"
              color={user.email_verified_at ? "teal" : "yellow"}
            >
              {user.email_verified_at ? "Email verified" : "Email not verified"}
            </Badge>
            {verification}
            <SimpleGrid cols={{ base: 1, sm: 2 }}>
              {settings
                .filter(
                  ([section]) =>
                    section !== "social" ||
                    providers.length > 0 ||
                    (user.providers?.length ?? 0) > 0,
                )
                .map(([section, label, description]) => (
                  <Card withBorder p="lg" key={section}>
                    <Stack>
                      <Text fw={600}>{label}</Text>
                      <Text c="dimmed" size="sm">
                        {description}
                      </Text>
                      <Button
                        component={Link}
                        to={`/account/${section}`}
                        variant="outline"
                      >
                        {label}
                      </Button>
                    </Stack>
                  </Card>
                ))}
              <Card withBorder p="lg">
                <Stack>
                  <Text fw={600}>AI settings</Text>
                  <Text c="dimmed" size="sm">
                    Your provider, model and private API key
                  </Text>
                  <Button component={Link} to="/account/ai" variant="outline">
                    AI settings
                  </Button>
                </Stack>
              </Card>
            </SimpleGrid>
          </Page>
        }
      />
      {settings.map(([section, title]) => (
        <Route
          key={section}
          path={section}
          element={
            <Page title={title} parent={{ to: "/account", label: "account" }}>
              {section === "profile" && verification}
              <AccountSettings
                key={`${user.id}:${section}`}
                section={section}
                user={user}
                refresh={refresh}
                providers={providers}
              />
            </Page>
          }
        />
      ))}
      <Route
        path="ai"
        element={
          <Page
            title="AI settings"
            parent={{ to: "/account", label: "account" }}
          >
            <AiSettings
              key={user.id}
              verified={user.email_verified_at !== null}
            />
          </Page>
        }
      />
      <Route
        path="*"
        element={
          <Page
            title="Page not found"
            parent={{ to: "/account", label: "account" }}
          >
            <Text>This account page does not exist.</Text>
          </Page>
        }
      />
    </Routes>
  );
}
