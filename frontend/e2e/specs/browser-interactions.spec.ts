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

async function createVerifiedGrant(request: APIRequestContext, collective = false) {
  const token = await tokenFor(request, "petugas_bantuan");
  const headers = { Authorization: `Bearer ${token}`, Accept: "application/json" };
  const created = await request.post(`${apiUrl}/grant-applications`, {
    headers,
    data: {
      beneficiary_type: "individual",
      beneficiary_scope: collective ? "collective" : "individual",
      recipient_name: collective ? "Warga Kolektif Browser" : "Penerima Bantuan Browser",
      recommended_amount: "18000.00",
      reason: "Workflow bantuan melalui browser",
      recommender_name: "Petugas Browser",
      ...(collective
        ? {
            target_beneficiary_count: 75,
            beneficiary_count_method: "estimated",
            beneficiary_location: "Desa Browser",
            beneficiary_count_notes: "Estimasi data warga",
          }
        : {}),
    },
  });
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as number;
  expect((await request.post(`${apiUrl}/grant-applications/${id}/send-to-verification`, { headers })).status()).toBe(
    200,
  );
  const updated = await request.put(`${apiUrl}/grant-applications/${id}`, {
    headers,
    data: {
      recipient_identity_number: "3201010101010077",
      recipient_address: "Alamat Bantuan Browser",
      verified_amount: "18000.00",
      verifier_notes: "Layak berdasarkan verifikasi browser",
    },
  });
  expect(updated.status()).toBe(200);

  return { id, token, headers };
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

test("asisten bendahara dapat membuat penerimaan dengan alokasi operasional dari UI", async ({ page }) => {
  await loginAs(page, "asisten_bendahara");
  await page.goto("/dashboard/receipts/new");
  await page
    .getByRole("group")
    .filter({ hasText: "Akun Kas/Bank" })
    .getByRole("combobox")
    .selectOption({ label: "BCA Operasional (Demo)" });
  await page.getByLabel("Channel").selectOption("transfer");

  const moneyInputs = page.locator('input[inputmode="numeric"]');
  await moneyInputs.nth(0).fill("100000");
  await page.getByRole("button", { name: "10% Operasional" }).click();
  await page.getByRole("button", { name: "Tambah Baris" }).click();

  const allocationCard = page.getByText("Alokasi Dana", { exact: true }).locator("../..");
  await allocationCard
    .getByRole("group")
    .filter({ hasText: "Dana Amanah" })
    .nth(1)
    .getByRole("combobox")
    .selectOption({ label: "Dana Sosial Umum (Demo)" });
  await allocationCard.getByRole("button", { name: "Isi sisa" }).click();
  await page.getByRole("button", { name: "Simpan" }).click();

  await expect(page).toHaveURL(/\/dashboard\/receipts\/\d+$/);
  await expect(page.getByText("Data berhasil dibuat.")).toBeVisible();
  await expect(page.getByRole("heading", { name: /RCP\// })).toBeVisible();
  await expect(page.getByText("Dana Operasional", { exact: false })).toBeVisible();
  await expect(page.getByText("Dana Sosial Umum (Demo)", { exact: true })).toBeVisible();
});

test("asisten bendahara dapat membuat draft pengeluaran dengan sumber dana melalui UI", async ({ page }) => {
  await loginAs(page, "asisten_bendahara");
  await page.goto("/dashboard/disbursements/new");
  await page
    .getByRole("group")
    .filter({ hasText: "Akun Kas/Bank" })
    .getByRole("combobox")
    .selectOption({ label: "BCA Operasional (Demo)" });
  await page.getByLabel("Penerima").fill("Penerima Pengeluaran dari Browser");
  await page.getByLabel("Keterangan").fill("Draft pengeluaran dibuat melalui browser E2E");

  const moneyInputs = page.locator('input[inputmode="numeric"]');
  await moneyInputs.nth(0).fill("25000");
  const sourceCard = page.getByText("Sumber Dana", { exact: true }).locator("../..");
  await sourceCard
    .getByRole("group")
    .filter({ hasText: "Dana Amanah" })
    .getByRole("combobox")
    .selectOption({ label: "Dana Sosial Umum (Demo)" });
  await sourceCard.getByRole("button", { name: "Isi sisa" }).click();
  await page.getByRole("button", { name: "Simpan" }).click();

  await expect(page).toHaveURL(/\/dashboard\/disbursements\/\d+$/);
  await expect(page.getByText("Data berhasil dibuat.")).toBeVisible();
  await expect(page.getByRole("heading", { name: /DSB\// })).toBeVisible();
  await expect(page.getByText("draft", { exact: true }).first()).toBeVisible();
  await expect(page.getByText("Penerima Pengeluaran dari Browser", { exact: true })).toBeVisible();
  await expect(page.getByText("Dana Sosial Umum (Demo)", { exact: true })).toBeVisible();
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

test("bantuan dapat diajukan lalu dikembalikan ke verifikasi melalui UI", async ({ page, request }) => {
  const grant = await createVerifiedGrant(request);
  await loginAs(page, "petugas_bantuan");
  await page.goto(`/dashboard/bantuan/${grant.id}`);
  await page.getByRole("button", { name: "Ajukan ke approval" }).click();
  await page.getByRole("alertdialog").getByRole("button", { name: "Ajukan ke approval" }).click();
  await expect(page.getByText("Menunggu approval", { exact: true }).first()).toBeVisible();

  await page.context().clearCookies();
  await loginAs(page, "ketua");
  await page.goto(`/dashboard/bantuan/${grant.id}`);
  await page.getByRole("button", { name: "Kembalikan" }).click();
  const dialog = page.getByRole("alertdialog");
  const returnButton = dialog.getByRole("button", { name: "Kembalikan" });
  await expect(returnButton).toBeDisabled();
  await dialog.getByLabel("Alasan pengembalian").fill("Mohon lengkapi hasil survei lapangan");
  await returnButton.click();
  await expect(page.getByText("Kembalikan berhasil.")).toBeVisible();
  await expect(page.getByText("Verifikasi", { exact: true }).first()).toBeVisible();
});

test("bantuan kolektif mewajibkan realisasi orang terbantu sebelum selesai", async ({ page, request }) => {
  const grant = await createVerifiedGrant(request, true);
  expect(
    (
      await request.post(`${apiUrl}/grant-applications/${grant.id}/submit-for-approval`, { headers: grant.headers })
    ).status(),
  ).toBe(200);
  const ketua = await tokenFor(request, "ketua");
  expect(
    (
      await request.post(`${apiUrl}/grant-applications/${grant.id}/approve`, {
        headers: { Authorization: `Bearer ${ketua}`, Accept: "application/json" },
        data: { approved_amount: "18000.00" },
      })
    ).status(),
  ).toBe(200);

  await loginAs(page, "petugas_bantuan");
  await page.goto(`/dashboard/bantuan/${grant.id}`);
  const completeButton = page.getByRole("button", { name: "Tandai selesai" });
  await expect(completeButton).toBeDisabled();
  await page.getByLabel("Realisasi orang terbantu").fill("72");
  await expect(completeButton).toBeEnabled();
  await expect(page.getByPlaceholder("Target: 75")).toHaveValue("72");
});
