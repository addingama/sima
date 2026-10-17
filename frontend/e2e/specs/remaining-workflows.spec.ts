import { type APIRequestContext, expect, test } from "@playwright/test";

import { type Role, roleAccounts } from "../fixtures/auth";

const apiUrl = "http://127.0.0.1:8100/api";
const today = new Date().toISOString().slice(0, 10);

async function tokenFor(request: APIRequestContext, role: Role): Promise<string> {
  const response = await request.post(`${apiUrl}/login`, {
    data: { login: roleAccounts[role], password: "password", device_name: "playwright-remaining" },
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

async function financeIds(request: APIRequestContext, token: string) {
  const accounts = await rows<{ id: number; code: string }>(request, token, "accounts");
  const funds = await rows<{ id: number; code: string }>(request, token, "funds");

  return {
    accountId: accounts.find((row) => row.code === "DEMO-BCA")?.id,
    fundId: funds.find((row) => row.code === "DEMO-UMUM")?.id,
  };
}

test("penerimaan dan pengeluaran dapat ditolak oleh role berwenang", async ({ request }) => {
  const asisten = await tokenFor(request, "asisten_bendahara");
  const verifikator = await tokenFor(request, "verifikator");
  const ketua = await tokenFor(request, "ketua");
  const { accountId, fundId } = await financeIds(request, asisten);
  expect(accountId).toBeTruthy();
  expect(fundId).toBeTruthy();

  const receipt = await request.post(
    `${apiUrl}/receipts`,
    options(asisten, {
      receipt_date: today,
      account_id: accountId,
      channel: "transfer",
      amount: "15000.00",
      description: "Penerimaan reject E2E",
      allocations: [{ fund_id: fundId, amount: "15000.00" }],
    }),
  );
  expect(receipt.status()).toBe(201);
  const receiptId = (await receipt.json()).data.id as number;
  expect((await request.post(`${apiUrl}/receipts/${receiptId}/submit`, options(asisten))).status()).toBe(200);

  const rejectedReceipt = await request.post(
    `${apiUrl}/receipts/${receiptId}/reject`,
    options(ketua, { reason: "Bukti penerimaan tidak sesuai" }),
  );
  expect(rejectedReceipt.status()).toBe(200);
  expect((await rejectedReceipt.json()).data.status).toBe("rejected");

  const expense = await request.post(
    `${apiUrl}/disbursements`,
    options(asisten, {
      disbursement_date: today,
      account_id: accountId,
      amount: "12000.00",
      payee: "Penerima Reject E2E",
      description: "Pengeluaran reject E2E",
      sources: [{ fund_id: fundId, amount: "12000.00" }],
    }),
  );
  expect(expense.status()).toBe(201);
  const expenseId = (await expense.json()).data.id as number;
  expect((await request.post(`${apiUrl}/disbursements/${expenseId}/submit`, options(asisten))).status()).toBe(200);

  const rejectedExpense = await request.post(
    `${apiUrl}/disbursements/${expenseId}/reject`,
    options(verifikator, { reason: "Dokumen pengeluaran belum lengkap" }),
  );
  expect(rejectedExpense.status()).toBe(200);
  expect((await rejectedExpense.json()).data.status).toBe("rejected");
});

test("pengajuan bantuan dapat ditugaskan dan ditolak setelah verifikasi", async ({ request }) => {
  const asisten = await tokenFor(request, "asisten_bendahara");
  const ketua = await tokenFor(request, "ketua");
  const users = await rows<{ id: number; email: string }>(request, await tokenFor(request, "admin"), "users");
  const verifier = users.find((row) => row.email === roleAccounts.verifikator);
  expect(verifier).toBeTruthy();

  const created = await request.post(
    `${apiUrl}/grant-applications`,
    options(asisten, {
      beneficiary_type: "individual",
      recipient_name: "Calon Penerima Reject E2E",
      recommended_amount: "30000.00",
      reason: "Pengajuan untuk pengujian reject",
      recommender_name: "Pengusul E2E",
    }),
  );
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as number;

  const assigned = await request.post(
    `${apiUrl}/grant-applications/${id}/assign`,
    options(ketua, { assigned_verifier_id: verifier?.id }),
  );
  expect(assigned.status()).toBe(200);
  expect((await assigned.json()).data.assigned_verifier_id).toBe(verifier?.id);

  expect(
    (await request.post(`${apiUrl}/grant-applications/${id}/send-to-verification`, options(asisten))).status(),
  ).toBe(200);
  const verifierToken = await tokenFor(request, "verifikator");
  const updated = await request.put(
    `${apiUrl}/grant-applications/${id}`,
    options(verifierToken, {
      recipient_identity_number: "3201010101010099",
      recipient_address: "Alamat penerima E2E",
      verified_amount: "30000.00",
      verifier_notes: "Sudah diverifikasi untuk skenario reject",
    }),
  );
  expect(updated.status()).toBe(200);
  expect(
    (await request.post(`${apiUrl}/grant-applications/${id}/submit-for-approval`, options(verifierToken))).status(),
  ).toBe(200);

  const rejected = await request.post(
    `${apiUrl}/grant-applications/${id}/reject`,
    options(ketua, { reason: "Tidak memenuhi prioritas bantuan" }),
  );
  expect(rejected.status()).toBe(200);
  expect((await rejected.json()).data.status).toBe("rejected");
});

test("lampiran dapat diunggah, didaftar, diunduh, dan dihapus", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const { accountId, fundId } = await financeIds(request, bendahara);
  const receipt = await request.post(
    `${apiUrl}/receipts`,
    options(bendahara, {
      receipt_date: today,
      account_id: accountId,
      channel: "cash",
      amount: "10000.00",
      allocations: [{ fund_id: fundId, amount: "10000.00" }],
    }),
  );
  expect(receipt.status()).toBe(201);
  const receiptId = (await receipt.json()).data.id as number;

  const uploaded = await request.post(`${apiUrl}/attachments`, {
    headers: { Authorization: `Bearer ${bendahara}`, Accept: "application/json" },
    multipart: {
      attachable_type: "receipt",
      attachable_id: String(receiptId),
      title: "Bukti E2E",
      file: {
        name: "bukti.png",
        mimeType: "image/png",
        buffer: Buffer.from(
          "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=",
          "base64",
        ),
      },
    },
  });
  expect(uploaded.status()).toBe(201);
  const attachmentId = (await uploaded.json()).data.id as number;

  const listed = await request.get(
    `${apiUrl}/attachments?attachable_type=receipt&attachable_id=${receiptId}`,
    options(bendahara),
  );
  expect(listed.status()).toBe(200);
  expect((await listed.json()).data).toEqual(
    expect.arrayContaining([expect.objectContaining({ id: attachmentId, title: "Bukti E2E" })]),
  );

  const downloaded = await request.get(`${apiUrl}/attachments/${attachmentId}/download`, options(bendahara));
  expect(downloaded.status()).toBe(200);
  expect((await downloaded.body()).length).toBeGreaterThan(0);

  expect((await request.delete(`${apiUrl}/attachments/${attachmentId}`, options(bendahara))).status()).toBe(200);
  expect((await request.get(`${apiUrl}/attachments/${attachmentId}/download`, options(bendahara))).status()).toBe(404);
});

test("master donatur dan program mendukung lifecycle CRUD", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const suffix = Date.now();
  const donor = await request.post(
    `${apiUrl}/donors`,
    options(bendahara, {
      name: "Donatur E2E",
      type: "individu",
      email: `donor-${suffix}@example.test`,
      is_active: true,
    }),
  );
  expect(donor.status()).toBe(201);
  expect((await donor.json()).data.code).toMatch(/^DON\//);
  const donorId = (await donor.json()).data.id as number;

  const donorUpdated = await request.put(
    `${apiUrl}/donors/${donorId}`,
    options(bendahara, { name: "Donatur E2E Diperbarui", phone: "081200000099" }),
  );
  expect(donorUpdated.status()).toBe(200);
  expect((await donorUpdated.json()).data.name).toBe("Donatur E2E Diperbarui");
  expect((await request.delete(`${apiUrl}/donors/${donorId}`, options(bendahara))).status()).toBe(200);

  const program = await request.post(
    `${apiUrl}/programs`,
    options(bendahara, {
      name: "Program Darurat E2E",
      event_type: "emergency",
      status: "active",
      is_active: true,
    }),
  );
  expect(program.status()).toBe(201);
  expect((await program.json()).data.code).toMatch(/^EVT\//);
  const programId = (await program.json()).data.id as number;

  const programUpdated = await request.put(
    `${apiUrl}/programs/${programId}`,
    options(bendahara, { name: "Program Darurat E2E Selesai", event_type: "emergency", status: "closed" }),
  );
  expect(programUpdated.status()).toBe(200);
  expect((await programUpdated.json()).data.status).toBe("closed");
  expect((await request.delete(`${apiUrl}/programs/${programId}`, options(bendahara))).status()).toBe(200);
});

test("master rekening dan Dana Amanah mendukung lifecycle CRUD", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const suffix = Date.now();
  const account = await request.post(
    `${apiUrl}/accounts`,
    options(bendahara, {
      code: `E2E-ACC-${suffix}`,
      name: "Rekening E2E",
      type: "bank",
      bank_name: "Bank Automation",
      account_number: String(suffix),
      is_active: true,
    }),
  );
  expect(account.status()).toBe(201);
  const accountId = (await account.json()).data.id as number;

  const accountUpdated = await request.put(
    `${apiUrl}/accounts/${accountId}`,
    options(bendahara, { name: "Rekening E2E Diperbarui", account_holder: "Yayasan E2E" }),
  );
  expect(accountUpdated.status()).toBe(200);
  expect((await accountUpdated.json()).data.name).toBe("Rekening E2E Diperbarui");
  expect((await request.delete(`${apiUrl}/accounts/${accountId}`, options(bendahara))).status()).toBe(200);

  const fund = await request.post(
    `${apiUrl}/funds`,
    options(bendahara, {
      code: `E2E-FND-${suffix}`,
      name: "Dana E2E",
      type: "restricted",
      description: "Dana sementara untuk pengujian E2E",
      is_active: true,
    }),
  );
  expect(fund.status()).toBe(201);
  const fundId = (await fund.json()).data.id as number;

  const fundUpdated = await request.put(
    `${apiUrl}/funds/${fundId}`,
    options(bendahara, { name: "Dana E2E Diperbarui", description: "Deskripsi diperbarui" }),
  );
  expect(fundUpdated.status()).toBe(200);
  expect((await fundUpdated.json()).data.name).toBe("Dana E2E Diperbarui");
  expect((await request.delete(`${apiUrl}/funds/${fundId}`, options(bendahara))).status()).toBe(200);
});

test("liabilitas dapat diselesaikan dengan pengeluaran yang sudah approved", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const verifikator = await tokenFor(request, "verifikator");
  const { accountId, fundId } = await financeIds(request, bendahara);
  const funds = await rows<{ id: number; code: string }>(request, bendahara, "funds");
  const operationalId = funds.find((row) => row.code === "SYS-OPERASIONAL")?.id;
  expect(operationalId).toBeTruthy();

  const liability = await request.post(
    `${apiUrl}/liabilities`,
    options(bendahara, {
      liability_date: today,
      creditor: "Vendor Settlement E2E",
      description: "Liabilitas untuk settlement E2E",
      fund_id: operationalId,
      amount: "20000.00",
    }),
  );
  expect(liability.status()).toBe(201);
  const liabilityId = (await liability.json()).data.id as number;

  const expense = await request.post(
    `${apiUrl}/disbursements`,
    options(bendahara, {
      disbursement_date: today,
      account_id: accountId,
      amount: "20000.00",
      payee: "Vendor Settlement E2E",
      description: "Pembayaran liabilitas E2E",
      sources: [{ fund_id: fundId, amount: "20000.00" }],
    }),
  );
  expect(expense.status()).toBe(201);
  const expenseId = (await expense.json()).data.id as number;
  expect((await request.post(`${apiUrl}/disbursements/${expenseId}/submit`, options(bendahara))).status()).toBe(200);
  expect((await request.post(`${apiUrl}/disbursements/${expenseId}/verify`, options(verifikator))).status()).toBe(200);
  expect((await request.post(`${apiUrl}/disbursements/${expenseId}/approve`, options(bendahara))).status()).toBe(200);

  const settled = await request.post(
    `${apiUrl}/liabilities/${liabilityId}/settle`,
    options(bendahara, { disbursement_id: expenseId }),
  );
  expect(settled.status()).toBe(200);
  expect((await settled.json()).data.status).toBe("settled");
  expect((await settled.json()).data.settled_disbursement_id).toBe(expenseId);
});

test("pengaturan permission role hanya dapat diakses admin dan role admin terkunci", async ({ request }) => {
  const admin = await tokenFor(request, "admin");
  const bendahara = await tokenFor(request, "bendahara");
  const roles = await request.get(`${apiUrl}/roles`, options(admin));
  expect(roles.status()).toBe(200);
  const payload = await roles.json();
  expect(payload.data.roles).toEqual(
    expect.arrayContaining([expect.objectContaining({ name: "admin", is_locked: true })]),
  );
  const adminRole = payload.data.roles.find((role: { name: string; id: number }) => role.name === "admin") as {
    id: number;
  };

  expect((await request.get(`${apiUrl}/roles`, options(bendahara))).status()).toBe(403);
  const locked = await request.put(
    `${apiUrl}/roles/${adminRole.id}/permissions`,
    options(admin, { permissions: ["user.manage"] }),
  );
  expect(locked.status()).toBe(403);
});

test("dashboard, audit, seluruh laporan, dan portal donatur menghasilkan data", async ({ request }) => {
  const auditor = await tokenFor(request, "auditor");
  const programs = await rows<{ id: number; code: string }>(request, auditor, "programs");
  const funds = await rows<{ id: number; code: string }>(request, auditor, "funds");
  const programId = programs.find((row) => row.code === "DEMO-PRG-001")?.id;
  const fundId = funds.find((row) => row.code === "DEMO-UMUM")?.id;
  expect(programId).toBeTruthy();
  expect(fundId).toBeTruthy();
  const reportPaths = [
    "/dashboard",
    "/reports/account-balances",
    `/reports/by-program?program_id=${programId}`,
    "/reports/fund-balances",
    `/reports/fund-statement?fund_id=${fundId}`,
    "/reports/grant-applications",
    "/reports/ledger",
    "/reports/opening-balances",
    "/reports/reconciliation-summary",
  ];

  for (const path of reportPaths) {
    const response = await request.get(`${apiUrl}${path}`, options(auditor));
    expect(response.status(), path).toBe(200);
    expect((await response.json()).success, path).toBe(true);
  }

  const audits = await request.get(`${apiUrl}/audits`, options(auditor));
  expect(audits.status()).toBe(200);
  const auditRows = (await audits.json()).data as Array<{ id: number }>;
  expect(auditRows.length).toBeGreaterThan(0);
  expect((await request.get(`${apiUrl}/audits/${auditRows[0].id}`, options(auditor))).status()).toBe(200);

  const donor = await tokenFor(request, "donatur");
  for (const path of ["/portal/profile", "/portal/summary", "/portal/donations"]) {
    const response = await request.get(`${apiUrl}${path}`, options(donor));
    expect(response.status(), path).toBe(200);
    expect((await response.json()).success, path).toBe(true);
  }
});
