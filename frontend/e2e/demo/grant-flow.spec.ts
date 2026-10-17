import { expect, test } from "@playwright/test";

import { loginAs } from "../fixtures/auth";
import {
  approveGrant,
  caption,
  confirmAction,
  createAndApproveLinkedDisbursement,
  openGrantFromBoard,
  switchRole,
  uploadHandoverPhoto,
} from "./helpers";

test("alur pengajuan bantuan dari rekomendasi sampai selesai", async ({ page }) => {
  test.setTimeout(10 * 60 * 1000);
  const recipient = "Siti Aminah";

  await loginAs(page, "petugas_bantuan");
  await caption(page, "1. Petugas bantuan masuk dan membuka Kanban");
  await page.goto("/dashboard/bantuan");
  await expect(page.getByRole("heading", { name: "Pengajuan Bantuan" })).toBeVisible();
  await caption(page, "2. Buat rekomendasi penerima perorangan", 1200);

  await page.getByRole("link", { name: "Rekomendasi baru" }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/new$/);

  await page.getByLabel(/Nama penerima/).pressSequentially(recipient, { delay: 45 });
  await page.getByLabel(/Telepon penerima/).fill("081234567890");
  await page.getByLabel(/Alamat \/ cara menemui/).fill("Kp. Sukamaju RT 02/03, Desa Cibadak");
  await page.getByLabel(/NIK \/ nomor identitas/).fill("3201015501800001");
  await page.locator("#recommended_amount").fill("750000");
  await page.locator("#reason").fill("Santunan sakit untuk biaya berobat dan kebutuhan harian keluarga.");
  await page.locator("#recommender_name").fill("Ketua RT 02");
  await caption(page, "3. Simpan rekomendasi — petugas otomatis jadi verifikator", 900);
  await page.getByRole("button", { name: "Simpan" }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/\d+$/);
  await expect(page.getByText(recipient, { exact: true }).first()).toBeVisible();

  const applicationNumber = (await page.getByRole("heading").filter({ hasText: /BNT\// }).innerText()).trim();

  await page.goto("/dashboard/bantuan");
  await expect(page.getByRole("link", { name: recipient })).toBeVisible();
  await caption(page, "4. Kartu muncul di kolom Rekomendasi", 1400);
  await page.getByRole("link", { name: recipient }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/\d+$/);

  await caption(page, "5. Kirim ke verifikasi", 900);
  await confirmAction(page, "Kirim ke verifikasi");
  await expect(page.getByText("Kirim ke verifikasi berhasil.")).toBeVisible();
  await expect(page.getByText("Verifikasi", { exact: true }).first()).toBeVisible();

  await caption(page, "6. Verifikator assigned melengkapi nominal dan catatan", 1000);
  await page.getByRole("link", { name: "Edit" }).click();
  await expect(page).toHaveURL(/\/edit$/);
  await page.locator("#verified_amount").fill("750000");
  await page.getByLabel(/Catatan verifikator/).fill("Data penerima sesuai. Layak dibantu tunai.");
  await page.getByRole("button", { name: "Simpan" }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/\d+$/);

  await caption(page, "7. Ajukan ke approval ketua", 900);
  await confirmAction(page, "Ajukan ke approval");
  await expect(page.getByText("Ajukan ke approval berhasil.")).toBeVisible();
  await expect(page.getByText("Menunggu approval", { exact: true }).first()).toBeVisible();

  await caption(page, "8. Ganti akun ke Ketua", 900);
  await switchRole(page, "ketua");
  await openGrantFromBoard(page, recipient);
  await caption(page, "9. Ketua menyetujui pengajuan perorangan", 900);
  await approveGrant(page, "Disetujui. Prioritas keluarga dhuafa.");

  await caption(page, "10. Bendahara membuat dan menyetujui pengeluaran tertaut", 900);
  await switchRole(page, "bendahara");
  await openGrantFromBoard(page, recipient);
  await createAndApproveLinkedDisbursement(page, applicationNumber);

  await caption(page, "11. Petugas unggah foto serah terima dan menandai selesai", 1000);
  await switchRole(page, "petugas_bantuan");
  await openGrantFromBoard(page, recipient);
  await uploadHandoverPhoto(page);
  await page.getByRole("button", { name: "Tandai selesai" }).click();
  await expect(page.getByText("Pengajuan ditandai selesai.")).toBeVisible();
  await expect(page.getByText("Selesai", { exact: true }).first()).toBeVisible();

  await page.goto("/dashboard/bantuan");
  await page.getByRole("tab", { name: "Arsip" }).click();
  await expect(page.getByRole("link", { name: recipient })).toBeVisible();
  await caption(page, "12. Kartu pindah ke Arsip — Selesai", 1600);

  await caption(page, "13. Ketua membuka laporan bantuan organisasi", 900);
  await switchRole(page, "ketua");
  await page.goto("/dashboard/reports/bantuan");
  await expect(page.getByRole("heading", { name: "Pengajuan Bantuan" })).toBeVisible();
  await page.getByLabel("Status").selectOption("completed");
  await page.getByLabel(/Cari penerima/).fill(recipient);
  await page.getByRole("button", { name: "Terapkan Filter" }).click();
  await expect(page.getByText(recipient).first()).toBeVisible();
  await expect(page.getByRole("cell", { name: "Selesai" })).toBeVisible();
  await caption(page, "Selesai: rekomendasi perorangan sampai serah terima", 2200);
});

test("alur pengajuan bantuan instansi kolektif air bersih sampai selesai", async ({ page }) => {
  test.setTimeout(10 * 60 * 1000);
  const recipient = "Kelompok Air Bersih Desa Cibadak";

  await loginAs(page, "petugas_bantuan");
  await caption(page, "1. Bantuan instansi/kelompok: air bersih untuk warga desa");
  await page.goto("/dashboard/bantuan");
  await expect(page.getByRole("heading", { name: "Pengajuan Bantuan" })).toBeVisible();

  await page.getByRole("link", { name: "Rekomendasi baru" }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/new$/);

  await caption(page, "2. Cakupan kolektif + penerima organisasi/instansi", 1100);
  await page.locator("#beneficiary_scope").selectOption("collective");
  await expect(page.locator("#target_beneficiary_count")).toBeVisible();
  await page.locator("#target_beneficiary_count").fill("120");
  await page.locator("#beneficiary_count_method").selectOption("estimated");
  await page.locator("#beneficiary_location").fill("Desa Cibadak, Kecamatan Cikembar");
  await page.locator("#beneficiary_count_notes").fill("Estimasi 120 jiwa dari 32 KK data desa.");

  await page.locator("#beneficiary_type").selectOption("organization");
  await expect(page.getByLabel(/Nama PIC organisasi/)).toBeVisible();
  await page.getByLabel(/Nama penerima/).pressSequentially(recipient, { delay: 35 });
  await page.getByLabel(/Telepon penerima/).fill("0266123456");
  await page.getByLabel(/Alamat \/ cara menemui/).fill("Kantor Desa Cibadak, samping balai warga");
  await page.getByLabel(/Nama PIC organisasi/).fill("H. Sutisna");
  await page.getByLabel(/Kontak PIC/).fill("081298765432");
  await page.getByLabel(/Hubungan PIC/).fill("Kepala Desa / penanggung jawab distribusi");
  await page.locator("#recommended_amount").fill("3500000");
  await page
    .locator("#reason")
    .fill("Bantuan air bersih: tandon, pipa, dan filter untuk 32 KK yang masih mengandalkan sumber jauh.");
  await page.locator("#recommender_name").fill("Camat Cikembar");
  await caption(page, "3. Simpan rekomendasi kelompok air bersih", 900);
  await page.getByRole("button", { name: "Simpan" }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/\d+$/);
  await expect(page.getByText(recipient, { exact: true }).first()).toBeVisible();

  const applicationNumber = (await page.getByRole("heading").filter({ hasText: /BNT\// }).innerText()).trim();

  await page.goto("/dashboard/bantuan");
  await expect(page.getByRole("link", { name: recipient })).toBeVisible();
  await caption(page, "4. Kartu kelompok muncul di Rekomendasi", 1400);
  await page.getByRole("link", { name: recipient }).click();

  await caption(page, "5. Kirim ke verifikasi lapangan", 900);
  await confirmAction(page, "Kirim ke verifikasi");
  await expect(page.getByText("Kirim ke verifikasi berhasil.")).toBeVisible();

  await caption(page, "6. Verifikasi jumlah warga dan kelayakan lokasi", 1000);
  await page.getByRole("link", { name: "Edit" }).click();
  await expect(page).toHaveURL(/\/edit$/);
  await page.locator("#verified_amount").fill("3500000");
  await page
    .getByLabel(/Catatan verifikator/)
    .fill("Lokasi dan 32 KK terverifikasi. Air bersih layak didanai dari Dana Sosial Umum.");
  await page.getByRole("button", { name: "Simpan" }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/\d+$/);

  await caption(page, "7. Ajukan ke approval ketua", 900);
  await confirmAction(page, "Ajukan ke approval");
  await expect(page.getByText("Menunggu approval", { exact: true }).first()).toBeVisible();

  await caption(page, "8. Ketua menyetujui bantuan air bersih desa", 900);
  await switchRole(page, "ketua");
  await openGrantFromBoard(page, recipient);
  await approveGrant(page, "Disetujui. Manfaat kolektif, satu pengeluaran atas nama kelompok desa.");

  await caption(page, "9. Bendahara membuat satu pengeluaran untuk instansi", 900);
  await switchRole(page, "bendahara");
  await openGrantFromBoard(page, recipient);
  await createAndApproveLinkedDisbursement(page, applicationNumber);

  await caption(page, "10. Serah terima ke Kepala Desa + realisasi orang terbantu", 1000);
  await switchRole(page, "petugas_bantuan");
  await openGrantFromBoard(page, recipient);
  await uploadHandoverPhoto(page);
  await page.getByLabel("Penerima aktual").fill("H. Sutisna");
  await page.getByLabel("Realisasi orang terbantu").fill("118");
  await page.getByRole("button", { name: "Tandai selesai" }).click();
  await expect(page.getByText("Pengajuan ditandai selesai.")).toBeVisible();
  await expect(page.getByText("Selesai", { exact: true }).first()).toBeVisible();

  await page.goto("/dashboard/bantuan");
  await page.getByRole("tab", { name: "Arsip" }).click();
  await expect(page.getByRole("link", { name: recipient })).toBeVisible();
  await caption(page, "11. Arsip: bantuan air bersih desa selesai", 1600);

  await switchRole(page, "ketua");
  await page.goto("/dashboard/reports/bantuan");
  await expect(page.getByRole("heading", { name: "Pengajuan Bantuan" })).toBeVisible();
  await page.getByLabel("Cakupan").selectOption("collective");
  await page.getByLabel("Jenis Penerima").selectOption("organization");
  await page.getByLabel("Status").selectOption("completed");
  await page.getByLabel(/Cari penerima/).fill(recipient);
  await page.getByRole("button", { name: "Terapkan Filter" }).click();
  await expect(page.getByText(recipient).first()).toBeVisible();
  await expect(page.getByRole("cell", { name: "Selesai" })).toBeVisible();
  await expect(page.getByText("118").first()).toBeVisible();
  await caption(page, "Selesai: instansi/kelompok air bersih sampai realisasi 118 orang", 2200);
});
