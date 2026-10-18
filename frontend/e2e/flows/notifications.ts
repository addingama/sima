import { type APIRequestContext, expect } from "@playwright/test";

import { type Role, roleAccounts } from "../fixtures/auth";

export const UNREAD_EXCERPT = "mohon dicek alamat belum";
export const READ_EXCERPT = "catatan yang sudah dibaca";

export type SeededNotice = {
  grantId: number;
  applicationNumber: string;
  excerpt: string;
};

async function tokenFor(request: APIRequestContext, apiUrl: string, role: Role): Promise<string> {
  const response = await request.post(`${apiUrl}/login`, {
    data: { login: roleAccounts[role], password: "password", device_name: "playwright-notifications" },
  });
  expect(response.ok(), await response.text()).toBeTruthy();

  return (await response.json()).data.token as string;
}

function auth(token: string) {
  return { Authorization: `Bearer ${token}`, Accept: "application/json" };
}

async function createMentionedGrant(
  request: APIRequestContext,
  apiUrl: string,
  token: string,
  verifierId: number,
  recipient: string,
  note: string,
): Promise<SeededNotice> {
  const created = await request.post(`${apiUrl}/grant-applications`, {
    headers: auth(token),
    data: {
      beneficiary_type: "individual",
      beneficiary_scope: "individual",
      recipient_name: recipient,
      recipient_address: "Kp. Cijulang RT 01/02, Desa Cibadak",
      recommended_amount: "200000.00",
      reason: "Bantuan beras dan kebutuhan dapur.",
      recommender_name: "Ibu RT",
    },
  });
  expect(created.ok(), await created.text()).toBeTruthy();
  const grant = (await created.json()).data as { id: number; application_number: string };

  const comment = await request.post(`${apiUrl}/grant-applications/${grant.id}/comments`, {
    headers: auth(token),
    data: { body: `@[user:${verifierId}] ${note}` },
  });
  expect(comment.ok(), await comment.text()).toBeTruthy();

  return { grantId: grant.id, applicationNumber: grant.application_number, excerpt: note };
}

/** Dua mention untuk verifikator: satu belum dibaca, satu sudah ditandai dibaca lewat API. */
export async function seedReadAndUnreadNotifications(
  request: APIRequestContext,
  apiUrl: string,
): Promise<{ unread: SeededNotice; read: SeededNotice }> {
  const petugas = await tokenFor(request, apiUrl, "petugas_bantuan");
  const listed = await request.get(`${apiUrl}/grant-applications/mentionable-users?q=Verifikator`, {
    headers: auth(petugas),
  });
  expect(listed.ok(), await listed.text()).toBeTruthy();
  const verifier = ((await listed.json()).data as { id: number; name: string }[]).find(
    (user) => user.name === "Verifikator",
  );
  if (!verifier) {
    throw new Error("Pengguna Verifikator tidak ditemukan");
  }

  const unread = await createMentionedGrant(request, apiUrl, petugas, verifier.id, "Rina Belum", UNREAD_EXCERPT);
  const read = await createMentionedGrant(request, apiUrl, petugas, verifier.id, "Rina Sudah", READ_EXCERPT);

  const verifikator = await tokenFor(request, apiUrl, "verifikator");
  const notifications = await request.get(`${apiUrl}/notifications`, { headers: auth(verifikator) });
  expect(notifications.ok(), await notifications.text()).toBeTruthy();
  const match = ((await notifications.json()).data as { id: string; excerpt: string | null }[]).find((item) =>
    item.excerpt?.includes(READ_EXCERPT),
  );
  if (!match) {
    throw new Error("Notifikasi yang sudah dibaca tidak ditemukan");
  }
  const marked = await request.post(`${apiUrl}/notifications/${match.id}/read`, { headers: auth(verifikator) });
  expect(marked.ok(), await marked.text()).toBeTruthy();

  return { unread, read };
}
