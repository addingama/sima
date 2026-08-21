"use client";

import { CrudListPage } from "@/components/sima/crud";
import { donorResource } from "@/lib/resources";

export default function DonorsPage() {
  return (
    <CrudListPage
      config={donorResource}
      description="Daftar pihak yang menyumbang dana titipan. Isi email atau nomor HP — akun portal dibuat otomatis (password default). Donatur login dengan email/HP tersebut. Nonaktifkan data yang sudah tidak dipakai, jangan hapus jika pernah ada transaksi."
      emptyMessage="Belum ada data donatur. Tambah donatur sebelum mencatat penerimaan atas nama mereka."
    />
  );
}
