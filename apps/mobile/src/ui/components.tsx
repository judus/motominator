import {
  Button as TamaguiButton,
  Card,
  H2,
  Input,
  Label,
  Paragraph,
  YStack,
  useTheme,
} from "tamagui";
import type { ComponentProps, ReactNode } from "react";
import { useId } from "react";
import { ScrollView } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

export { H2, H3, Paragraph as Text, XStack, YStack as Column } from "tamagui";

export function Button({
  label,
  intent = "primary",
  disabled,
  ...props
}: Omit<
  ComponentProps<typeof TamaguiButton>,
  "children" | "theme" | "variant"
> & {
  label: string;
  intent?: "primary" | "secondary" | "danger";
}) {
  const filled = intent === "primary";
  return (
    <TamaguiButton
      size="$4"
      {...props}
      theme={intent === "danger" ? "red" : "blue"}
      variant={filled ? undefined : "outlined"}
      backgroundColor={filled ? "$color11" : "transparent"}
      borderColor="$color11"
      hoverStyle={{ backgroundColor: filled ? "$color12" : "$color3" }}
      pressStyle={{ backgroundColor: filled ? "$color12" : "$color4" }}
      disabled={disabled}
      opacity={disabled ? 0.45 : 1}
      accessibilityRole="button"
      accessibilityLabel={label}
    >
      <TamaguiButton.Text color={filled ? "$color1" : "$color11"}>
        {label}
      </TamaguiButton.Text>
    </TamaguiButton>
  );
}

export function Field({
  label,
  id,
  ...props
}: ComponentProps<typeof Input> & { label: string; id: string }) {
  const instanceId = useId();
  const inputId = `${id}-${instanceId}`;
  return (
    <YStack gap="$2">
      <Label htmlFor={inputId}>{label}</Label>
      <Input
        id={inputId}
        accessibilityLabel={label}
        placeholderTextColor="$placeholderColor"
        size="$4"
        {...props}
      />
    </YStack>
  );
}

export function Section({ children, ...props }: ComponentProps<typeof Card>) {
  return (
    <Card
      borderWidth={1}
      borderColor="$borderColor"
      padding="$4"
      gap="$3"
      {...props}
    >
      {children}
    </Card>
  );
}

export function Screen({
  children,
  title,
  subtitle,
  standalone = false,
}: {
  children: ReactNode;
  title: string;
  subtitle?: string;
  standalone?: boolean;
}) {
  const theme = useTheme();
  return (
    <SafeAreaView
      edges={
        standalone ? ["top", "bottom", "left", "right"] : ["left", "right"]
      }
      style={{ flex: 1, backgroundColor: theme.background.val }}
    >
      <ScrollView
        style={{ flex: 1 }}
        contentInsetAdjustmentBehavior="automatic"
        keyboardShouldPersistTaps="handled"
        automaticallyAdjustKeyboardInsets
      >
        <YStack
          width="100%"
          maxWidth={800}
          alignSelf="center"
          padding="$4"
          gap="$4"
        >
          <YStack gap="$2">
            <H2>{title}</H2>
            {subtitle ? (
              <Paragraph color="$color11">{subtitle}</Paragraph>
            ) : null}
          </YStack>
          {children}
        </YStack>
      </ScrollView>
    </SafeAreaView>
  );
}
