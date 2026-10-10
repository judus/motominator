import { defaultConfig } from "@tamagui/config/v5";
import { createTamagui } from "tamagui";

const config = createTamagui({
  ...defaultConfig,
  settings: { ...defaultConfig.settings, onlyAllowShorthands: false },
});
type AppConfig = typeof config;
declare module "tamagui" {
  interface TamaguiCustomConfig extends AppConfig {}
}
export default config;
