import {
  Anchor,
  Breadcrumbs,
  Button,
  Group,
  Stack,
  Text,
  Title,
} from "@mantine/core";
import { Link, useLocation } from "react-router";
import { useEffect, useRef, type ReactNode } from "react";

export function Page({
  title,
  description,
  parent,
  actions,
  children,
}: {
  title: string;
  description?: string;
  parent?: { to: string; label: string };
  actions?: ReactNode;
  children: ReactNode;
}) {
  const heading = useRef<HTMLHeadingElement>(null);
  const location = useLocation();
  useEffect(() => {
    heading.current?.focus();
  }, [location.pathname]);
  return (
    <Stack gap="lg">
      {parent && (
        <Group justify="space-between">
          <Button
            component={Link}
            to={parent.to}
            variant="subtle"
            size="compact-sm"
          >
            ← Back to {parent.label}
          </Button>
          {location.pathname.startsWith("/garage/") &&
            parent.to !== "/garage" && (
              <Anchor component={Link} to="/garage" size="sm">
                Garage home
              </Anchor>
            )}
        </Group>
      )}
      <Group justify="space-between" align="flex-start">
        <Stack gap="xs">
          {parent && (
            <Breadcrumbs>
              <Anchor component={Link} to={parent.to}>
                {parent.label}
              </Anchor>
              <Text>{title}</Text>
            </Breadcrumbs>
          )}
          <Title ref={heading} tabIndex={-1} order={1} size="h2">
            {title}
          </Title>
          {description && <Text c="dimmed">{description}</Text>}
        </Stack>
        {actions}
      </Group>
      {children}
    </Stack>
  );
}
