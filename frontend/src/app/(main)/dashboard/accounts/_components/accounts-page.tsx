"use client";

import { CrudListPage } from "@/components/sima/crud";
import { accountResource } from "@/lib/resources";

export default function AccountsPage() {
  return (
    <CrudListPage
      config={accountResource}
      description="Kelola rekening kas dan bank."
      emptyMessage="Belum ada data kas/bank."
    />
  );
}
