import { expect, type Page } from "@playwright/test";

export const roleAccounts = {
  admin: "admin@sima.test",
  asisten_bendahara: "asisten.bendahara@sima.test",
  bendahara: "bendahara@sima.test",
  verifikator: "verifikator@sima.test",
  petugas_bantuan: "petugas.bantuan@sima.test",
  ketua: "ketua@sima.test",
  auditor: "auditor@sima.test",
  donatur: "donatur@sima.test",
} as const;

export type Role = keyof typeof roleAccounts;

export async function loginAs(page: Page, role: Role) {
  await page.goto("/auth/v2/login");
  await page.getByLabel("Email atau nomor HP").fill(roleAccounts[role]);
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Masuk" }).click();
  await expect(page).toHaveURL(/\/dashboard\//);
}
