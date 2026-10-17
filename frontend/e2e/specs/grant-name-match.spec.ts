import { expect, test } from "@playwright/test";

import { loginAs } from "../fixtures/auth";
import { NEXT_REASON, PRIOR_REASON, SAME_NAME_RECIPIENT, seedCompletedSameNameGrant } from "../flows/grant-name-match";

test("petugas melihat flag saat menginput nama penerima yang sama", async ({ page, request }) => {
  test.setTimeout(120_000);
  const prior = await seedCompletedSameNameGrant(request, "http://127.0.0.1:8100/api");

  await loginAs(page, "petugas_bantuan");
  await page.goto("/dashboard/bantuan/new");
  await page.getByLabel(/Nama penerima/).fill(SAME_NAME_RECIPIENT);
  await page.getByLabel(/Alamat \/ cara menemui/).fill("Kp. Sukamaju RT 02/03, Desa Cibadak");
  await page.getByLabel(/NIK \/ nomor identitas/).fill("3201015501800001");
  await page.locator("#recommended_amount").fill("150000");
  await page.locator("#reason").fill(NEXT_REASON);
  await page.locator("#recommender_name").fill("Ketua RT 02");
  await page.getByRole("button", { name: "Simpan" }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/\d+$/);

  const banner = page.getByRole("alert").filter({ hasText: "Nama penerima sama" });
  await expect(banner).toBeVisible();
  await expect(banner).toContainText("Sudah dibantu 1 kali.");
  await expect(banner).toContainText(PRIOR_REASON);
  await expect(banner).toContainText("NIK sama");
  await expect(banner.getByRole("link", { name: prior.applicationNumber })).toBeVisible();

  await page.goto("/dashboard/bantuan");
  const card = page.getByRole("link", { name: SAME_NAME_RECIPIENT });
  await expect(card.getByText("Nama sama")).toBeVisible();
  await expect(card.getByText("Sudah dibantu 1 kali")).toBeVisible();
});
