import { Stack } from "expo-router/stack";
import { router, usePathname } from "expo-router";
import { useTheme } from "tamagui";
import { Button } from "@/ui/components";
export function DomainStack({
  title,
  home,
}: {
  title: string;
  home: "/garage" | "/account" | "/copilot";
}) {
  const theme = useTheme(),
    pathname = usePathname();
  return (
    <Stack
      screenOptions={{
        title,
        headerStyle: { backgroundColor: theme.background.val },
        headerTintColor: theme.color.val,
        headerBackButtonDisplayMode: "minimal",
        headerRight:
          pathname === home
            ? undefined
            : () => (
                <Button
                  intent="secondary"
                  size="$3"
                  label={title}
                  onPress={() => router.dismissTo(home)}
                />
              ),
      }}
    />
  );
}
