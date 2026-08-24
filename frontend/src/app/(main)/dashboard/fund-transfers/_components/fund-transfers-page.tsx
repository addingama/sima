"use client";

import { CrudListPage } from "@/components/sima/crud";
import { fundTransferResource } from "@/lib/resources";

export default function FundTransfersPage() {
  return (
    <CrudListPage
      config={fundTransferResource}
      description="Pindahkan saldo antar Dana Amanah tanpa mengubah saldo kas/bank."
      emptyMessage="Belum ada data transfer Dana Amanah."
    />
  );
}
