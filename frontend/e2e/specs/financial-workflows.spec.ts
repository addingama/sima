import { type APIRequestContext, expect, test } from "@playwright/test";

import { type Role, roleAccounts } from "../fixtures/auth";

const apiUrl = "http://127.0.0.1:8100/api";

async function tokenFor(request: APIRequestContext, role: Role): Promise<string> {
  const response = await request.post(`${apiUrl}/login`, {
    data: { login: roleAccounts[role], password: "password", device_name: "playwright-workflow" },
  });
  expect(response.ok()).toBe(true);

  return (await response.json()).data.token as string;
}

function options(token: string, data?: unknown) {
  return { headers: { Authorization: `Bearer ${token}`, Accept: "application/json" }, data };
}

async function masterIds(request: APIRequestContext, token: string) {
  const [accountsResponse, fundsResponse] = await Promise.all([
    request.get(`${apiUrl}/accounts?per_page=100`, options(token)),
    request.get(`${apiUrl}/funds?per_page=100`, options(token)),
  ]);
  expect(accountsResponse.ok()).toBe(true);
  expect(fundsResponse.ok()).toBe(true);

  const accounts = (await accountsResponse.json()).data as Array<{ id: number; code: string }>;
  const funds = (await fundsResponse.json()).data as Array<{ id: number; code: string }>;

  return {
    accountId: accounts.find((row) => row.code === "DEMO-BCA")?.id,
    fundId: funds.find((row) => row.code === "DEMO-UMUM")?.id,
  };
}

test("penerimaan berjalan lintas role sampai approve dan reversal", async ({ request }) => {
  const asisten = await tokenFor(request, "asisten_bendahara");
  const bendahara = await tokenFor(request, "bendahara");
  const { accountId, fundId } = await masterIds(request, asisten);
  expect(accountId).toBeTruthy();
  expect(fundId).toBeTruthy();

  const created = await request.post(
    `${apiUrl}/receipts`,
    options(asisten, {
      receipt_date: new Date().toISOString().slice(0, 10),
      account_id: accountId,
      channel: "transfer",
      reference_number: "E2E-RCP-CROSS-ROLE",
      amount: "123456.00",
      description: "Penerimaan lintas role Playwright",
      allocations: [{ fund_id: fundId, amount: "123456.00" }],
    }),
  );
  expect(created.status()).toBe(201);
  const receiptId = (await created.json()).data.id as number;

  expect((await request.post(`${apiUrl}/receipts/${receiptId}/submit`, options(asisten))).status()).toBe(200);
  expect((await request.post(`${apiUrl}/receipts/${receiptId}/approve`, options(asisten))).status()).toBe(403);

  const approved = await request.post(`${apiUrl}/receipts/${receiptId}/approve`, options(bendahara));
  expect(approved.status()).toBe(200);
  expect((await approved.json()).data.status).toBe("approved");

  const reversed = await request.post(
    `${apiUrl}/receipts/${receiptId}/reverse`,
    options(bendahara, { reason: "Reversal otomatis skenario E2E" }),
  );
  expect(reversed.status()).toBe(200);
  expect((await reversed.json()).data.status).toBe("reversed");
});

test("pengeluaran berjalan lintas role sampai verify approve dan reversal", async ({ request }) => {
  const asisten = await tokenFor(request, "asisten_bendahara");
  const verifikator = await tokenFor(request, "verifikator");
  const bendahara = await tokenFor(request, "bendahara");
  const { accountId, fundId } = await masterIds(request, asisten);
  expect(accountId).toBeTruthy();
  expect(fundId).toBeTruthy();

  const created = await request.post(
    `${apiUrl}/disbursements`,
    options(asisten, {
      disbursement_date: new Date().toISOString().slice(0, 10),
      account_id: accountId,
      amount: "54321.00",
      payee: "Penerima E2E",
      category: "Automation",
      description: "Pengeluaran lintas role Playwright",
      sources: [{ fund_id: fundId, amount: "54321.00" }],
    }),
  );
  expect(created.status()).toBe(201);
  const disbursementId = (await created.json()).data.id as number;

  expect((await request.post(`${apiUrl}/disbursements/${disbursementId}/submit`, options(asisten))).status()).toBe(200);
  expect((await request.post(`${apiUrl}/disbursements/${disbursementId}/approve`, options(asisten))).status()).toBe(
    403,
  );

  const verified = await request.post(`${apiUrl}/disbursements/${disbursementId}/verify`, options(verifikator));
  expect(verified.status()).toBe(200);
  expect((await verified.json()).data.status).toBe("verified");

  const approved = await request.post(`${apiUrl}/disbursements/${disbursementId}/approve`, options(bendahara));
  expect(approved.status()).toBe(200);
  expect((await approved.json()).data.status).toBe("approved");

  const reversed = await request.post(
    `${apiUrl}/disbursements/${disbursementId}/reverse`,
    options(bendahara, { reason: "Reversal otomatis skenario E2E" }),
  );
  expect(reversed.status()).toBe(200);
  expect((await reversed.json()).data.status).toBe("reversed");
});
