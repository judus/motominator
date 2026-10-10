import { NativeTabs } from "expo-router/unstable-native-tabs";
import { useTheme } from "tamagui";
export default function AppTabs() {
  const theme = useTheme();
  return (
    <NativeTabs
      backgroundColor={theme.background.val}
      tintColor={theme.color.val}
    >
      <NativeTabs.Trigger name="index">
        <NativeTabs.Trigger.Label>Home</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf="house" md="home" />
      </NativeTabs.Trigger>
      <NativeTabs.Trigger name="garage">
        <NativeTabs.Trigger.Label>Garage</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf="motorcycle" md="garage" />
      </NativeTabs.Trigger>
      <NativeTabs.Trigger name="copilot">
        <NativeTabs.Trigger.Label>Copilot</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf="bubble.left.and.bubble.right" md="chat" />
      </NativeTabs.Trigger>
      <NativeTabs.Trigger name="account">
        <NativeTabs.Trigger.Label>Account</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf="person.crop.circle" md="person" />
      </NativeTabs.Trigger>
    </NativeTabs>
  );
}
