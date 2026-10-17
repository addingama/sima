import { expect, test } from "@playwright/test";

import { loginAs } from "../fixtures/auth";
import {
  addPettyCashLine,
  caption,
  closePettyCashFloat,
  fundAndApproveFloatDisbursement,
  selectRadixOption,
  selectSearchable,
  switchRole,
} from "./helpers";

test("ksk-ops dari buat float sampai tutup", async ({ page }) => {
  test.setTimeout(10 * 60 * 1000);
  const title = "Video Demo Operasional Kantor Lapangan";

  await loginAs(page, "bendahara");
  await caption(page, "1. Bendahara membuka modul Kas Kecil", 700);
  await page.goto("/dashboard/kas-kecil");
  await expect(page.getByRole("heading", { name: "Kas Kecil" })).toBeVisible();

  await caption(page, "2. Buat float operasional — kantong uang muka periode rutin", 800);
  await page.getByRole("link", { name: "Buat Float" }).click();
  await expect(page).toHaveURL(/\/dashboard\/kas-kecil\/new$/);

  await page.getByPlaceholder("cth. Operasional Oktober 2026").fill(title);
  await selectSearchable(page, "Cari nama PJ...", "Petugas Bantuan");
  await page.getByRole("group").filter({ hasText: "Periode Mulai" }).locator('input[type="date"]').fill("2026-10-01");
  await page.getByRole("group").filter({ hasText: "Periode Akhir" }).locator('input[type="date"]').fill("2026-10-31");
  await page.getByRole("group").filter({ hasText: "Catatan" }).locator("textarea").fill(
    "Anggaran bulanan ATK, transport, dan internet tim lapangan.",
  );
  await caption(page, "3. Simpan float draft — belum mengurangi ledger", 700);
  await page.getByRole("button", { name: "Buat Float" }).click();
  await expect(page).toHaveURL(/\/dashboard\/kas-kecil\/\d+$/);
  await expect(page.getByText(title).first()).toBeVisible();
  await expect(page.getByText("draft", { exact: true }).first()).toBeVisible();
  const floatUrl = page.url();

  await caption(page, "4. Cairkan anggaran awal via Pengeluaran (DSB)", 800);
  await fundAndApproveFloatDisbursement(page, {
    amount: "500000",
    accountLabel: "BCA Operasional (Demo)",
    fundLabel: "Dana Sosial Umum (Demo)",
    description: "Cair kas kecil operasional — video demo",
  });
  await caption(page, "5. Setelah DSB approved: float reporting — ledger sudah berkurang", 900);

  await caption(page, "6. PJ mencatat belanja — tanpa approval baris", 700);
  await switchRole(page, "petugas_bantuan");
  await page.goto(floatUrl);
  await expect(page.getByText(title).first()).toBeVisible();
  await expect(page.getByText("reporting", { exact: true }).first()).toBeVisible();

  // Habiskan tepat 500rb agar tutup tanpa setor sisa (PJ tidak punya account.view).
  await addPettyCashLine(page, {
    category: "ATK",
    amount: "150000",
    description: "Kertas, tinta, map arsip",
  });
  await caption(page, "7. Baris ATK tercatat langsung", 700);

  await addPettyCashLine(page, {
    category: "Transport",
    amount: "200000",
    description: "Bensin & ojek survey mustahik",
  });
  await addPettyCashLine(page, {
    category: "Internet & Pulsa",
    amount: "150000",
    description: "Paket data kantor lapangan",
  });
  await caption(page, "8. Total belanja = dana cair — siap tutup tanpa sisa", 900);

  await caption(page, "9. Tutup float: belanja + sisa = dana cair", 800);
  await closePettyCashFloat(page);
  await caption(page, "Selesai: operasional draft → cair → belanja → tutup", 1400);
});

test("ksk-event dari buat float sampai tutup", async ({ page }) => {
  test.setTimeout(10 * 60 * 1000);
  const title = "Video Demo Event Bakti Sosial";

  await loginAs(page, "bendahara");
  await caption(page, "1. Kas kecil event — float terpisah per kegiatan", 700);
  await page.goto("/dashboard/kas-kecil/new");
  await expect(page.getByRole("heading", { name: "Buat Float Kas Kecil" })).toBeVisible();

  await page.getByPlaceholder("cth. Operasional Oktober 2026").fill(title);
  await selectRadixOption(page, "Tujuan", "Event");
  await selectSearchable(page, "Cari nama PJ...", "Petugas Bantuan");
  await selectSearchable(page, "Cari event / program...", "Bakti Sosial Ramadan");
  await page.getByRole("group").filter({ hasText: "Catatan" }).locator("textarea").fill(
    "Uang muka panitia distribusi paket — terikat program Bakti Sosial Ramadan.",
  );
  await caption(page, "2. Pilih Event/Program — float tidak bercampur dengan operasional", 800);
  await page.getByRole("button", { name: "Buat Float" }).click();
  await expect(page).toHaveURL(/\/dashboard\/kas-kecil\/\d+$/);
  await expect(page.getByText("Bakti Sosial Ramadan").first()).toBeVisible();
  const floatUrl = page.url();

  await caption(page, "3. Cair dari Dana Amanah yang sesuai (Infaq Yatim)", 800);
  await fundAndApproveFloatDisbursement(page, {
    amount: "800000",
    accountLabel: "BCA Operasional (Demo)",
    fundLabel: "Infaq Yatim (Demo)",
    description: "Cair kas kecil event bakti sosial — video demo",
  });

  await caption(page, "4. PJ mencatat belanja event", 700);
  await switchRole(page, "petugas_bantuan");
  await page.goto(floatUrl);
  await expect(page.getByText("reporting", { exact: true }).first()).toBeVisible();

  await addPettyCashLine(page, {
    category: "Konsumsi",
    amount: "450000",
    description: "Konsumsi panitia & penerima paket",
  });
  await addPettyCashLine(page, {
    category: "Transport",
    amount: "350000",
    description: "Sewa angkutan distribusi",
  });
  await caption(page, "5. Belanja tepat menghabiskan float — tutup tanpa kembalian", 900);

  await caption(page, "6. Tutup float event setelah kegiatan selesai", 800);
  await closePettyCashFloat(page);
  await caption(page, "Selesai: event terikat program, cair → belanja → tutup", 1400);
});
