import { Platform } from "react-native";
import * as DocumentPicker from "expo-document-picker";
import * as ImagePicker from "expo-image-picker";
import { Directory, File, Paths } from "expo-file-system";
import { randomUUID } from "expo-crypto";
import * as Sharing from "expo-sharing";
import { apiBaseUrl, AuthError, readToken } from "../auth/client";
import type { InvoiceImport } from "@motominator/client";

export interface PickedInvoice {
  uri: string;
  name: string;
}
let invoiceCache: Directory | null = null;
function ownedCache(): Directory {
  return (invoiceCache ??= new Directory(Paths.cache, "motominator-invoices"));
}
const leasedFiles = new Set<string>();

// Sweep only this feature's copies. Active selections/uploads keep their lease.
export function cleanupInvoiceCache() {
  if (Platform.OS === "web") return;
  try {
    const cache = ownedCache();
    if (!cache.exists) return;
    const cutoff = Date.now() - 24 * 60 * 60 * 1000;
    for (const file of cache.list()) {
      if (
        file instanceof File &&
        !leasedFiles.has(file.uri) &&
        file.lastModified !== null &&
        file.lastModified < cutoff
      )
        file.delete();
    }
  } catch {
    /* Cache availability/eviction must not prevent authentication or picking. */
  }
}
async function ownPickedInvoice(picked: PickedInvoice): Promise<PickedInvoice> {
  cleanupInvoiceCache();
  const cache = ownedCache();
  const source = new File(picked.uri);
  const extension = picked.name.match(/\.(pdf|jpe?g|png)$/i)?.[1].toLowerCase();
  const copy = new File(
    cache,
    `${randomUUID()}${extension ? `.${extension}` : ""}`,
  );
  leasedFiles.add(copy.uri);
  try {
    cache.create({ idempotent: true, intermediates: true });
    await source.copy(copy);
    return { uri: copy.uri, name: picked.name };
  } catch (error) {
    clearPickedInvoice({ uri: copy.uri, name: picked.name });
    throw error;
  } finally {
    // Picker-generated cache copies are ours; original document/photo paths are not.
    try {
      if (source.uri.startsWith(Paths.cache.uri) && source.exists)
        source.delete();
    } catch {
      /* The OS can evict the picker copy independently of our owned copy. */
    }
  }
}

export async function pickInvoice(
  kind: "document" | "camera" | "photo",
): Promise<PickedInvoice | null> {
  if (Platform.OS === "web")
    throw new Error("Use the browser app to upload invoices on the web.");
  if (kind === "document") {
    const result = await DocumentPicker.getDocumentAsync({
      type: ["application/pdf", "image/jpeg", "image/png"],
      copyToCacheDirectory: true,
      multiple: false,
    });
    return result.canceled
      ? null
      : ownPickedInvoice({
          uri: result.assets[0].uri,
          name: result.assets[0].name,
        });
  }
  if (
    kind === "camera" &&
    !(await ImagePicker.requestCameraPermissionsAsync()).granted
  )
    throw new Error(
      "Allow camera access to photograph an invoice, or choose a file instead.",
    );
  const options: ImagePicker.ImagePickerOptions = {
    mediaTypes: ["images"],
    quality: 0.8,
    allowsEditing: false,
    preferredAssetRepresentationMode:
      ImagePicker.UIImagePickerPreferredAssetRepresentationMode.Compatible,
  };
  const result =
    kind === "camera"
      ? await ImagePicker.launchCameraAsync(options)
      : await ImagePicker.launchImageLibraryAsync(options);
  if (result.canceled) return null;
  const asset = result.assets[0];
  return ownPickedInvoice({
    uri: asset.uri,
    name: asset.fileName ?? "invoice.jpg",
  });
}
export function invoiceBody(picked: PickedInvoice): FormData {
  const body = new FormData();
  body.append("file", new File(picked.uri), picked.name);
  return body;
}
export function clearPickedInvoice(picked: PickedInvoice) {
  leasedFiles.delete(picked.uri);
  if (Platform.OS === "web") return;
  if (picked.uri.startsWith(`${ownedCache().uri.replace(/\/$/, "")}/`)) {
    try {
      const file = new File(picked.uri);
      if (file.exists) file.delete();
    } catch {
      /* Cache eviction can occur before cleanup. */
    }
  }
}
export async function shareInvoice(path: string, record: InvoiceImport) {
  if (!(await Sharing.isAvailableAsync()))
    throw new Error("Document sharing is unavailable on this device.");
  const token = await readToken();
  if (!token)
    throw new AuthError("Your session expired. Please sign in again.", 401);
  const extension =
    record.mime === "application/pdf"
      ? "pdf"
      : record.mime === "image/png"
        ? "png"
        : "jpg";
  const file = new File(
    Paths.cache,
    `invoice-${record.id}-${Date.now()}.${extension}`,
  );
  try {
    await File.downloadFileAsync(`${apiBaseUrl}${path}`, file, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token.token}`,
      },
    });
    await Sharing.shareAsync(file.uri, {
      mimeType: record.mime,
      dialogTitle: "Open invoice",
    });
  } finally {
    if (file.exists) file.delete();
  }
}
