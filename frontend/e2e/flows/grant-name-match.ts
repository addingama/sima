import { type APIRequestContext, expect, type Page } from "@playwright/test";

import { loginAs, type Role, roleAccounts } from "../fixtures/auth";

export const SAME_NAME_RECIPIENT = "Ahmad Fauzi";
export const PRIOR_REASON = "Santunan sakit untuk biaya berobat.";
export const NEXT_REASON = "Modal usaha warung kelontong.";
const NIK = "3201015501800001";
const AMOUNT = "150000.00";

const HANDOVER_PNG = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=",
  "base64",
);

async function tokenFor(request: APIRequestContext, apiUrl: string, role: Role): Promise<string> {
  const response = await request.post(`${apiUrl}/login`, {
    data: { login: roleAccounts[role], password: "password", device_name: "playwright-name-match" },
  });
  expect(response.ok(), await response.text()).toBeTruthy();

  return (await response.json()).data.token as string;
}

function auth(token: string) {
  return { Authorization: `Bearer ${token}`, Accept: "application/json" };
}

async function expectOk(response: Awaited<ReturnType<APIRequestContext["post"]>>, label: string) {
  expect(response.ok(), `${label}: ${response.status()} ${await response.text()}`).toBeTruthy();
}

/** Kartu selesai untuk nama yang sama, lewat API, supaya video fokus ke flag. */
export async function seedCompletedSameNameGrant(
  request: APIRequestContext,
  apiUrl: string,
): Promise<{ applicationNumber: string }> {
  const petugas = await tokenFor(request, apiUrl, "petugas_bantuan");
  const ketua = await tokenFor(request, apiUrl, "ketua");
  const bendahara = await tokenFor(request, apiUrl, "bendahara");
  const verifikator = await tokenFor(request, apiUrl, "verifikator");

  const created = await request.post(`${apiUrl}/grant-applications`, {
    headers: auth(petugas),
    data: {
      beneficiary_type: "individual",
      beneficiary_scope: "individual",
      recipient_name: SAME_NAME_RECIPIENT,
      recipient_identity_number: NIK,
      recipient_address: "Kp. Sukamaju RT 02/03, Desa Cibadak",
      recommended_amount: AMOUNT,
      reason: PRIOR_REASON,
      recommender_name: "Ketua RT 02",
    },
  });
  await expectOk(created, "buat pengajuan selesai");
  const grant = (await created.json()).data as { id: number; application_number: string };

  await expectOk(
    await request.post(`${apiUrl}/grant-applications/${grant.id}/send-to-verification`, { headers: auth(petugas) }),
    "kirim verifikasi",
  );
  await expectOk(
    await request.put(`${apiUrl}/grant-applications/${grant.id}`, {
      headers: auth(petugas),
      data: {
        recipient_address: "Kp. Sukamaju RT 02/03, Desa Cibadak",
        recipient_identity_number: NIK,
        verified_amount: AMOUNT,
        verifier_notes: "Penerima sesuai. Santunan sakit layak.",
      },
    }),
    "lengkapi verifikasi",
  );
  await expectOk(
    await request.post(`${apiUrl}/grant-applications/${grant.id}/submit-for-approval`, { headers: auth(petugas) }),
    "ajukan approval",
  );
  await expectOk(
    await request.post(`${apiUrl}/grant-applications/${grant.id}/approve`, {
      headers: auth(ketua),
      data: { approved_amount: AMOUNT, notes: "Disetujui. Santunan sakit." },
    }),
    "setujui",
  );

  const headers = auth(bendahara);
  const [accountsResponse, fundsResponse] = await Promise.all([
    request.get(`${apiUrl}/accounts?per_page=100`, { headers }),
    request.get(`${apiUrl}/funds?per_page=100`, { headers }),
  ]);
  await expectOk(accountsResponse, "daftar rekening");
  await expectOk(fundsResponse, "daftar dana");
  const accounts = (await accountsResponse.json()).data as Array<{ id: number; code: string }>;
  const funds = (await fundsResponse.json()).data as Array<{ id: number; code: string }>;
  const account = accounts.find((row) => row.code === "DEMO-KAS");
  const fund = funds.find((row) => row.code === "DEMO-UMUM");
  expect(account).toBeTruthy();
  expect(fund).toBeTruthy();

  const expense = await request.post(`${apiUrl}/grant-applications/${grant.id}/disbursements`, {
    headers,
    data: {
      disbursement_date: "2026-10-18",
      account_id: account?.id,
      sources: [{ fund_id: fund?.id, amount: AMOUNT }],
    },
  });
  await expectOk(expense, "buat pengeluaran");
  const expenseId = (await expense.json()).data.disbursement_id as number;

  await expectOk(
    await request.post(`${apiUrl}/disbursements/${expenseId}/submit`, { headers: auth(bendahara) }),
    "submit pengeluaran",
  );
  await expectOk(
    await request.post(`${apiUrl}/disbursements/${expenseId}/verify`, { headers: auth(verifikator) }),
    "verifikasi pengeluaran",
  );
  await expectOk(
    await request.post(`${apiUrl}/disbursements/${expenseId}/approve`, { headers: auth(bendahara) }),
    "setujui pengeluaran",
  );

  const attachment = await request.post(`${apiUrl}/attachments`, {
    headers: auth(petugas),
    multipart: {
      attachable_type: "grant_application",
      attachable_id: String(grant.id),
      title: "handover",
      file: { name: "serah-terima.png", mimeType: "image/png", buffer: HANDOVER_PNG },
    },
  });
  await expectOk(attachment, "foto serah terima");

  await expectOk(
    await request.post(`${apiUrl}/grant-applications/${grant.id}/complete`, {
      headers: auth(petugas),
      data: { handed_over_on: "2026-10-18", handover_recipient_name: SAME_NAME_RECIPIENT },
    }),
    "selesai",
  );

  return { applicationNumber: grant.application_number };
}

export async function showSameNameFlag(page: Page, caption: (text: string, holdMs?: number) => Promise<void>) {
  await loginAs(page, "petugas_bantuan");
  await caption("1. Ahmad Fauzi sudah pernah selesai dibantu: santunan sakit", 1400);
  await page.goto("/dashboard/bantuan");
  await page.getByRole("tab", { name: "Arsip" }).click();
  await expect(page.getByRole("link", { name: SAME_NAME_RECIPIENT })).toBeVisible();
  await caption("2. Kartu selesai ada di Arsip. Bantuan berulang tetap boleh.", 1600);

  await caption("3. Petugas menginput nama yang sama, tujuan berbeda: modal usaha", 1200);
  await page.getByRole("link", { name: "Rekomendasi baru" }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/new$/);
  await page.getByLabel(/Nama penerima/).pressSequentially(SAME_NAME_RECIPIENT, { delay: 40 });
  await page.getByLabel(/Telepon penerima/).fill("081234567890");
  await page.getByLabel(/Alamat \/ cara menemui/).fill("Kp. Sukamaju RT 02/03, Desa Cibadak");
  await page.getByLabel(/NIK \/ nomor identitas/).fill(NIK);
  await page.locator("#recommended_amount").fill("150000");
  await page.locator("#reason").fill(NEXT_REASON);
  await page.locator("#recommender_name").fill("Ketua RT 02");
  await caption("4. Simpan rekomendasi kedua", 900);
  await page.getByRole("button", { name: "Simpan" }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/\d+$/);

  const banner = page.getByRole("alert").filter({ hasText: "Nama penerima sama" });
  await banner.scrollIntoViewIfNeeded();
  await expect(banner).toContainText("Bantuan berulang diperbolehkan");
  await expect(banner).toContainText("Sudah dibantu 1 kali.");
  await expect(banner).toContainText(PRIOR_REASON);
  await expect(banner).toContainText("NIK sama");
  await expect(banner).toContainText("Selesai");
  await caption("5. Flag untuk verifikator: orang yang sama, tujuan baru, sudah dibantu 1 kali", 2400);

  await page.goto("/dashboard/bantuan");
  await expect(page.getByRole("tab", { name: "Antrian" })).toHaveAttribute("data-state", "active");
  const card = page.getByRole("link", { name: SAME_NAME_RECIPIENT });
  await expect(card).toBeVisible();
  await expect(card.getByText("Nama sama")).toBeVisible();
  await expect(card.getByText("Sudah dibantu 1 kali")).toBeVisible();
  await caption("6. Kartu antrian menandai nama sama, tanpa menahan alur", 2200);
}
