"use client";

import { CrudListPage } from "@/components/sima/crud";
import { receiptResource } from "@/lib/resources";

export default function ReceiptsPage() {
  return (
    <CrudListPage
      config={receiptResource}
      description="Daftar penerimaan dana titipan."
      emptyMessage="Belum ada data penerimaan."
    />
  );
}
