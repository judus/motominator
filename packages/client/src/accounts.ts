import type { Request } from "./client";

export interface AccountDevice {
  id: number;
  name: string;
  created_at: string | null;
  last_used_at: string | null;
  expires_at: string | null;
  current_device: boolean;
}
export type TwoFactorOperation =
  "enable" | "disable" | "confirm" | "codes" | "regenerate";
export interface TwoFactorSetup {
  secret: string | null;
  codes: string[];
}
export interface ProfileInput {
  name: string;
  email: string;
  current_password?: string;
}
export interface PasswordInput {
  current_password: string;
  password: string;
  password_confirmation: string;
}
export interface SocialLinkIntent {
  code: string;
  url: string;
}

export function accountOperations(request: Request) {
  const path = "/api/v1/account";
  return {
    profile: (data: ProfileInput) => request(`${path}/profile`, "PUT", data),
    password: (data: PasswordInput) => request(`${path}/password`, "PUT", data),
    verification: () => request(`${path}/verification`, "POST"),
    twoFactor: (
      password: string,
      operation: TwoFactorOperation,
      code?: string,
    ) =>
      request<TwoFactorSetup>(`${path}/two-factor`, "POST", {
        password,
        operation,
        code,
      }),
    devices: () => request<AccountDevice[]>(`${path}/devices`),
    revokeDevice: (id: number, password: string) =>
      request<{ current_device: boolean }>(`${path}/devices/${id}`, "DELETE", {
        password,
      }),
    unlinkSocial: (provider: string, password: string) =>
      request(`${path}/social/${encodeURIComponent(provider)}`, "DELETE", {
        password,
      }),
    startSocialLink: (provider: string, password: string, challenge: string) =>
      request<SocialLinkIntent>(`${path}/social/link`, "POST", {
        provider,
        password,
        challenge,
      }),
    completeSocialLink: (code: string, verifier: string) =>
      request(`${path}/social/link/complete`, "POST", { code, verifier }),
  };
}
