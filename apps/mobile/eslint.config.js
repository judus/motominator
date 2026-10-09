const { defineConfig } = require("eslint/config");
const expoConfig = require("eslint-config-expo/flat");

module.exports = defineConfig([
  expoConfig,
  { ignores: ["dist/*"] },
  {
    files: ["src/**/*.{ts,tsx}"],
    rules: {
      "no-restricted-imports": [
        "error",
        {
          patterns: [
            {
              regex: "^@motominator/(web|server)(/|$)|(^|/)(web|server)(/|$)",
              message:
                "Do not import another app. Move concrete common client behavior into @motominator/client.",
            },
            {
              regex:
                "^@motominator/client/(src|dist|react/)|(^|/)packages/client(/|$)",
              message:
                "Import the public @motominator/client or @motominator/client/react entry instead of package internals.",
            },
          ],
        },
      ],
    },
  },
]);
