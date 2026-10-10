import { DarkTheme, DefaultTheme, ThemeProvider } from "expo-router";
import * as SplashScreen from "expo-splash-screen";
import { Platform, useColorScheme } from "react-native";
import { useEffect } from "react";
import { TamaguiProvider, useTheme } from "tamagui";
import config from "../../tamagui.config";
import { Stack } from "expo-router/stack";
import { AuthProvider, useAuth } from "@/auth/auth-context";
import { cleanupInvoiceCache } from "@/invoices/files";
import { CopilotProvider } from "@motominator/client/react";
import { useClient } from "@/client";
import { SignIn } from "@/components/sign-in";

SplashScreen.preventAutoHideAsync();
export default function RootLayout() {
  const scheme = useColorScheme();
  useEffect(() => {
    if (Platform.OS !== "web") cleanupInvoiceCache();
  }, []);
  return (
    <TamaguiProvider
      config={config}
      defaultTheme={scheme === "dark" ? "dark" : "light"}
    >
      <AuthProvider>
        <AuthenticatedLayout />
      </AuthProvider>
    </TamaguiProvider>
  );
}
function AuthenticatedLayout() {
  const { user, ready } = useAuth();
  const client = useClient();
  const scheme = useColorScheme();
  const theme = useTheme();
  const navigationTheme = scheme === "dark" ? DarkTheme : DefaultTheme;
  useEffect(() => {
    if (ready) void SplashScreen.hideAsync();
  }, [ready]);
  return (
    <ThemeProvider
      value={{
        ...navigationTheme,
        colors: {
          ...navigationTheme.colors,
          background: theme.background.val,
          card: theme.background.val,
          text: theme.color.val,
        },
      }}
    >
      {ready ? (
        user ? (
          <CopilotProvider key={user.id} client={client}>
            <Stack screenOptions={{ headerShown: false }}>
              <Stack.Screen name="(tabs)" />
              <Stack.Screen name="auth-return" />
            </Stack>
          </CopilotProvider>
        ) : (
          <SignIn />
        )
      ) : null}
    </ThemeProvider>
  );
}
