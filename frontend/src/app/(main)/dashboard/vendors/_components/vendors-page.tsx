"use client";

import { CrudListPage } from "@/components/sima/crud";
import { vendorResource } from "@/lib/resources";

export default function VendorsPage() {
  return (
    <CrudListPage
      config={vendorResource}
      description="Kelola data vendor dan penerima pembayaran pengeluaran."
      emptyMessage="Belum ada data vendor."
    />
  );
}
