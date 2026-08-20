"use client";

import { CrudListPage } from "@/components/sima/crud";
import { accountTransferResource } from "@/lib/resources";

export default function TransfersPage() {
  return (
    <CrudListPage
      config={accountTransferResource}
      description="Pindahkan saldo antar rekening kas/bank. Tidak mengubah Dana Amanah."
      emptyMessage="Belum ada data transfer."
    />
  );
}
