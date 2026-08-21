"use client";

import { CrudListPage } from "@/components/sima/crud";
import { fundResource } from "@/lib/resources";

export default function FundsPage() {
  return (
    <CrudListPage
      config={fundResource}
      description="Dana Amanah = pembatas penggunaan uang (untuk apa dana boleh dipakai), bukan lokasi fisik uang. Pakai tipe restricted untuk donasi berperuntukan (mis. yatim, zakat) dan unrestricted untuk dana umum. Saldo dihitung dari buku besar. Dana sistem (SYS-*) sudah ada dari seed — jangan diubah atau dihapus. Setiap penerimaan harus dialokasikan ke dana; setiap pengeluaran mengambil dari dana yang sesuai."
      emptyMessage="Belum ada Dana Amanah organisasi. Tambah dana sesuai peruntukan sebelum menerima atau mengeluarkan uang."
    />
  );
}
