import { getClientToken } from "@/lib/auth/session.client";

import type { ApiEnvelope, ListParams } from "./types";

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly code?: string,
    readonly fields?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

function buildQuery(params?: ListParams): string {
  if (!params) {
    return "";
  }

  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "") {
      continue;
    }

    search.set(key, String(value));
  }

  const query = search.toString();

  return query ? `?${query}` : "";
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
  token?: string | null,
): Promise<ApiEnvelope<T>> {
  const authToken = token ?? getClientToken();
  const headers = new Headers(options.headers);

  headers.set("Accept", "application/json");

  if (!(options.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }

  if (authToken) {
    headers.set("Authorization", `Bearer ${authToken}`);
  }

  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers,
  });

  const payload = (await response.json()) as ApiEnvelope<T>;

  if (!response.ok || !payload.success) {
    throw new ApiError(
      payload.message ?? "Permintaan gagal.",
      response.status,
      payload.errors?.code,
      payload.errors?.fields,
    );
  }

  return payload;
}

export async function apiGet<T>(path: string, params?: ListParams, token?: string | null) {
  return apiFetch<T>(`${path}${buildQuery(params)}`, { method: "GET" }, token);
}

export async function apiPost<T>(path: string, body?: unknown, token?: string | null) {
  return apiFetch<T>(
    path,
    {
      method: "POST",
      body: body === undefined ? undefined : JSON.stringify(body),
    },
    token,
  );
}

export async function apiPut<T>(path: string, body?: unknown, token?: string | null) {
  return apiFetch<T>(
    path,
    {
      method: "PUT",
      body: body === undefined ? undefined : JSON.stringify(body),
    },
    token,
  );
}

export async function apiDelete<T>(path: string, token?: string | null) {
  return apiFetch<T>(path, { method: "DELETE" }, token);
}
