import {
  pickInvoice,
  clearPickedInvoice,
  cleanupInvoiceCache,
} from "../src/invoices/files";
import { Platform } from "react-native";
import * as DocumentPicker from "expo-document-picker";

const mockFiles = new Map<string, { modified: number }>();
const mockDeleted: string[] = [];
let mockCopy: (() => Promise<void>) | null = null;
jest.mock("expo-document-picker", () => ({ getDocumentAsync: jest.fn() }));
jest.mock("expo-image-picker", () => ({}));
jest.mock("expo-sharing", () => ({}));
jest.mock("expo-crypto", () => ({ randomUUID: () => "random-copy" }));
jest.mock("../src/auth/client", () => ({
  apiBaseUrl: "http://localhost",
  AuthError: Error,
  readToken: jest.fn(),
}));
jest.mock("expo-file-system", () => {
  class File {
    uri: string;
    constructor(parent: string | { uri: string }, name?: string) {
      this.uri = name
        ? `${typeof parent === "string" ? parent : parent.uri}/${name}`
        : typeof parent === "string"
          ? parent
          : parent.uri;
    }
    get exists() {
      return mockFiles.has(this.uri);
    }
    get lastModified() {
      return mockFiles.get(this.uri)?.modified ?? null;
    }
    async copy(destination: File) {
      mockFiles.set(destination.uri, { modified: Date.now() });
      if (mockCopy) await mockCopy();
    }
    delete() {
      mockDeleted.push(this.uri);
      mockFiles.delete(this.uri);
    }
  }
  class Directory {
    uri: string;
    exists = true;
    constructor(parent: { uri: string }, name: string) {
      this.uri = `${parent.uri}${name}`;
    }
    create() {}
    list() {
      return [...mockFiles.keys()]
        .filter((uri) => uri.startsWith(`${this.uri}/`))
        .map((uri) => new File(uri));
    }
  }
  return { File, Directory, Paths: { cache: { uri: "file:///cache/" } } };
});
beforeEach(() => {
  jest.clearAllMocks();
  mockFiles.clear();
  mockDeleted.length = 0;
  mockCopy = null;
});
it("owns an independent copy and never deletes an original document", async () => {
  const original = "file:///documents/bill.pdf";
  mockFiles.set(original, { modified: Date.now() });
  jest.mocked(DocumentPicker.getDocumentAsync).mockResolvedValue({
    canceled: false,
    assets: [
      {
        uri: original,
        name: "bill.pdf",
        lastModified: Date.now(),
        mimeType: "application/pdf",
      },
    ],
  });
  const picked = (await pickInvoice("document"))!;
  expect(picked.uri).toBe("file:///cache/motominator-invoices/random-copy.pdf");
  clearPickedInvoice({ uri: original, name: "bill.pdf" });
  expect(mockDeleted).not.toContain(original);
  clearPickedInvoice(picked);
  expect(mockDeleted).toContain(picked.uri);
  expect(mockFiles.has(original)).toBe(true);
});
it("releases picker-generated cache copies but retains the selected feature copy", async () => {
  const original = "file:///cache/DocumentPicker/bill.pdf";
  mockFiles.set(original, { modified: Date.now() });
  jest.mocked(DocumentPicker.getDocumentAsync).mockResolvedValue({
    canceled: false,
    assets: [{ uri: original, name: "bill.pdf", lastModified: Date.now() }],
  });
  const picked = (await pickInvoice("document"))!;
  expect(mockDeleted).toContain(original);
  expect(mockFiles.has(picked.uri)).toBe(true);
  clearPickedInvoice(picked);
});
it("sweeps old unleased feature copies without touching another feature's cache", () => {
  const old = "file:///cache/motominator-invoices/old";
  const other = "file:///cache/some-other-feature";
  const recent = "file:///cache/motominator-invoices/recent";
  mockFiles.set(old, { modified: Date.now() - 48 * 60 * 60 * 1000 });
  mockFiles.set(other, { modified: 0 });
  mockFiles.set(recent, { modified: Date.now() });
  cleanupInvoiceCache();
  expect(mockDeleted).toEqual([old]);
});

it("keeps an active leased copy even when its timestamp is old", async () => {
  let finish!: () => void;
  const original = "file:///documents/bill.pdf";
  mockFiles.set(original, { modified: Date.now() });
  jest.mocked(DocumentPicker.getDocumentAsync).mockResolvedValue({
    canceled: false,
    assets: [{ uri: original, name: "bill.pdf", lastModified: Date.now() }],
  });
  mockCopy = () =>
    new Promise((resolve) => {
      finish = resolve;
    });
  const selection = pickInvoice("document");
  for (let attempt = 0; attempt < 20 && !finish; attempt++)
    await Promise.resolve();
  expect(finish).toBeDefined();
  const copy = "file:///cache/motominator-invoices/random-copy.pdf";
  mockFiles.set(copy, { modified: 0 });
  cleanupInvoiceCache();
  expect(mockDeleted).not.toContain(copy);
  finish();
  clearPickedInvoice((await selection)!);
});

it("releases failed partial copies and SDK cache copies", async () => {
  const original = "file:///cache/DocumentPicker/bill.pdf";
  mockFiles.set(original, { modified: Date.now() });
  jest.mocked(DocumentPicker.getDocumentAsync).mockResolvedValue({
    canceled: false,
    assets: [{ uri: original, name: "bill.pdf", lastModified: Date.now() }],
  });
  mockCopy = async () => {
    throw new Error("Disk full.");
  };
  await expect(pickInvoice("document")).rejects.toThrow("Disk full.");
  expect(mockDeleted).toContain(original);
  expect(mockDeleted).toContain(
    "file:///cache/motominator-invoices/random-copy.pdf",
  );
});

it("does not access native filesystem APIs on the web", async () => {
  jest.replaceProperty(Platform, "OS", "web");
  const old = "file:///cache/motominator-invoices/old";
  mockFiles.set(old, { modified: 0 });
  cleanupInvoiceCache();
  clearPickedInvoice({ uri: old, name: "bill.pdf" });
  expect(mockDeleted).toEqual([]);
  await expect(pickInvoice("document")).rejects.toThrow("Use the browser app");
  expect(DocumentPicker.getDocumentAsync).not.toHaveBeenCalled();
});
