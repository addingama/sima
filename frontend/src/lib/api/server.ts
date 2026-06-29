import { cookies } from "next/headers";

import { AUTH_TOKEN_COOKIE } from "@/lib/auth/constants";

import { apiFetch, apiGet } from "./client";
import type { ApiEnvelope, ListParams } from "./types";

export async function getServerToken(): Promise<string | undefined> {
  const cookieStore = await cookies();

  return cookieStore.get(AUTH_TOKEN_COOKIE)?.value;
}

export async function serverApiFetch<T>(path: string, options: RequestInit = {}): Promise<ApiEnvelope<T>> {
  const token = await getServerToken();

  return apiFetch<T>(path, options, token);
}

export async function serverApiGet<T>(path: string, params?: ListParams) {
  const token = await getServerToken();

  return apiGet<T>(path, params, token);
}
