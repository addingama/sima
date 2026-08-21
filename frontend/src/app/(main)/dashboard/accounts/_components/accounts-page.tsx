"use client";

import { CrudListPage } from "@/components/sima/crud";
import { accountResource } from "@/lib/resources";

export default function AccountsPage() {
  return (
    <CrudListPage
      config={accountResource}
      description="Kas/Bank = lokasi fisik uang (di mana uang berada). Buat satu rekening per kas tunai atau rekening bank. Saldo di sini dihitung otomatis dari buku besar dan tidak bisa diisi manual di form — saldo awal go-live diisi lewat menu Saldo Awal. Total saldo kas/bank harus bisa direkonsiliasi dengan total Dana Amanah (selisih = 0). Nonaktifkan rekening yang sudah ditutup, jangan hapus jika pernah ada transaksi."
      emptyMessage="Belum ada rekening kas/bank. Tambah rekening sebelum mencatat penerimaan atau pengeluaran."
    />
  );
}
