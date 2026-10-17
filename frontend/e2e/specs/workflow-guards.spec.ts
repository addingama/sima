import { type APIRequestContext, expect, test } from "@playwright/test";

import { type Role, roleAccounts } from "../fixtures/auth";

const apiUrl = "http://127.0.0.1:8100/api";
const today = new Date().toISOString().slice(0, 10);

async function tokenFor(request: APIRequestContext, role: Role): Promise<string> {
  const response = await request.post(`${apiUrl}/login`, {
    data: { login: roleAccounts[role], password: "password", device_name: "playwright-guards" },
  });
  expect(response.status()).toBe(200);

  return (await response.json()).data.token as string;
}

function options(token: string, data?: unknown) {
  return { headers: { Authorization: `Bearer ${token}`, Accept: "application/json" }, data };
}

async function ids(request: APIRequestContext, token: string) {
  const [accountsResponse, fundsResponse] = await Promise.all([
    request.get(`${apiUrl}/accounts?per_page=100`, options(token)),
    request.get(`${apiUrl}/funds?per_page=100`, options(token)),
  ]);
  expect(accountsResponse.status()).toBe(200);
  expect(fundsResponse.status()).toBe(200);
  const accounts = (await accountsResponse.json()).data as Array<{ id: number; code: string }>;
  const funds = (await fundsResponse.json()).data as Array<{ id: number; code: string }>;

  return {
    accountId: accounts.find((row) => row.code === "DEMO-BCA")?.id,
    fundId: funds.find((row) => row.code === "DEMO-UMUM")?.id,
    systemFundId: funds.find((row) => row.code === "SYS-OPERASIONAL")?.id,
  };
}

test("penerimaan tidak dapat diedit atau disubmit ulang setelah masuk approval", async ({ request }) => {
  const asisten = await tokenFor(request, "asisten_bendahara");
  const { accountId, fundId } = await ids(request, asisten);
  const payload = {
    receipt_date: today,
    account_id: accountId,
    channel: "transfer",
    amount: "17500.00",
    description: "Guard penerimaan E2E",
    allocations: [{ fund_id: fundId, amount: "17500.00" }],
  };
  const created = await request.post(`${apiUrl}/receipts`, options(asisten, payload));
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as number;
  expect((await request.post(`${apiUrl}/receipts/${id}/submit`, options(asisten))).status()).toBe(200);

  const edited = await request.put(
    `${apiUrl}/receipts/${id}`,
    options(asisten, { ...payload, description: "Perubahan yang harus ditolak" }),
  );
  expect(edited.status()).toBe(403);
  expect((await request.post(`${apiUrl}/receipts/${id}/submit`, options(asisten))).status()).toBe(403);
});

test("pengeluaran wajib melewati submit dan verifikasi sebelum approval", async ({ request }) => {
  const asisten = await tokenFor(request, "asisten_bendahara");
  const bendahara = await tokenFor(request, "bendahara");
  const verifikator = await tokenFor(request, "verifikator");
  const { accountId, fundId } = await ids(request, asisten);
  const created = await request.post(
    `${apiUrl}/disbursements`,
    options(asisten, {
      disbursement_date: today,
      account_id: accountId,
      amount: "13500.00",
      payee: "Penerima Guard E2E",
      sources: [{ fund_id: fundId, amount: "13500.00" }],
    }),
  );
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as number;

  expect((await request.post(`${apiUrl}/disbursements/${id}/approve`, options(bendahara))).status()).toBe(403);
  expect((await request.post(`${apiUrl}/disbursements/${id}/submit`, options(asisten))).status()).toBe(200);
  expect((await request.post(`${apiUrl}/disbursements/${id}/approve`, options(bendahara))).status()).toBe(403);
  expect((await request.post(`${apiUrl}/disbursements/${id}/verify`, options(verifikator))).status()).toBe(200);
  expect((await request.post(`${apiUrl}/disbursements/${id}/approve`, options(bendahara))).status()).toBe(200);
  expect((await request.post(`${apiUrl}/disbursements/${id}/approve`, options(bendahara))).status()).toBe(403);
});

test("bantuan approved belum dapat diselesaikan tanpa pengeluaran dan bukti serah terima", async ({ request }) => {
  const petugas = await tokenFor(request, "petugas_bantuan");
  const ketua = await tokenFor(request, "ketua");
  const created = await request.post(
    `${apiUrl}/grant-applications`,
    options(petugas, {
      beneficiary_type: "individual",
      recipient_name: "Penerima Guard Bantuan",
      recommended_amount: "22500.00",
      reason: "Menguji guard penyelesaian bantuan",
      recommender_name: "Petugas Guard",
    }),
  );
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as number;
  expect(
    (await request.post(`${apiUrl}/grant-applications/${id}/send-to-verification`, options(petugas))).status(),
  ).toBe(200);
  expect(
    (
      await request.put(
        `${apiUrl}/grant-applications/${id}`,
        options(petugas, {
          recipient_identity_number: "3201010101010088",
          recipient_address: "Alamat Guard Bantuan",
          verified_amount: "22500.00",
          verifier_notes: "Layak untuk pengujian guard",
        }),
      )
    ).status(),
  ).toBe(200);
  expect(
    (await request.post(`${apiUrl}/grant-applications/${id}/submit-for-approval`, options(petugas))).status(),
  ).toBe(200);
  expect(
    (
      await request.post(`${apiUrl}/grant-applications/${id}/approve`, options(ketua, { approved_amount: "22500.00" }))
    ).status(),
  ).toBe(200);

  const completed = await request.post(
    `${apiUrl}/grant-applications/${id}/complete`,
    options(petugas, { handed_over_on: today, handover_recipient_name: "Penerima Guard Bantuan" }),
  );
  expect(completed.status()).toBe(422);
  expect((await completed.json()).errors.code).toBe("domain_rule_violation");
});

test("upload menolak format file yang tidak diizinkan", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const { accountId, fundId } = await ids(request, bendahara);
  const receipt = await request.post(
    `${apiUrl}/receipts`,
    options(bendahara, {
      receipt_date: today,
      account_id: accountId,
      channel: "cash",
      amount: "8000.00",
      allocations: [{ fund_id: fundId, amount: "8000.00" }],
    }),
  );
  expect(receipt.status()).toBe(201);
  const receiptId = (await receipt.json()).data.id as number;

  const upload = await request.post(`${apiUrl}/attachments`, {
    headers: { Authorization: `Bearer ${bendahara}`, Accept: "application/json" },
    multipart: {
      attachable_type: "receipt",
      attachable_id: String(receiptId),
      title: "File terlarang",
      file: { name: "payload.exe", mimeType: "application/x-msdownload", buffer: Buffer.from("not-an-executable") },
    },
  });
  expect(upload.status()).toBe(422);
  expect((await upload.json()).errors.fields.file[0]).toContain("Format lampiran harus");
});

test("saldo awal tidak dapat diposting dua kali pada rekening yang sama", async ({ request }) => {
  const admin = await tokenFor(request, "admin");
  const suffix = Date.now();
  const account = await request.post(
    `${apiUrl}/accounts`,
    options(admin, { code: `GUARD-ACC-${suffix}`, name: "Rekening Guard", type: "cash", is_active: true }),
  );
  const fund = await request.post(
    `${apiUrl}/funds`,
    options(admin, { code: `GUARD-FND-${suffix}`, name: "Dana Guard", type: "unrestricted", is_active: true }),
  );
  expect(account.status()).toBe(201);
  expect(fund.status()).toBe(201);
  const line = {
    account_id: (await account.json()).data.id,
    fund_id: (await fund.json()).data.id,
    amount: "5000.00",
  };

  expect(
    (
      await request.post(
        `${apiUrl}/opening-balances`,
        options(admin, { opening_date: today, reference: "Opening pertama", lines: [line] }),
      )
    ).status(),
  ).toBe(201);
  const duplicate = await request.post(
    `${apiUrl}/opening-balances`,
    options(admin, { opening_date: today, reference: "Opening duplikat", lines: [line] }),
  );
  expect(duplicate.status()).toBe(422);
  expect((await duplicate.json()).errors.code).toBe("domain_rule_violation");
});

test("Dana Amanah sistem tidak dapat diubah atau dihapus", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const { systemFundId } = await ids(request, bendahara);
  expect(systemFundId).toBeTruthy();

  expect(
    (
      await request.put(
        `${apiUrl}/funds/${systemFundId}`,
        options(bendahara, { name: "Nama sistem yang tidak boleh berubah" }),
      )
    ).status(),
  ).toBe(403);
  expect((await request.delete(`${apiUrl}/funds/${systemFundId}`, options(bendahara))).status()).toBe(403);
});
