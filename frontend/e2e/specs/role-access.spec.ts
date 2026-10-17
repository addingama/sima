import { expect, test } from "@playwright/test";

import { loginAs, type Role } from "../fixtures/auth";

const expectations: Record<Role, { visible: string[]; hidden: string[] }> = {
  admin: { visible: ["Master Data", "Keuangan", "Pengajuan Bantuan", "Laporan", "Pengaturan"], hidden: [] },
  asisten_bendahara: { visible: ["Master Data", "Keuangan", "Pengajuan Bantuan", "Laporan"], hidden: ["Pengaturan"] },
  bendahara: { visible: ["Master Data", "Keuangan", "Pengajuan Bantuan", "Laporan"], hidden: ["Pengaturan"] },
  verifikator: {
    visible: ["Master Data", "Keuangan", "Pengajuan Bantuan", "Approval", "Laporan"],
    hidden: ["Pengaturan"],
  },
  petugas_bantuan: {
    visible: ["Dashboard", "Pengajuan Bantuan"],
    hidden: ["Master Data", "Keuangan", "Laporan", "Pengaturan"],
  },
  ketua: { visible: ["Master Data", "Keuangan", "Pengajuan Bantuan", "Approval", "Laporan"], hidden: ["Pengaturan"] },
  auditor: {
    visible: ["Master Data", "Keuangan", "Pengajuan Bantuan", "Laporan", "Audit Trail"],
    hidden: ["Pengaturan"],
  },
  donatur: {
    visible: ["Portal Donatur"],
    hidden: ["Master Data", "Keuangan", "Pengajuan Bantuan", "Laporan"],
  },
};

for (const [role, navigation] of Object.entries(expectations) as [Role, (typeof expectations)[Role]][]) {
  test(`${role} hanya melihat navigasi yang sesuai`, async ({ page }) => {
    await loginAs(page, role);

    for (const label of navigation.visible) {
      await expect(page.getByText(label, { exact: true }).first()).toBeVisible();
    }
    for (const label of navigation.hidden) {
      await expect(page.getByText(label, { exact: true })).toHaveCount(0);
    }
  });
}

test("halaman dashboard memerlukan autentikasi dan logout membersihkan sesi", async ({ page }) => {
  await page.goto("/dashboard/default");
  await expect(page).toHaveURL(/auth\/v2\/login/);

  await loginAs(page, "bendahara");
  await page.locator('[data-slot="dropdown-menu-trigger"]').click();
  await page.getByText("Keluar", { exact: true }).click();
  await expect(page).toHaveURL(/auth\/v2\/login/);
});
