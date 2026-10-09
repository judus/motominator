import {
  Anchor,
  AppShell,
  Burger,
  Button,
  Container,
  Group,
  Stack,
  Text,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { Link } from "react-router";
import type { ReactNode } from "react";

export function AppFrame({
  children,
  page,
  authenticated,
  ready,
  registrationEnabled,
  busy,
  onSignOut,
}: {
  children: ReactNode;
  page: string;
  authenticated: boolean;
  ready: boolean;
  registrationEnabled: boolean;
  busy: boolean;
  onSignOut: () => void;
}) {
  const [opened, { toggle, close }] = useDisclosure();
  function link(to: string, label: string) {
    const active = page === to || (to !== "/" && page.startsWith(to + "/"));
    return (
      <Button
        component={Link}
        to={to}
        variant={active ? "light" : "subtle"}
        aria-current={active ? "page" : undefined}
        onClick={close}
        justify="flex-start"
      >
        {label}
      </Button>
    );
  }
  return (
    <AppShell
      header={{ height: 64 }}
      navbar={{
        width: 220,
        breakpoint: "sm",
        collapsed: { mobile: !opened, desktop: !authenticated },
      }}
      padding="md"
    >
      <AppShell.Header>
        <Group h="100%" px="md" justify="space-between">
          <Group>
            {authenticated && (
              <Burger
                opened={opened}
                onClick={toggle}
                hiddenFrom="sm"
                size="sm"
                aria-label="Toggle navigation"
                aria-expanded={opened}
              />
            )}
            <Anchor
              component={Link}
              to="/"
              fw={700}
              size="xl"
              c="inherit"
              underline="never"
              onClick={close}
            >
              Motominator
            </Anchor>
          </Group>
          <Anchor href="#main-content" size="sm">
            Skip to content
          </Anchor>
          {ready && !authenticated && (
            <Group visibleFrom="sm">
              {link("/login", "Sign in")}
              {registrationEnabled && link("/register", "Create account")}
            </Group>
          )}
        </Group>
      </AppShell.Header>
      {authenticated && (
        <AppShell.Navbar p="md" aria-label="Main navigation">
          <Stack h="100%" gap="xs">
            <Text size="xs" c="dimmed" tt="uppercase" fw={600}>
              Your space
            </Text>
            {link("/", "Home")}
            {link("/garage", "Garage")}
            {link("/account", "Account")}
            <Button
              mt="auto"
              variant="outline"
              disabled={busy}
              onClick={onSignOut}
            >
              Sign out
            </Button>
          </Stack>
        </AppShell.Navbar>
      )}
      <AppShell.Main id="main-content" tabIndex={-1}>
        <Container size="lg" py="lg">
          <Stack gap="xl">{children}</Stack>
        </Container>
      </AppShell.Main>
    </AppShell>
  );
}
