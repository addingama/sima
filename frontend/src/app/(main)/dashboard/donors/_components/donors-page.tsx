"use client";

import { CrudListPage } from "@/components/sima/crud";
import { donorResource } from "@/lib/resources";

export default function DonorsPage() {
  return (
    <CrudListPage
      config={donorResource}
      description="Daftar pihak yang menyumbang dana titipan. Simpan nama, kontak, dan tipe (individu/lembaga) agar mudah dipilih saat mencatat penerimaan. Donatur dapat ditautkan ke akun login portal untuk melihat riwayat donasi mereka sendiri. Nonaktifkan data yang sudah tidak dipakai, jangan hapus jika pernah ada transaksi."
      emptyMessage="Belum ada data donatur. Tambah donatur sebelum mencatat penerimaan atas nama mereka."
    />
  );
}
