"use client";

import { CrudListPage } from "@/components/sima/crud";
import { disbursementResource } from "@/lib/resources";

export default function DisbursementsPage() {
  return (
    <CrudListPage
      config={disbursementResource}
      description="Daftar pengeluaran dana amanah."
      emptyMessage="Belum ada data pengeluaran."
    />
  );
}
