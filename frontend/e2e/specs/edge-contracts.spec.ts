import { type APIRequestContext, expect, test } from "@playwright/test";

import { type Role, roleAccounts } from "../fixtures/auth";

const apiUrl = "http://127.0.0.1:8100/api";
const today = new Date().toISOString().slice(0, 10);

async function tokenFor(request: APIRequestContext, role: Role): Promise<string> {
  const response = await request.post(`${apiUrl}/login`, {
    data: { login: roleAccounts[role], password: "password", device_name: "playwright-edge" },
  });
  expect(response.ok()).toBe(true);

  return (await response.json()).data.token as string;
}

function options(token: string, data?: unknown) {
  return { headers: { Authorization: `Bearer ${token}`, Accept: "application/json" }, data };
}

async function financeRows(request: APIRequestContext, token: string) {
  const [accountsResponse, fundsResponse] = await Promise.all([
    request.get(`${apiUrl}/accounts?per_page=100`, options(token)),
    request.get(`${apiUrl}/funds?per_page=100`, options(token)),
  ]);
  expect(accountsResponse.status()).toBe(200);
  expect(fundsResponse.status()).toBe(200);

  return {
    accounts: (await accountsResponse.json()).data as Array<{ id: number; code: string; balance: string }>,
    funds: (await fundsResponse.json()).data as Array<{ id: number; code: string; balance: string }>,
  };
}

test("draft penerimaan dan pengeluaran dapat diedit lalu dibaca lengkap", async ({ request }) => {
  const asisten = await tokenFor(request, "asisten_bendahara");
  const { accounts, funds } = await financeRows(request, asisten);
  const accountId = accounts.find((row) => row.code === "DEMO-BCA")?.id;
  const fundId = funds.find((row) => row.code === "DEMO-UMUM")?.id;
  expect(accountId).toBeTruthy();
  expect(fundId).toBeTruthy();

  const receipt = await request.post(
    `${apiUrl}/receipts`,
    options(asisten, {
      receipt_date: today,
      account_id: accountId,
      channel: "cash",
      amount: "10000.00",
      description: "Draft awal E2E",
      allocations: [{ fund_id: fundId, amount: "10000.00" }],
    }),
  );
  expect(receipt.status()).toBe(201);
  const receiptId = (await receipt.json()).data.id as number;
  const receiptUpdated = await request.put(
    `${apiUrl}/receipts/${receiptId}`,
    options(asisten, {
      receipt_date: today,
      account_id: accountId,
      channel: "transfer",
      amount: "12500.00",
      description: "Draft diperbarui E2E",
      allocations: [{ fund_id: fundId, amount: "12500.00" }],
    }),
  );
  expect(receiptUpdated.status()).toBe(200);
  expect((await receiptUpdated.json()).data.amount).toBe("12500.00");
  const allocations = await request.get(`${apiUrl}/receipts/${receiptId}/allocations`, options(asisten));
  expect(allocations.status()).toBe(200);
  expect((await allocations.json()).data[0].amount).toBe("12500.00");

  const expense = await request.post(
    `${apiUrl}/disbursements`,
    options(asisten, {
      disbursement_date: today,
      account_id: accountId,
      amount: "9000.00",
      payee: "Penerima Draft E2E",
      sources: [{ fund_id: fundId, amount: "9000.00" }],
    }),
  );
  expect(expense.status()).toBe(201);
  const expenseId = (await expense.json()).data.id as number;
  const expenseUpdated = await request.put(
    `${apiUrl}/disbursements/${expenseId}`,
    options(asisten, {
      disbursement_date: today,
      account_id: accountId,
      amount: "9500.00",
      payee: "Penerima Draft Diperbarui",
      description: "Draft pengeluaran diperbarui E2E",
      sources: [{ fund_id: fundId, amount: "9500.00" }],
    }),
  );
  expect(expenseUpdated.status()).toBe(200);
  expect((await expenseUpdated.json()).data.amount).toBe("9500.00");
  const expenseDetail = await request.get(`${apiUrl}/disbursements/${expenseId}`, options(asisten));
  expect(expenseDetail.status()).toBe(200);
  expect((await expenseDetail.json()).data.payee).toBe("Penerima Draft Diperbarui");
});

test("idempotency key mencegah penerimaan ganda", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const { accounts, funds } = await financeRows(request, bendahara);
  const accountId = accounts.find((row) => row.code === "DEMO-BCA")?.id;
  const fundId = funds.find((row) => row.code === "DEMO-UMUM")?.id;
  const reference = `IDEMPOTENT-E2E-${Date.now()}`;
  const data = {
    receipt_date: today,
    account_id: accountId,
    channel: "transfer",
    reference_number: reference,
    amount: "11000.00",
    allocations: [{ fund_id: fundId, amount: "11000.00" }],
  };
  const headers = {
    Authorization: `Bearer ${bendahara}`,
    Accept: "application/json",
    "Idempotency-Key": reference,
  };

  const first = await request.post(`${apiUrl}/receipts`, { headers, data });
  const second = await request.post(`${apiUrl}/receipts`, { headers, data });
  expect(first.status()).toBe(201);
  expect(second.status()).toBe(201);
  expect((await second.json()).data.id).toBe((await first.json()).data.id);

  const listed = await request.get(`${apiUrl}/receipts?q=${reference}`, options(bendahara));
  expect(listed.status()).toBe(200);
  expect((await listed.json()).data).toHaveLength(1);
});

test("transfer menolak tujuan sama dan saldo sumber tidak cukup", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const { accounts, funds } = await financeRows(request, bendahara);
  const account = accounts.find((row) => row.code === "DEMO-KAS");
  const sourceFund = funds.find((row) => row.code === "DEMO-UMUM");
  const destinationFund = funds.find((row) => row.code === "SYS-BANKADMIN");
  expect(account).toBeTruthy();
  expect(sourceFund).toBeTruthy();
  expect(destinationFund).toBeTruthy();

  const sameAccount = await request.post(
    `${apiUrl}/account-transfers`,
    options(bendahara, {
      transfer_date: today,
      from_account_id: account?.id,
      to_account_id: account?.id,
      amount: "1000.00",
    }),
  );
  expect(sameAccount.status()).toBe(422);

  const excessiveFundTransfer = await request.post(
    `${apiUrl}/fund-transfers`,
    options(bendahara, {
      transfer_date: today,
      from_fund_id: sourceFund?.id,
      to_fund_id: destinationFund?.id,
      amount: "999999999999.00",
      description: "Saldo tidak cukup E2E",
    }),
  );
  expect(excessiveFundTransfer.status()).toBe(201);
  const transferId = (await excessiveFundTransfer.json()).data.id as number;
  const posting = await request.post(`${apiUrl}/fund-transfers/${transferId}/post`, options(bendahara));
  expect(posting.status()).toBe(422);
  expect((await posting.json()).errors.code).toBe("insufficient_balance");
});

test("rekonsiliasi menerima item mutasi rekening dan menampilkannya di detail", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const { accounts } = await financeRows(request, bendahara);
  const account = accounts.find((row) => row.code === "DEMO-BCA");
  expect(account).toBeTruthy();

  const reconciliation = await request.post(
    `${apiUrl}/bank-reconciliations`,
    options(bendahara, {
      account_id: account?.id,
      period_start: today.slice(0, 8).concat("01"),
      period_end: today,
      statement_balance: account?.balance,
      notes: "Rekonsiliasi dengan item E2E",
    }),
  );
  expect(reconciliation.status()).toBe(201);
  const id = (await reconciliation.json()).data.id as number;

  const line = await request.post(
    `${apiUrl}/bank-reconciliations/${id}/lines`,
    options(bendahara, {
      statement_date: today,
      statement_ref: "BANK-E2E-001",
      statement_amount: "0.00",
      is_matched: true,
      note: "Item statement E2E",
    }),
  );
  expect(line.status()).toBe(201);

  const detail = await request.get(`${apiUrl}/bank-reconciliations/${id}`, options(bendahara));
  expect(detail.status()).toBe(200);
  expect((await detail.json()).data.lines).toEqual(
    expect.arrayContaining([expect.objectContaining({ statement_ref: "BANK-E2E-001", is_matched: true })]),
  );
});

test("lookup bantuan, opsi portal, filter, sort, dan pagination tersedia", async ({ request }) => {
  const petugas = await tokenFor(request, "petugas_bantuan");
  for (const path of ["/grant-applications/verifiers", "/grant-applications/handover-officers"]) {
    const response = await request.get(`${apiUrl}${path}`, options(petugas));
    expect(response.status(), path).toBe(200);
    expect((await response.json()).data.length, path).toBeGreaterThan(0);
  }

  const bendahara = await tokenFor(request, "bendahara");
  const portalOptions = await request.get(`${apiUrl}/donors/portal-login-options`, options(bendahara));
  expect(portalOptions.status()).toBe(200);
  expect(Array.isArray((await portalOptions.json()).data)).toBe(true);

  const filtered = await request.get(
    `${apiUrl}/receipts?status=approved&sort=receipt_date&direction=desc&page=1&per_page=1`,
    options(bendahara),
  );
  expect(filtered.status()).toBe(200);
  const payload = await filtered.json();
  expect(payload.data).toHaveLength(1);
  expect(payload.data[0].status).toBe("approved");
  expect(payload.meta.pagination.current_page).toBe(1);
  expect(payload.meta.pagination.per_page).toBe(1);
});
