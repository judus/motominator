import { MantineProvider } from "@mantine/core";
import { render as renderComponent } from "@testing-library/react";
import type { ReactNode } from "react";

export {
  act,
  fireEvent,
  screen,
  waitFor,
  within,
} from "@testing-library/react";

export function render(ui: ReactNode) {
  return renderComponent(ui, {
    wrapper: ({ children }) => (
      <MantineProvider env="test">{children}</MantineProvider>
    ),
  });
}
