import { createClient, type Request } from "@motominator/client";
import { useMemo } from "react";
import { authenticatedApi, AuthError } from "./auth/client";
import { useAuth } from "./auth/auth-context";

export function useClient() {
  const { refresh } = useAuth();
  return useMemo(
    () =>
      createClient(
        async <T>(...args: Parameters<Request>): Promise<T> => {
          try {
            return await authenticatedApi<T>(...args);
          } catch (failure) {
            if (failure instanceof AuthError && failure.status === 401)
              void refresh();
            throw failure;
          }
        },
        (callback, delay) => {
          const timer = setTimeout(callback, delay);
          return () => clearTimeout(timer);
        },
      ),
    [refresh],
  );
}
export function aiSettingsLoadError(failure: unknown): string {
  return failure instanceof AuthError && failure.status === 403
    ? "Sign out and sign in again to refresh this device's AI permissions."
    : "Unable to load AI settings. Please retry.";
}
