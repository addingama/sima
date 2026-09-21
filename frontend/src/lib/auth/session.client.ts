"use client";

import type { SimaUser } from "@/lib/api/types";
import { AUTH_TOKEN_COOKIE, AUTH_USER_COOKIE } from "@/lib/auth/constants";

function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

  return match ? decodeURIComponent(match[1]) : null;
}

export function getClientToken(): string | null {
  return readCookie(AUTH_TOKEN_COOKIE);
}

export function getClientUser(): SimaUser | null {
  const raw = readCookie(AUTH_USER_COOKIE);

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as SimaUser;
  } catch {
    return null;
  }
}

export function setClientSession(token: string, user: SimaUser, remember = false): void {
  const maxAge = remember ? 60 * 60 * 24 * 30 : 60 * 60 * 8;
  const secure = window.location.protocol === "https:" ? "; Secure" : "";

  // biome-ignore lint/suspicious/noDocumentCookie: API sesi ini sinkron; Cookie Store API belum didukung merata.
  document.cookie = `${AUTH_TOKEN_COOKIE}=${encodeURIComponent(token)}; Path=/; Max-Age=${maxAge}; SameSite=Lax${secure}`;
  // biome-ignore lint/suspicious/noDocumentCookie: API sesi ini sinkron; Cookie Store API belum didukung merata.
  document.cookie = `${AUTH_USER_COOKIE}=${encodeURIComponent(JSON.stringify(user))}; Path=/; Max-Age=${maxAge}; SameSite=Lax${secure}`;
}

export function clearClientSession(): void {
  // biome-ignore lint/suspicious/noDocumentCookie: API sesi ini sinkron; Cookie Store API belum didukung merata.
  document.cookie = `${AUTH_TOKEN_COOKIE}=; Path=/; Max-Age=0`;
  // biome-ignore lint/suspicious/noDocumentCookie: API sesi ini sinkron; Cookie Store API belum didukung merata.
  document.cookie = `${AUTH_USER_COOKIE}=; Path=/; Max-Age=0`;
}
