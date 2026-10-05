import { type APIRequestContext, expect, test } from "@playwright/test";

import { loginAs, type Role, roleAccounts } from "../fixtures/auth";

const apiUrl = "http://127.0.0.1:8100/api";

async function tokenFor(request: APIRequestContext, role: Role): Promise<string> {
  const response = await request.post(`${apiUrl}/login`, {
    data: { login: roleAccounts[role], password: "password", device_name: "playwright-browser-ui" },
  });
  expect(response.status()).toBe(200);

  return (await response.json()).data.token as string;
}

async function createReceipt(request: APIRequestContext) {
  const token = await tokenFor(request, "asisten_bendahara");
  const headers = { Authorization: `Bearer ${token}`, Accept: "application/json" };
  const [accountsResponse, fundsResponse] = await Promise.all([
    request.get(`${apiUrl}/accounts?per_page=100`, { headers }),
    request.get(`${apiUrl}/funds?per_page=100`, { headers }),
  ]);
  const accounts = (await accountsResponse.json()).data as Array<{ id: number; code: string }>;
  const funds = (await fundsResponse.json()).data as Array<{ id: number; code: string }>;
  const accountId = accounts.find((row) => row.code === "DEMO-BCA")?.id;
  const fundId = funds.find((row) => row.code === "DEMO-UMUM")?.id;
  const response = await request.post(`${apiUrl}/receipts`, {
    headers,
    data: {
      receipt_date: new Date().toISOString().slice(0, 10),
      account_id: accountId,
      channel: "transfer",
      amount: "14000.00",
      description: "Workflow browser E2E",
      allocations: [{ fund_id: fundId, amount: "14000.00" }],
    },
  });
  expect(response.status()).toBe(201);

  return (await response.json()).data.id as number;
}

async function createDisbursement(request: APIRequestContext) {
  const token = await tokenFor(request, "asisten_bendahara");
  const headers = { Authorization: `Bearer ${token}`, Accept: "application/json" };
  const [accountsResponse, fundsResponse] = await Promise.all([
    request.get(`${apiUrl}/accounts?per_page=100`, { headers }),
    request.get(`${apiUrl}/funds?per_page=100`, { headers }),
  ]);
  const accounts = (await accountsResponse.json()).data as Array<{ id: number; code: string }>;
  const funds = (await fundsResponse.json()).data as Array<{ id: number; code: string }>;
  const accountId = accounts.find((row) => row.code === "DEMO-BCA")?.id;
  const fundId = funds.find((row) => row.code === "DEMO-UMUM")?.id;
  const response = await request.post(`${apiUrl}/disbursements`, {
    headers,
    data: {
      disbursement_date: new Date().toISOString().slice(0, 10),
      account_id: accountId,
      amount: "16000.00",
      payee: "Penerima Workflow Browser",
      description: "Workflow pengeluaran browser E2E",
      sources: [{ fund_id: fundId, amount: "16000.00" }],
    },
  });
  expect(response.status()).toBe(201);

  return (await response.json()).data.id as number;
}

test("bendahara dapat membuat, mengedit, mencari, dan menghapus vendor melalui UI", async ({ page }) => {
  const suffix = Date.now();
  const initialName = `Vendor Browser ${suffix}`;
  const updatedName = `${initialName} Diperbarui`;

  await loginAs(page, "bendahara");
  await page.goto("/dashboard/vendors/new");
  await page.getByLabel("Nama *").fill(initialName);
  await page.getByLabel("Nama Kontak").fill("Kontak Browser");
  await page.getByLabel("Email").fill(`vendor-browser-${suffix}@example.test`);
  await page.getByRole("button", { name: "Simpan" }).click();

  await expect(page).toHaveURL(/\/dashboard\/vendors\/\d+$/);
  await expect(page.getByRole("heading", { name: initialName })).toBeVisible();
  await page.getByRole("link", { name: "Edit" }).click();
  await expect(page).toHaveURL(/\/edit$/);
  await page.getByLabel("Nama *").fill(updatedName);
  await page.getByRole("button", { name: "Simpan" }).click();

  await expect(page).toHaveURL(/\/dashboard\/vendors\/\d+$/);
  await expect(page.getByRole("heading", { name: updatedName })).toBeVisible();
  await page.goto("/dashboard/vendors");
  await page.getByPlaceholder("Cari...").fill(updatedName);
  await page.getByPlaceholder("Cari...").press("Enter");
  await expect(page.getByRole("link", { name: updatedName })).toBeVisible();
  await page.getByRole("link", { name: updatedName }).click();

  await page.getByRole("button", { name: "Hapus" }).click();
  const dialog = page.getByRole("alertdialog");
  await expect(dialog).toBeVisible();
  await dialog.getByRole("button", { name: "Hapus" }).click();
  await expect(page).toHaveURL(/\/dashboard\/vendors$/);
  await expect(page.getByText("Data berhasil dihapus.")).toBeVisible();
});

test("form program menampilkan validasi lalu dapat membuat event darurat", async ({ page }) => {
  const name = `Respons Bencana Browser ${Date.now()}`;

  await loginAs(page, "bendahara");
  await page.goto("/dashboard/programs/new");
  await page.getByRole("button", { name: "Simpan" }).click();
  await expect(page.getByText("Nama wajib diisi.")).toBeVisible();

  await page.getByLabel("Nama *").fill(name);
  await page.getByLabel("Tipe Event *").selectOption("emergency");
  await page.getByLabel("Status").selectOption("active");
  await page.getByRole("button", { name: "Simpan" }).click();

  await expect(page).toHaveURL(/\/dashboard\/programs\/\d+$/);
  await expect(page.getByRole("heading", { name })).toBeVisible();
  await expect(page.getByText("emergency", { exact: true })).toBeVisible();
});

test("petugas bantuan tidak dapat membuka halaman master data lewat URL langsung", async ({ page }) => {
  await loginAs(page, "petugas_bantuan");
  await page.goto("/dashboard/funds");

  await expect(page.getByText("Tidak dapat mengakses", { exact: true })).toBeVisible();
  await expect(page.getByText("Anda tidak memiliki izin untuk melihat data ini.")).toBeVisible();
  await expect(page.getByText("Dana Amanah", { exact: true })).toHaveCount(0);
});

test("penerimaan dapat disubmit dan disetujui lintas role melalui UI", async ({ page, request }) => {
  const receiptId = await createReceipt(request);
  await loginAs(page, "asisten_bendahara");
  await page.goto(`/dashboard/receipts/${receiptId}`);
  await page.getByRole("button", { name: "Submit" }).click();
  await page.getByRole("alertdialog").getByRole("button", { name: "Submit" }).click();
  await expect(page.getByText("Submit berhasil.")).toBeVisible();
  await expect(page.getByText("submitted", { exact: true }).first()).toBeVisible();

  await page.context().clearCookies();
  await loginAs(page, "ketua");
  await page.goto(`/dashboard/receipts/${receiptId}`);
  await page.getByRole("button", { name: "Setujui" }).click();
  const approvalDialog = page.getByRole("alertdialog");
  await approvalDialog.getByLabel("Catatan").fill("Disetujui melalui browser E2E");
  await approvalDialog.getByRole("button", { name: "Setujui" }).click();
  await expect(page.getByText("Setujui berhasil.")).toBeVisible();
  await expect(page.getByText("approved", { exact: true }).first()).toBeVisible();
});

test("dialog penolakan mewajibkan alasan sebelum dapat diproses", async ({ page, request }) => {
  const receiptId = await createReceipt(request);
  const asisten = await tokenFor(request, "asisten_bendahara");
  expect(
    (
      await request.post(`${apiUrl}/receipts/${receiptId}/submit`, {
        headers: { Authorization: `Bearer ${asisten}`, Accept: "application/json" },
      })
    ).status(),
  ).toBe(200);

  await loginAs(page, "ketua");
  await page.goto(`/dashboard/receipts/${receiptId}`);
  await page.getByRole("button", { name: "Tolak" }).click();
  const dialog = page.getByRole("alertdialog");
  const rejectButton = dialog.getByRole("button", { name: "Tolak" });
  await expect(rejectButton).toBeDisabled();
  await dialog.getByLabel("Alasan penolakan").fill("Bukti belum dapat diverifikasi");
  await expect(rejectButton).toBeEnabled();
  await rejectButton.click();
  await expect(page.getByText("Tolak berhasil.")).toBeVisible();
  await expect(page.getByText("rejected", { exact: true }).first()).toBeVisible();
});

test("pengeluaran dapat disubmit, diverifikasi, dan disetujui lintas role melalui UI", async ({ page, request }) => {
  const id = await createDisbursement(request);
  await loginAs(page, "asisten_bendahara");
  await page.goto(`/dashboard/disbursements/${id}`);
  await page.getByRole("button", { name: "Submit" }).click();
  await page.getByRole("alertdialog").getByRole("button", { name: "Submit" }).click();
  await expect(page.getByText("submitted", { exact: true }).first()).toBeVisible();

  await page.context().clearCookies();
  await loginAs(page, "verifikator");
  await page.goto(`/dashboard/disbursements/${id}`);
  await page.getByRole("button", { name: "Verifikasi" }).click();
  const verifyDialog = page.getByRole("alertdialog");
  await verifyDialog.getByLabel("Catatan").fill("Diverifikasi melalui browser");
  await verifyDialog.getByRole("button", { name: "Verifikasi" }).click();
  await expect(page.getByText("verified", { exact: true }).first()).toBeVisible();

  await page.context().clearCookies();
  await loginAs(page, "bendahara");
  await page.goto(`/dashboard/disbursements/${id}`);
  await page.getByRole("button", { name: "Setujui" }).click();
  const approvalDialog = page.getByRole("alertdialog");
  await approvalDialog.getByLabel("Catatan").fill("Disetujui melalui browser");
  await approvalDialog.getByRole("button", { name: "Setujui" }).click();
  await expect(page.getByText("Setujui berhasil.")).toBeVisible();
  await expect(page.getByText("approved", { exact: true }).first()).toBeVisible();
});

test("pengeluaran approved dapat direverse melalui dialog alasan wajib", async ({ page, request }) => {
  const id = await createDisbursement(request);
  const asisten = await tokenFor(request, "asisten_bendahara");
  const verifikator = await tokenFor(request, "verifikator");
  const bendahara = await tokenFor(request, "bendahara");
  const headers = (token: string) => ({ Authorization: `Bearer ${token}`, Accept: "application/json" });
  expect((await request.post(`${apiUrl}/disbursements/${id}/submit`, { headers: headers(asisten) })).status()).toBe(
    200,
  );
  expect((await request.post(`${apiUrl}/disbursements/${id}/verify`, { headers: headers(verifikator) })).status()).toBe(
    200,
  );
  expect((await request.post(`${apiUrl}/disbursements/${id}/approve`, { headers: headers(bendahara) })).status()).toBe(
    200,
  );

  await loginAs(page, "bendahara");
  await page.goto(`/dashboard/disbursements/${id}`);
  await page.getByRole("button", { name: "Reverse" }).click();
  const dialog = page.getByRole("alertdialog");
  const reverseButton = dialog.getByRole("button", { name: "Reverse" });
  await expect(reverseButton).toBeDisabled();
  await dialog.getByLabel("Alasan reversal").fill("Transaksi duplikat hasil verifikasi browser");
  await reverseButton.click();
  await expect(page.getByText("Reverse berhasil.")).toBeVisible();
  await expect(page.getByText("reversed", { exact: true }).first()).toBeVisible();
});
