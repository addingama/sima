import { type APIRequestContext, expect, test } from "@playwright/test";

import { type Role, roleAccounts } from "../fixtures/auth";

const apiUrl = "http://127.0.0.1:8100/api";
const today = new Date().toISOString().slice(0, 10);

async function tokenFor(request: APIRequestContext, role: Role): Promise<string> {
  const response = await request.post(`${apiUrl}/login`, {
    data: { login: roleAccounts[role], password: "password", device_name: "playwright-extended" },
  });
  expect(response.ok()).toBe(true);

  return (await response.json()).data.token as string;
}

function options(token: string, data?: unknown) {
  return { headers: { Authorization: `Bearer ${token}`, Accept: "application/json" }, data };
}

async function rows<T>(request: APIRequestContext, token: string, resource: string): Promise<T[]> {
  const response = await request.get(`${apiUrl}/${resource}?per_page=100`, options(token));
  expect(response.ok()).toBe(true);

  return (await response.json()).data as T[];
}

test("transfer rekening berjalan dari draft sampai post dan reversal", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const accounts = await rows<{ id: number; code: string }>(request, bendahara, "accounts");
  const source = accounts.find((row) => row.code === "DEMO-BCA");
  const destination = accounts.find((row) => row.code === "DEMO-KAS");
  expect(source).toBeTruthy();
  expect(destination).toBeTruthy();

  const created = await request.post(
    `${apiUrl}/account-transfers`,
    options(bendahara, {
      transfer_date: today,
      from_account_id: source?.id,
      to_account_id: destination?.id,
      amount: "10000.00",
      description: "Transfer rekening E2E",
    }),
  );
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as number;

  const posted = await request.post(`${apiUrl}/account-transfers/${id}/post`, options(bendahara));
  expect(posted.status()).toBe(200);
  expect((await posted.json()).data.status).toBe("posted");

  const reversed = await request.post(
    `${apiUrl}/account-transfers/${id}/reverse`,
    options(bendahara, { reason: "Regression reversal transfer rekening" }),
  );
  expect(reversed.status()).toBe(200);
  expect((await reversed.json()).data.status).toBe("reversed");
});

test("transfer Dana Amanah berjalan dari draft sampai post dan reversal", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const funds = await rows<{ id: number; code: string }>(request, bendahara, "funds");
  const source = funds.find((row) => row.code === "DEMO-UMUM");
  const destination = funds.find((row) => row.code === "SYS-BANKADMIN");
  expect(source).toBeTruthy();
  expect(destination).toBeTruthy();

  const created = await request.post(
    `${apiUrl}/fund-transfers`,
    options(bendahara, {
      transfer_date: today,
      from_fund_id: source?.id,
      to_fund_id: destination?.id,
      amount: "10000.00",
      description: "Transfer dana E2E",
    }),
  );
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as number;

  const posted = await request.post(`${apiUrl}/fund-transfers/${id}/post`, options(bendahara));
  expect(posted.status()).toBe(200);
  expect((await posted.json()).data.status).toBe("posted");

  const reversed = await request.post(
    `${apiUrl}/fund-transfers/${id}/reverse`,
    options(bendahara, { reason: "Regression reversal transfer dana" }),
  );
  expect(reversed.status()).toBe(200);
  expect((await reversed.json()).data.status).toBe("reversed");
});

test("biaya bank berjalan sampai posting dan reversal", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const accounts = await rows<{ id: number; code: string }>(request, bendahara, "accounts");
  const account = accounts.find((row) => row.code === "DEMO-BCA");
  expect(account).toBeTruthy();

  const created = await request.post(
    `${apiUrl}/bank-fees`,
    options(bendahara, {
      fee_date: today,
      account_id: account?.id,
      amount: "5000.00",
      fee_type: "admin",
      description: "Biaya bank E2E",
    }),
  );
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as number;

  const posted = await request.post(`${apiUrl}/bank-fees/${id}/post`, options(bendahara));
  expect(posted.status()).toBe(200);
  expect((await posted.json()).data.status).toBe("posted");

  const reversed = await request.post(
    `${apiUrl}/bank-fees/${id}/reverse`,
    options(bendahara, { reason: "Regression reversal biaya bank" }),
  );
  expect(reversed.status()).toBe(200);
  expect((await reversed.json()).data.status).toBe("reversed");
});

test("liabilitas operasional dapat dibuat dan dibatalkan", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const funds = await rows<{ id: number; code: string }>(request, bendahara, "funds");
  const operational = funds.find((row) => row.code === "SYS-OPERASIONAL");
  expect(operational).toBeTruthy();

  const created = await request.post(
    `${apiUrl}/liabilities`,
    options(bendahara, {
      liability_date: today,
      creditor: "Vendor E2E",
      description: "Liabilitas yang dibatalkan oleh automation",
      fund_id: operational?.id,
      amount: "25000.00",
    }),
  );
  expect(created.status()).toBe(201);
  expect((await created.json()).data.status).toBe("outstanding");
  const id = (await created.json()).data.id as number;

  const voided = await request.post(
    `${apiUrl}/liabilities/${id}/void`,
    options(bendahara, { reason: "Tagihan dibatalkan" }),
  );
  expect(voided.status()).toBe(200);
  expect((await voided.json()).data.status).toBe("void");
});

test("master vendor dapat dibuat, diubah, dan dinonaktifkan", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const suffix = Date.now();
  const created = await request.post(
    `${apiUrl}/vendors`,
    options(bendahara, {
      code: `E2E-VND-${suffix}`,
      name: "Vendor Automation",
      contact_name: "Kontak Awal",
      is_active: true,
    }),
  );
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as number;

  const updated = await request.put(
    `${apiUrl}/vendors/${id}`,
    options(bendahara, {
      code: `E2E-VND-${suffix}`,
      name: "Vendor Automation Diperbarui",
      contact_name: "Kontak Baru",
      is_active: true,
    }),
  );
  expect(updated.status()).toBe(200);
  expect((await updated.json()).data.name).toBe("Vendor Automation Diperbarui");

  const removed = await request.delete(`${apiUrl}/vendors/${id}`, options(bendahara));
  expect(removed.status()).toBe(200);
});

test("admin dapat menyiapkan master baru dan memposting saldo awal", async ({ request }) => {
  const admin = await tokenFor(request, "admin");
  const suffix = Date.now();
  const accountResponse = await request.post(
    `${apiUrl}/accounts`,
    options(admin, {
      code: `E2E-KAS-${suffix}`,
      name: "Kas Saldo Awal E2E",
      type: "cash",
      is_active: true,
    }),
  );
  expect(accountResponse.status()).toBe(201);
  const accountId = (await accountResponse.json()).data.id as number;

  const fundResponse = await request.post(
    `${apiUrl}/funds`,
    options(admin, {
      code: `E2E-DANA-${suffix}`,
      name: "Dana Saldo Awal E2E",
      type: "unrestricted",
      is_active: true,
    }),
  );
  expect(fundResponse.status()).toBe(201);
  const fundId = (await fundResponse.json()).data.id as number;

  const opening = await request.post(
    `${apiUrl}/opening-balances`,
    options(admin, {
      opening_date: today,
      reference: "Saldo awal automation E2E",
      lines: [{ account_id: accountId, fund_id: fundId, amount: "75000.00" }],
    }),
  );
  expect(opening.status()).toBe(201);
  expect((await opening.json()).data.total_amount).toBe("75000.00");
  expect((await opening.json()).data.batch_number).toMatch(/^OPN\//);
});

test("bendahara dapat membuat dan menyelesaikan rekonsiliasi seimbang", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const accounts = await rows<{ id: number; code: string; balance: string }>(request, bendahara, "accounts");
  const account = accounts.find((row) => row.code === "DEMO-BCA");
  expect(account).toBeTruthy();
  expect(account?.balance).toBeTruthy();

  const created = await request.post(
    `${apiUrl}/bank-reconciliations`,
    options(bendahara, {
      account_id: account?.id,
      period_start: today.slice(0, 8).concat("01"),
      period_end: today,
      statement_balance: account?.balance,
      notes: "Rekonsiliasi seimbang E2E",
    }),
  );
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as number;

  const completed = await request.post(`${apiUrl}/bank-reconciliations/${id}/complete`, options(bendahara));
  expect(completed.status()).toBe(200);
  expect((await completed.json()).data.status).toBe("completed");
});

test("admin dapat mengelola role, password, dan status akun pengguna", async ({ request }) => {
  const admin = await tokenFor(request, "admin");
  const suffix = Date.now();
  const email = `e2e-user-${suffix}@sima.test`;
  const created = await request.post(
    `${apiUrl}/users`,
    options(admin, {
      name: "Pengguna Automation",
      email,
      password: "password123",
      role: "petugas_bantuan",
      is_active: true,
    }),
  );
  expect(created.status()).toBe(201);
  expect((await created.json()).data.roles).toEqual(["petugas_bantuan"]);
  const id = (await created.json()).data.id as number;

  const roleChanged = await request.put(`${apiUrl}/users/${id}/roles`, options(admin, { role: "auditor" }));
  expect(roleChanged.status()).toBe(200);
  expect((await roleChanged.json()).data.roles).toEqual(["auditor"]);

  const passwordReset = await request.post(
    `${apiUrl}/users/${id}/reset-password`,
    options(admin, { password: "password456" }),
  );
  expect(passwordReset.status()).toBe(200);

  const loginWithNewPassword = await request.post(`${apiUrl}/login`, {
    data: { login: email, password: "password456", device_name: "playwright-password-reset" },
  });
  expect(loginWithNewPassword.status()).toBe(200);
  const issuedToken = (await loginWithNewPassword.json()).data.token as string;

  const deactivated = await request.delete(`${apiUrl}/users/${id}`, options(admin));
  expect(deactivated.status()).toBe(200);
  expect((await deactivated.json()).message).toBe("Pengguna dinonaktifkan.");

  const revokedToken = await request.get(`${apiUrl}/me`, {
    headers: { Authorization: `Bearer ${issuedToken}`, Accept: "application/json" },
  });
  expect(revokedToken.status()).toBe(401);
});

test("bantuan kolektif berjalan lintas role sampai serah terima", async ({ request }) => {
  const petugas = await tokenFor(request, "petugas_bantuan");
  const ketua = await tokenFor(request, "ketua");
  const bendahara = await tokenFor(request, "bendahara");
  const verifikator = await tokenFor(request, "verifikator");

  const created = await request.post(
    `${apiUrl}/grant-applications`,
    options(petugas, {
      beneficiary_type: "organization",
      beneficiary_scope: "collective",
      recipient_name: "Desa Air Minum E2E",
      recommended_amount: "50000.00",
      reason: "Distribusi air minum ke wilayah terpencil",
      recommender_name: "Petugas Lapangan E2E",
      target_beneficiary_count: 120,
      beneficiary_count_method: "estimated",
      beneficiary_location: "Desa Terpencil E2E",
      beneficiary_count_notes: "Estimasi berdasarkan jumlah keluarga",
    }),
  );
  expect(created.status()).toBe(201);
  const grant = (await created.json()).data as {
    id: number;
    assigned_verifier_id: number;
    assigned_handover_id: number;
  };
  expect(grant.assigned_verifier_id).toBeTruthy();
  expect(grant.assigned_handover_id).toBeTruthy();

  const sent = await request.post(`${apiUrl}/grant-applications/${grant.id}/send-to-verification`, options(petugas));
  expect(sent.status()).toBe(200);
  expect((await sent.json()).data.status).toBe("verification");

  const verified = await request.put(
    `${apiUrl}/grant-applications/${grant.id}`,
    options(petugas, {
      recipient_address: "Desa Terpencil, Kabupaten Amanah",
      organization_pic_name: "Kepala Desa",
      organization_pic_contact: "081234567890",
      organization_pic_relationship: "Penanggung jawab distribusi",
      verified_amount: "50000.00",
      verifier_notes: "Lokasi dan jumlah penerima telah diverifikasi",
    }),
  );
  expect(verified.status()).toBe(200);

  const submitted = await request.post(
    `${apiUrl}/grant-applications/${grant.id}/submit-for-approval`,
    options(petugas),
  );
  expect(submitted.status()).toBe(200);
  expect((await submitted.json()).data.status).toBe("pending_approval");

  const returned = await request.post(
    `${apiUrl}/grant-applications/${grant.id}/return`,
    options(ketua, { reason: "Lengkapi konfirmasi jumlah penerima" }),
  );
  expect(returned.status()).toBe(200);
  expect((await returned.json()).data.status).toBe("verification");

  const resubmitted = await request.post(
    `${apiUrl}/grant-applications/${grant.id}/submit-for-approval`,
    options(petugas),
  );
  expect(resubmitted.status()).toBe(200);

  const approved = await request.post(
    `${apiUrl}/grant-applications/${grant.id}/approve`,
    options(ketua, { approved_amount: "50000.00", notes: "Disetujui untuk distribusi" }),
  );
  expect(approved.status()).toBe(200);
  expect((await approved.json()).data.status).toBe("approved");

  const accounts = await rows<{ id: number; code: string }>(request, bendahara, "accounts");
  const funds = await rows<{ id: number; code: string }>(request, bendahara, "funds");
  const account = accounts.find((row) => row.code === "DEMO-BCA");
  const fund = funds.find((row) => row.code === "DEMO-UMUM");
  expect(account).toBeTruthy();
  expect(fund).toBeTruthy();

  const expense = await request.post(
    `${apiUrl}/grant-applications/${grant.id}/disbursements`,
    options(bendahara, {
      disbursement_date: today,
      account_id: account?.id,
      sources: [{ fund_id: fund?.id, amount: "50000.00" }],
    }),
  );
  expect(expense.status()).toBe(201);
  const expenseId = (await expense.json()).data.disbursement_id as number;

  expect((await request.post(`${apiUrl}/disbursements/${expenseId}/submit`, options(bendahara))).status()).toBe(200);
  expect((await request.post(`${apiUrl}/disbursements/${expenseId}/verify`, options(verifikator))).status()).toBe(200);
  expect((await request.post(`${apiUrl}/disbursements/${expenseId}/approve`, options(bendahara))).status()).toBe(200);

  const attachment = await request.post(`${apiUrl}/attachments`, {
    headers: { Authorization: `Bearer ${petugas}`, Accept: "application/json" },
    multipart: {
      attachable_type: "grant_application",
      attachable_id: String(grant.id),
      title: "handover",
      file: {
        name: "handover.png",
        mimeType: "image/png",
        buffer: Buffer.from(
          "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=",
          "base64",
        ),
      },
    },
  });
  expect(attachment.status()).toBe(201);

  const completed = await request.post(
    `${apiUrl}/grant-applications/${grant.id}/complete`,
    options(petugas, {
      handed_over_on: today,
      handover_recipient_name: "Kepala Desa",
      handover_notes: "Air minum telah diterima dan didokumentasikan",
      actual_beneficiary_count: 118,
    }),
  );
  expect(completed.status()).toBe(200);
  expect((await completed.json()).data.status).toBe("completed");
  expect((await completed.json()).data.actual_beneficiary_count).toBe(118);
});
