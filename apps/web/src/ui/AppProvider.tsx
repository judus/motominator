import { Button, createTheme, MantineProvider } from "@mantine/core";
import type { PropsWithChildren } from "react";
import "@mantine/core/styles.css";

const theme = createTheme({
  primaryColor: "blue",
  components: {
    Button: Button.extend({
      defaultProps: { variant: "filled", color: "blue" },
    }),
  },
});

export function AppProvider({ children }: PropsWithChildren) {
  return (
    <MantineProvider theme={theme} defaultColorScheme="auto">
      {children}
    </MantineProvider>
  );
}
