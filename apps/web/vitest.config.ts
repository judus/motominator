import viteConfig from "./vite.config.ts";
import { mergeConfig, defineConfig } from "vitest/config";

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      // Keep UI dependencies in Vite's resolver so React deduplication also applies
      // in jsdom; Node's external module resolution would pick Expo's React copy.
      server: { deps: { inline: true } },
      include: ["src/**/*.test.{ts,tsx}"],
      environment: "jsdom",
      setupFiles: ["./src/test/setup.ts"],
      clearMocks: true,
      restoreMocks: true,
    },
  }),
);
