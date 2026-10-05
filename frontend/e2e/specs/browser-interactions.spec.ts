import { expect, test } from "@playwright/test";

import { loginAs } from "../fixtures/auth";

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
