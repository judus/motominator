import { copilotOperations, type StreamRequest } from "./copilot";
import { accountOperations } from "./accounts";
import type { InvoiceImport, InvoiceDraft } from "./invoices";
import type {
  AiSettings,
  GarageData,
  MaintenanceRecord,
  Motorcycle,
  Page,
} from "./models";

// Apps own HTTP transport, credentials, session expiry and platform APIs.
export type Request = <T>(
  path: string,
  method?: string,
  data?: unknown,
) => Promise<T>;
export const aiSettingsPath = "/api/v1/ai/settings";
const motorcyclesPath = "/api/v1/motorcycles";
export type Schedule = (callback: () => void, delay: number) => () => void;
export function createClient(
  request: Request,
  schedule?: Schedule,
  stream?: StreamRequest,
) {
  const motorcyclePath = (id: number) => `${motorcyclesPath}/${id}`;
  const maintenancePath = (id: number) =>
    `${motorcyclePath(id)}/maintenance-records`;
  const importsPath = (id: number) => `${motorcyclePath(id)}/invoice-imports`;
  const importPath = (bike: number, id: number) => `${importsPath(bike)}/${id}`;
  return {
    schedule,
    copilot: copilotOperations(request, stream),
    account: accountOperations(request),
    invoices: {
      list: (bike: number, page: number) =>
        request<Page<InvoiceImport>>(`${importsPath(bike)}?page=${page}`),
      get: async (bike: number, id: number) =>
        (await request<{ data: InvoiceImport }>(importPath(bike, id))).data,
      // The app prepares multipart data using its platform's file API.
      upload: async (bike: number, body: unknown) =>
        (
          await request<{ data: InvoiceImport }>(
            importsPath(bike),
            "POST",
            body,
          )
        ).data,
      extract: async (bike: number, id: number) =>
        (
          await request<{ data: InvoiceImport }>(
            `${importPath(bike, id)}/extract`,
            "POST",
          )
        ).data,
      save: async (
        bike: number,
        id: number,
        draft: InvoiceDraft,
        version: number,
      ) =>
        (
          await request<{ data: InvoiceImport }>(importPath(bike, id), "PUT", {
            draft,
            version,
          })
        ).data,
      confirm: async (
        bike: number,
        id: number,
        draft: InvoiceDraft,
        version: number,
      ) =>
        (
          await request<{ data: InvoiceImport }>(
            `${importPath(bike, id)}/confirm`,
            "POST",
            { draft, version },
          )
        ).data,
      downloadPath: (bike: number, id: number) =>
        `${importPath(bike, id)}/download`,
    },
    garage: {
      motorcycles: (page: number) =>
        request<Page<Motorcycle>>(`${motorcyclesPath}?page=${page}`),
      motorcycle: async (id: number) =>
        (await request<{ data: Motorcycle }>(motorcyclePath(id))).data,
      saveMotorcycle: async (data: GarageData, id?: number) =>
        (
          await request<{ data: Motorcycle }>(
            id === undefined ? motorcyclesPath : motorcyclePath(id),
            id === undefined ? "POST" : "PUT",
            data,
          )
        ).data,
      maintenance: (id: number, page: number) =>
        request<Page<MaintenanceRecord>>(`${maintenancePath(id)}?page=${page}`),
      saveMaintenance: (id: number, data: GarageData, recordId?: number) =>
        request<{ data: MaintenanceRecord }>(
          recordId === undefined
            ? maintenancePath(id)
            : `${maintenancePath(id)}/${recordId}`,
          recordId === undefined ? "POST" : "PUT",
          data,
        ),
      maintenanceRecord: async (id: number, record: number) =>
        (
          await request<{ data: MaintenanceRecord }>(
            `${maintenancePath(id)}/${record}`,
          )
        ).data,
    },
    ai: {
      settings: () => request<AiSettings>(aiSettingsPath),
      save: (data: { provider: string; model: string; api_key?: string }) =>
        request<AiSettings>(aiSettingsPath, "PUT", data),
      remove: () => request<AiSettings>(aiSettingsPath, "DELETE"),
      test: () =>
        request<{ message: string }>(`${aiSettingsPath}/test`, "POST"),
    },
  };
}
export type Client = ReturnType<typeof createClient>;
