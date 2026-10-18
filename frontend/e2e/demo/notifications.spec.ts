import { expect, test } from "@playwright/test";

import { loginAs } from "../fixtures/auth";
import { READ_EXCERPT, seedReadAndUnreadNotifications, UNREAD_EXCERPT } from "../flows/notifications";
import { caption } from "./helpers";

test("notif-inbox semua notifikasi dibaca dan belum", async ({ page, request }) => {
  test.setTimeout(3 * 60 * 1000);
  const seeded = await seedReadAndUnreadNotifications(request, "http://127.0.0.1:8110/api");

  await loginAs(page, "verifikator");
  await caption(page, "1. Lonceng menampilkan jumlah yang belum dibaca", 1400);

  await page.getByRole("button", { name: "Notifikasi, 1 belum dibaca" }).click();
  await expect(page.getByRole("button", { name: new RegExp(UNREAD_EXCERPT) })).toBeVisible();
  await expect(page.getByRole("button", { name: "Tandai semua dibaca" })).toBeVisible();
  await caption(page, "2. Pratinjau terbaru. Semua notifikasi ada di halaman inbox", 1600);
  await page.getByRole("link", { name: "Lihat semua" }).click();

  await expect(page.getByRole("heading", { name: "Notifikasi" })).toBeVisible();
  await expect(page.getByRole("button", { name: new RegExp(READ_EXCERPT) })).toBeVisible();
  await caption(page, "3. Tab Semua menampilkan yang sudah dibaca dan yang belum", 1600);

  await page.getByRole("tab", { name: "Belum dibaca" }).click();
  await expect(page.getByRole("button", { name: new RegExp(READ_EXCERPT) })).toHaveCount(0);
  await caption(page, "4. Tab Belum dibaca menyembunyikan yang sudah dibaca", 1500);

  await page.getByRole("button", { name: new RegExp(UNREAD_EXCERPT) }).click();
  await expect(page).toHaveURL(new RegExp(`/dashboard/bantuan/${seeded.unread.grantId}#komentar`));
  await expect(page.getByText(UNREAD_EXCERPT)).toBeVisible();
  await caption(page, "5. Membuka notifikasi menandai dibaca dan menuju komentar", 1600);

  await page.goto("/dashboard/notifikasi");
  await page
    .getByRole("button", {
      name: `Tandai belum dibaca: Petugas Bantuan menyebut Anda di ${seeded.unread.applicationNumber}`,
    })
    .click();
  await page.getByRole("tab", { name: "Belum dibaca" }).click();
  await expect(page.getByRole("button", { name: new RegExp(UNREAD_EXCERPT) })).toBeVisible();
  await caption(page, "6. Notifikasi bisa ditandai belum dibaca lagi", 1500);

  await page.getByRole("tab", { name: "Semua" }).click();
  await page
    .getByRole("button", {
      name: `Hapus notifikasi: Petugas Bantuan menyebut Anda di ${seeded.unread.applicationNumber}`,
    })
    .click();
  await caption(page, "7. Hapus satu notifikasi, atau sekaligus yang sudah dibaca", 1400);
  await page.getByRole("alertdialog").getByRole("button", { name: "Hapus", exact: true }).click();
  await page.getByRole("button", { name: "Hapus yang sudah dibaca", exact: true }).click();
  await page.getByRole("alertdialog").getByRole("button", { name: "Hapus yang sudah dibaca" }).click();
  await expect(page.getByText("Tidak ada notifikasi", { exact: true })).toBeVisible();
  await caption(page, "Inbox kosong setelah notifikasi dihapus", 1600);
});
