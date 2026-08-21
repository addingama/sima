"use client";

import { CrudListPage } from "@/components/sima/crud";
import { vendorResource } from "@/lib/resources";

export default function VendorsPage() {
  return (
    <CrudListPage
      config={vendorResource}
      description="Daftar penyedia barang/jasa atau penerima pembayaran pengeluaran. Isi kontak dan NPWP bila perlu untuk arsip. Saat membuat pengeluaran, pilih vendor agar nama penerima terisi otomatis dan riwayat pembayaran mudah dilacak. Nonaktifkan vendor yang sudah tidak dipakai."
      emptyMessage="Belum ada data vendor. Tambah vendor jika pengeluaran sering dibayar ke pihak yang sama."
    />
  );
}
