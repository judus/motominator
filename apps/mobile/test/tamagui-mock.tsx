// Logic tests use React Native controls; Metro exports cover Tamagui resolution.
import type { ComponentProps } from "react";
import { Pressable, Text, TextInput, View } from "react-native";

export const YStack = View;
export const XStack = View;
export const Card = View;
export const H2 = Text;
export const H3 = Text;
export const Paragraph = Text;
export const Label = Text;
export function Input({
  disabled,
  ...props
}: ComponentProps<typeof TextInput> & { disabled?: boolean }) {
  return <TextInput {...props} editable={!disabled} />;
}
function MockButton({
  children,
  disabled,
  ...props
}: ComponentProps<typeof Pressable>) {
  return (
    <Pressable
      {...props}
      disabled={disabled}
      accessibilityState={{ ...props.accessibilityState, disabled: !!disabled }}
    >
      <Text>{children as string}</Text>
    </Pressable>
  );
}
export const Button = Object.assign(MockButton, { Text });
export const useTheme = () => ({
  background: { val: "white" },
  color: { val: "black" },
  placeholderColor: { val: "gray" },
});
export const getTokens = () => ({
  space: { $3: { val: 12 }, $4: { val: 16 } },
});
