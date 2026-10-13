import { expect, test } from "@playwright/test";

import { loginAs } from "../fixtures/auth";
import { caption, confirmAction, selectRelation, switchRole } from "./helpers";

test("alur dashboard dan dana amanah sampai rekonsiliasi seimbang", async ({ page }) => {
  test.setTimeout(10 * 60 * 1000);

  await loginAs(page, "bendahara");
  await caption(page, "1. Login bendahara — dashboard dari buku besar, bukan angka statis");
  await expect(page.getByRole("heading", { name: "Dashboard" })).toBeVisible();
  await expect(page.getByText("Saldo Kas").first()).toBeVisible();
  await expect(page.getByText("Saldo Dana Amanah").first()).toBeVisible();
  await expect(page.getByText("Seimbang", { exact: true })).toBeVisible();
  await caption(page, "2. Rekonsiliasi global: kas/bank = Dana Amanah (selisih 0)", 1800);

  await page.getByText("Rekonsiliasi Global", { exact: true }).scrollIntoViewIfNeeded();
  await expect(page.getByText("Total Kas/Bank", { exact: true })).toBeVisible();
  await expect(page.getByText("Total Dana Amanah", { exact: true })).toBeVisible();
  await caption(page, "3. Dua dimensi: di mana uang berada, dan untuk apa boleh dipakai", 1600);

  await page.goto("/dashboard/funds");
  await expect(page.getByRole("heading", { name: "Dana Amanah" })).toBeVisible();
  await caption(page, "4. Dana restricted vs unrestricted — pembatas penggunaan, bukan rekening", 1400);

  await page.getByLabel("Tipe").selectOption("restricted");
  await expect(page.getByRole("link", { name: "Zakat Mustahik (Demo)" })).toBeVisible();
  await expect(page.getByText("restricted").first()).toBeVisible();
  await caption(page, "5. Restricted: Zakat Mustahik — hanya untuk peruntukan zakat", 1600);
  await page.getByRole("link", { name: "Zakat Mustahik (Demo)" }).click();
  await expect(page.getByRole("heading", { name: "Zakat Mustahik (Demo)" })).toBeVisible();
  await page.waitForTimeout(900);

  await page.goto("/dashboard/funds");
  await page.getByLabel("Tipe").selectOption("unrestricted");
  await expect(page.getByRole("link", { name: "Dana Sosial Umum (Demo)" })).toBeVisible();
  await caption(page, "6. Unrestricted: Dana Sosial Umum — fleksibel untuk kebutuhan sah", 1600);

  await page.goto("/dashboard/accounts");
  await expect(page.getByRole("heading", { name: "Kas/Bank" })).toBeVisible();
  await expect(page.getByText("Kas Kantor (Demo)")).toBeVisible();
  await expect(page.getByText("BCA Operasional (Demo)")).toBeVisible();
  await caption(page, "7. Kas/Bank = lokasi fisik uang. Totalnya harus seimbang dengan dana", 1800);

  await page.goto("/dashboard/reports/fund-balances");
  await expect(page.getByRole("heading", { name: "Saldo Dana Amanah" })).toBeVisible();
  await expect(page.getByText("Zakat Mustahik (Demo)")).toBeVisible();
  await caption(page, "Selesai: dashboard, dana, rekening, dan rekonsiliasi Seimbang", 2000);
});

test("alur penerimaan dari draft sampai approve", async ({ page }) => {
  test.setTimeout(10 * 60 * 1000);

  await loginAs(page, "asisten_bendahara");
  await caption(page, "1. Asisten bendahara membuat penerimaan + alokasi niat dana");
  await page.goto("/dashboard/receipts/new");

  await selectRelation(page, "Akun Kas/Bank", "BCA Operasional (Demo)");
  await selectRelation(page, "Donatur", "Budi Santoso");
  await page.getByLabel("Channel").selectOption("transfer");
  await page.getByLabel("No. Referensi").fill("TRF-DEMO-VIDEO-001");
  await page.locator('input[inputmode="numeric"]').nth(0).fill("500000");
  await page.getByLabel("Keterangan").fill("Donasi Budi — 10% operasional, sisanya Dana Sosial Umum.");
  await caption(page, "2. Setiap uang masuk wajib punya alokasi / niat dana", 1200);

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
  await caption(page, "3. Simpan draft, lalu submit ke antrian approval", 900);
  await page.getByRole("button", { name: "Simpan" }).click();
  await expect(page).toHaveURL(/\/dashboard\/receipts\/\d+$/);
  await expect(page.getByRole("heading", { name: /RCP\// })).toBeVisible();
  const receiptUrl = page.url();

  await confirmAction(page, "Submit");
  await expect(page.getByText("Submit berhasil.")).toBeVisible();
  await expect(page.getByText("submitted", { exact: true }).first()).toBeVisible();

  await caption(page, "4. Ketua menyetujui — baru saat itu jurnal diposting", 1000);
  await switchRole(page, "ketua");
  await page.goto(receiptUrl);
  await confirmAction(page, "Setujui");
  await expect(page.getByText("Setujui berhasil.")).toBeVisible();
  await expect(page.getByText("approved", { exact: true }).first()).toBeVisible();

  await caption(page, "5. Dashboard tetap Seimbang; saldo kas dan dana naik bersama", 1000);
  await switchRole(page, "bendahara");
  await page.goto("/dashboard/default");
  await expect(page.getByText("Seimbang", { exact: true })).toBeVisible();
  await caption(page, "Selesai: draft → submit → approve = posting ledger", 2000);
});

test("alur pengeluaran dari draft sampai approve", async ({ page }) => {
  test.setTimeout(10 * 60 * 1000);

  await loginAs(page, "asisten_bendahara");
  await caption(page, "1. Asisten membuat pengeluaran dari Dana Amanah yang sesuai");
  await page.goto("/dashboard/disbursements/new");

  await selectRelation(page, "Akun Kas/Bank", "Kas Kantor (Demo)");
  await page.getByLabel("Penerima").fill("Toko Sembako Makmur");
  await page.getByLabel("Kategori").fill("Santunan");
  await page.locator('input[inputmode="numeric"]').nth(0).fill("150000");
  await page.getByLabel("Keterangan").fill("Paket sembako warga RT 05 — diambil dari Dana Sosial Umum.");
  const sourceCard = page.getByText("Sumber Dana", { exact: true }).locator("../..");
  await sourceCard
    .getByRole("group")
    .filter({ hasText: "Dana Amanah" })
    .getByRole("combobox")
    .selectOption({ label: "Dana Sosial Umum (Demo)" });
  await sourceCard.getByRole("button", { name: "Isi sisa" }).click();
  await caption(page, "2. Uang keluar wajib mengambil dari dana yang boleh dipakai", 1200);
  await page.getByRole("button", { name: "Simpan" }).click();
  await expect(page).toHaveURL(/\/dashboard\/disbursements\/\d+$/);
  await expect(page.getByRole("heading", { name: /DSB\// })).toBeVisible();
  const expenseUrl = page.url();

  await confirmAction(page, "Submit");
  await expect(page.getByText("Submit berhasil.")).toBeVisible();

  await caption(page, "3. Verifikator memeriksa kelengkapan sebelum approve keuangan", 1000);
  await switchRole(page, "verifikator");
  await page.goto(expenseUrl);
  await confirmAction(page, "Verifikasi");
  await expect(page.getByText("Verifikasi berhasil.")).toBeVisible();

  await caption(page, "4. Bendahara menyetujui — posting ke buku besar, saldo turun", 1000);
  await switchRole(page, "bendahara");
  await page.goto(expenseUrl);
  await confirmAction(page, "Setujui");
  await expect(page.getByText("Setujui berhasil.")).toBeVisible();
  await expect(page.getByText("approved", { exact: true }).first()).toBeVisible();

  await page.goto("/dashboard/default");
  await expect(page.getByText("Seimbang", { exact: true })).toBeVisible();
  await caption(page, "Selesai: draft → submit → verifikasi → approve = posting", 2000);
});

test("alur portal donatur melihat donasi approved", async ({ page }) => {
  test.setTimeout(10 * 60 * 1000);

  await loginAs(page, "donatur");
  await caption(page, "1. Donatur login — hanya melihat donasinya sendiri");
  await page.goto("/dashboard/portal-donatur");
  await expect(page.getByRole("heading", { name: "Portal Donatur" })).toBeVisible();
  await expect(page.getByText("Budi Santoso").first()).toBeVisible();
  await expect(page.getByText("Total Donasi")).toBeVisible();
  await expect(page.getByText("Riwayat Donasi")).toBeVisible();
  await caption(page, "2. Bukan seluruh kas lembaga — hanya penerimaan approved atas namanya", 1800);
  await expect(page.getByText(/RCP\//).first()).toBeVisible();
  await caption(page, "Selesai: transparansi ke donatur tanpa membuka buku lembaga", 2000);
});
