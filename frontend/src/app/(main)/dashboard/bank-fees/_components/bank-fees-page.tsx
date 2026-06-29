"use client";

import { CrudListPage } from "@/components/sima/crud";
import { bankFeeResource } from "@/lib/resources";

export default function BankFeesPage() {
  return (
    <CrudListPage
      config={bankFeeResource}
      description="Catat biaya administrasi bank, lalu post ke buku besar Amanah Ledger."
      emptyMessage="Belum ada data biaya bank."
    />
  );
}
