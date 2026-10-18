import { expect, test } from "@playwright/test";

import { loginAs } from "../fixtures/auth";
import { READ_EXCERPT, seedReadAndUnreadNotifications, UNREAD_EXCERPT } from "../flows/notifications";

test("verifikator melihat semua notifikasi lalu menandai dan menghapus", async ({ page, request }) => {
  test.setTimeout(120_000);
  const seeded = await seedReadAndUnreadNotifications(request, "http://127.0.0.1:8100/api");

  await loginAs(page, "verifikator");
  const bell = page.getByRole("button", { name: "Notifikasi, 1 belum dibaca" });
  await expect(bell).toBeVisible();
  await bell.click();
  await expect(page.getByRole("button", { name: new RegExp(UNREAD_EXCERPT) })).toBeVisible();
  await expect(page.getByRole("button", { name: new RegExp(READ_EXCERPT) })).toBeVisible();
  await expect(page.getByRole("button", { name: "Tandai semua dibaca" })).toBeVisible();
  await page.getByRole("link", { name: "Lihat semua" }).click();

  await expect(page).toHaveURL(/\/dashboard\/notifikasi$/);
  await expect(page.getByRole("heading", { name: "Notifikasi" })).toBeVisible();
  await expect(page.getByRole("button", { name: new RegExp(UNREAD_EXCERPT) })).toBeVisible();
  await expect(page.getByRole("button", { name: new RegExp(READ_EXCERPT) })).toBeVisible();

  await page.getByRole("tab", { name: "Belum dibaca" }).click();
  await expect(page.getByRole("button", { name: new RegExp(UNREAD_EXCERPT) })).toBeVisible();
  await expect(page.getByRole("button", { name: new RegExp(READ_EXCERPT) })).toHaveCount(0);

  await page.getByRole("button", { name: new RegExp(UNREAD_EXCERPT) }).click();
  await expect(page).toHaveURL(new RegExp(`/dashboard/bantuan/${seeded.unread.grantId}#komentar`));
  await expect(page.getByText(UNREAD_EXCERPT)).toBeVisible();

  await page.goto("/dashboard/notifikasi");
  await page.getByRole("tab", { name: "Belum dibaca" }).click();
  await expect(page.getByText("Tidak ada notifikasi belum dibaca")).toBeVisible();

  await page.getByRole("tab", { name: "Semua" }).click();
  const markUnread = page.getByRole("button", {
    name: `Tandai belum dibaca: Petugas Bantuan menyebut Anda di ${seeded.unread.applicationNumber}`,
  });
  await markUnread.click();
  await page.getByRole("tab", { name: "Belum dibaca" }).click();
  await expect(page.getByRole("button", { name: new RegExp(UNREAD_EXCERPT) })).toBeVisible();

  await page.getByRole("tab", { name: "Semua" }).click();
  await page
    .getByRole("button", {
      name: `Hapus notifikasi: Petugas Bantuan menyebut Anda di ${seeded.unread.applicationNumber}`,
    })
    .click();
  await page.getByRole("alertdialog").getByRole("button", { name: "Hapus", exact: true }).click();
  await expect(page.getByRole("button", { name: new RegExp(UNREAD_EXCERPT) })).toHaveCount(0);

  await page.getByRole("button", { name: "Hapus yang sudah dibaca", exact: true }).click();
  await page.getByRole("alertdialog").getByRole("button", { name: "Hapus yang sudah dibaca" }).click();
  await expect(page.getByText("Tidak ada notifikasi", { exact: true })).toBeVisible();
});
