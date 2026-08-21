"use client";

import { CrudListPage } from "@/components/sima/crud";
import { donorResource } from "@/lib/resources";

export default function DonorsPage() {
  return (
    <CrudListPage
      config={donorResource}
      description="Daftar pihak yang menyumbang dana titipan. Simpan nama, kontak, dan tipe (individu/lembaga) agar mudah dipilih saat mencatat penerimaan. Untuk portal: isi field Akun Login Portal (user role donatur). Nonaktifkan data yang sudah tidak dipakai, jangan hapus jika pernah ada transaksi."
      emptyMessage="Belum ada data donatur. Tambah donatur sebelum mencatat penerimaan atas nama mereka."
    />
  );
}
