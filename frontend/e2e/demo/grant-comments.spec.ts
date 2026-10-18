import { type APIRequestContext, expect, test } from "@playwright/test";

import { roleAccounts } from "../fixtures/auth";
import { caption, switchRole } from "./helpers";

const RECIPIENT = "Rina Marlina";
const API_URL = "http://127.0.0.1:8110/api";

async function seedDraftGrant(request: APIRequestContext): Promise<number> {
  const login = await request.post(`${API_URL}/login`, {
    data: { login: roleAccounts.petugas_bantuan, password: "password", device_name: "playwright-comments" },
  });
  expect(login.ok(), await login.text()).toBeTruthy();
  const token = (await login.json()).data.token as string;

  const created = await request.post(`${API_URL}/grant-applications`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    data: {
      beneficiary_type: "individual",
      beneficiary_scope: "individual",
      recipient_name: RECIPIENT,
      recipient_address: "Kp. Cijulang RT 01/02, Desa Cibadak",
      recommended_amount: "200000.00",
      reason: "Bantuan beras dan kebutuhan dapur.",
      recommender_name: "Ibu RT",
    },
  });
  expect(created.ok(), await created.text()).toBeTruthy();

  return (await created.json()).data.id as number;
}

test("bnt-komentar mention dan lonceng notifikasi", async ({ page, request }) => {
  test.setTimeout(10 * 60 * 1000);
  const grantId = await seedDraftGrant(request);

  await page.goto("/auth/v2/login");
  await page.getByLabel("Email atau nomor HP").fill(roleAccounts.petugas_bantuan);
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Masuk" }).click();
  await expect(page).toHaveURL(/\/dashboard\//);

  await page.goto(`/dashboard/bantuan/${grantId}#komentar`);
  await expect(page.getByRole("heading", { name: /BNT\// })).toBeVisible();
  const composer = page.getByRole("textbox", { name: "Tulis komentar" });
  await composer.scrollIntoViewIfNeeded();
  await caption(page, "1. Komentar ada di detail kartu, di status apa pun", 1400);

  await composer.click();
  await composer.pressSequentially("@Ver", { delay: 110 });
  const mention = page.getByRole("option", { name: "Verifikator", exact: true });
  await expect(mention).toBeVisible();
  await caption(page, "2. Ketik @ lalu pilih rekan. Yang disebut mendapat akses lihat kartu", 1600);
  await mention.click();
  await expect(page.getByRole("listbox", { name: "Pengguna yang bisa disebut" })).toBeHidden();
  await composer.pressSequentially("mohon dicek alamat penerima.", { delay: 45 });
  await caption(page, "3. Kirim komentar. Nama tampil dari data pengguna", 1200);
  await page.getByRole("button", { name: "Kirim", exact: true }).click();

  await expect(page.getByText("Bisa melihat lewat mention: Verifikator")).toBeVisible();
  await expect(page.getByText("@Verifikator")).toBeVisible();
  await expect(page.getByText("mohon dicek alamat penerima.")).toBeVisible();
  await caption(page, "Akses lihat ini permanen, tanpa hak mengubah data kartu", 1600);

  await page.getByRole("button", { name: "Notifikasi", exact: true }).click();
  await expect(page.getByText("Tidak ada notifikasi")).toBeVisible();
  await caption(page, "4. Penulis tidak mendapat notifikasi atas komentarnya sendiri", 1600);

  await page.goto("/dashboard/bantuan");
  const card = page.getByRole("link", { name: new RegExp(RECIPIENT) });
  await expect(card).toBeVisible();
  await card.scrollIntoViewIfNeeded();
  await expect(card).toContainText("1 komentar");
  await caption(page, "5. Papan Kanban hanya menampilkan jumlah komentar", 1600);

  await switchRole(page, "verifikator");
  const bell = page.getByRole("button", { name: /Notifikasi, 1 belum dibaca/ });
  await expect(bell).toBeVisible();
  await caption(page, "6. Yang disebut mendapat lonceng khusus", 1200);
  await bell.click();
  const notice = page.getByRole("button", { name: /menyebut Anda di BNT\// });
  await expect(notice).toBeVisible();
  await caption(page, "7. Klik notifikasi membuka komentar di kartu itu", 1400);
  await notice.click();

  await expect(page).toHaveURL(new RegExp(`/dashboard/bantuan/${grantId}#komentar`));
  await expect(page.getByText("mohon dicek alamat penerima.")).toBeVisible();
  await expect(page.getByText("Bisa melihat lewat mention: Verifikator")).toBeVisible();
  await composer.scrollIntoViewIfNeeded();
  await caption(page, "Verifikator bisa membaca kartu karena disebut", 1800);
});
