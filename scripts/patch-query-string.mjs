// query-string 7 uses CommonJS; the security-fixed decoder exports ESM default.
// Remove this bridge when Expo Router adopts a compatible query-string release.
import { createHash } from "node:crypto";
import { readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { createRequire } from "node:module";

const require = createRequire(import.meta.url);
const filename = require.resolve("query-string");
const metadata = require("query-string/package.json");
const originalHash =
  "caa3f2c8b45dfe1e91db22ae10743af68de8d96f26515132bb52485ec0f037fa";
const originalImport =
  "const decodeComponent = require('decode-uri-component');";
const patchedImport =
  "const decodeComponent = require('decode-uri-component').default;";
const source = readFileSync(filename, "utf8");
const original = source.replace(patchedImport, originalImport);
const hash = createHash("sha256").update(original).digest("hex");

if (metadata.version !== "7.1.3" || hash !== originalHash) {
  throw new Error(
    "Review query-string security bridge: upstream source changed.",
  );
}
const decoder = createRequire(filename)("decode-uri-component");
const decoderFilename = createRequire(filename).resolve("decode-uri-component");
const decoderMetadata = JSON.parse(
  readFileSync(join(dirname(decoderFilename), "package.json"), "utf8"),
);
if (
  decoderMetadata.version !== "0.5.0" ||
  typeof decoder.default !== "function"
) {
  throw new Error("Expected security-fixed decoder ESM default export.");
}
if (source === original) {
  writeFileSync(filename, original.replace(originalImport, patchedImport));
}
