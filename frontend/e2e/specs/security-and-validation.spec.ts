import { type APIRequestContext, expect, test } from "@playwright/test";

import { loginAs, type Role, roleAccounts } from "../fixtures/auth";

const apiUrl = "http://127.0.0.1:8100/api";

async function tokenFor(request: APIRequestContext, role: Role): Promise<string> {
  const response = await request.post(`${apiUrl}/login`, {
    data: { login: roleAccounts[role], password: "password", device_name: "playwright-security" },
  });
  expect(response.ok()).toBe(true);

  return (await response.json()).data.token as string;
}

function bearer(token: string) {
  return { Authorization: `Bearer ${token}`, Accept: "application/json" };
}

test("login salah menampilkan error dan tidak membuat sesi", async ({ page }) => {
  await page.goto("/auth/v2/login");
  await page.getByLabel("Email atau nomor HP").fill(roleAccounts.bendahara);
  await page.getByLabel("Password").fill("password-salah");
  await page.getByRole("button", { name: "Masuk" }).click();

  await expect(page.getByText("Kredensial tidak valid.")).toBeVisible();
  await expect(page).toHaveURL(/auth\/v2\/login/);
  await expect(page.context().cookies()).resolves.not.toEqual(
    expect.arrayContaining([expect.objectContaining({ name: "sima_token" })]),
  );
});

test("redirect halaman terlindungi dipertahankan setelah login", async ({ page }) => {
  await page.goto("/dashboard/receipts");
  await expect(page).toHaveURL(/auth\/v2\/login\?redirect=%2Fdashboard%2Freceipts/);

  await page.getByLabel("Email atau nomor HP").fill(roleAccounts.bendahara);
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Masuk" }).click();

  await expect(page).toHaveURL(/\/dashboard\/receipts$/);
  await expect(page.locator("main")).toBeVisible();
});

test("form penerimaan menahan submit ketika field wajib kosong", async ({ page }) => {
  await loginAs(page, "asisten_bendahara");
  await page.goto("/dashboard/receipts/new");
  await page.getByRole("button", { name: "Simpan" }).click();

  await expect(page.getByText("Akun Kas/Bank wajib diisi.")).toBeVisible();
  await expect(page.getByText("Nominal wajib diisi.").first()).toBeVisible();
  await expect(page).toHaveURL(/\/dashboard\/receipts\/new$/);
});

test("role terbatas ditolak oleh API walau endpoint dipanggil langsung", async ({ request }) => {
  const petugas = await tokenFor(request, "petugas_bantuan");
  const donor = await tokenFor(request, "donatur");
  const auditor = await tokenFor(request, "auditor");
  const verifikator = await tokenFor(request, "verifikator");

  expect((await request.get(`${apiUrl}/funds`, { headers: bearer(petugas) })).status()).toBe(403);
  expect((await request.get(`${apiUrl}/grant-applications`, { headers: bearer(donor) })).status()).toBe(403);
  expect((await request.post(`${apiUrl}/receipts`, { headers: bearer(auditor), data: {} })).status()).toBe(403);
  expect((await request.post(`${apiUrl}/receipts`, { headers: bearer(verifikator), data: {} })).status()).toBe(403);
});

test("asisten tidak dapat approve penerimaan yang sudah submitted", async ({ request }) => {
  const bendahara = await tokenFor(request, "bendahara");
  const asisten = await tokenFor(request, "asisten_bendahara");
  const list = await request.get(`${apiUrl}/receipts?status=submitted`, { headers: bearer(bendahara) });
  expect(list.ok()).toBe(true);

  const payload = await list.json();
  const receipt = payload.data.find((row: { status: string }) => row.status === "submitted");
  expect(receipt).toBeTruthy();

  const approval = await request.post(`${apiUrl}/receipts/${receipt.id}/approve`, {
    headers: bearer(asisten),
    data: { notes: "Tidak boleh disetujui asisten" },
  });
  expect(approval.status()).toBe(403);
});

test("donatur hanya dapat memakai endpoint portal miliknya", async ({ request }) => {
  const donor = await tokenFor(request, "donatur");

  expect((await request.get(`${apiUrl}/portal/profile`, { headers: bearer(donor) })).status()).toBe(200);
  expect((await request.get(`${apiUrl}/portal/summary`, { headers: bearer(donor) })).status()).toBe(200);
  expect((await request.get(`${apiUrl}/portal/donations`, { headers: bearer(donor) })).status()).toBe(200);
  expect((await request.get(`${apiUrl}/reports/fund-balances`, { headers: bearer(donor) })).status()).toBe(403);
});
