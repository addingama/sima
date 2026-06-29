"use client";

import { CrudListPage } from "@/components/sima/crud";
import { fundResource } from "@/lib/resources";

export default function FundsPage() {
  return (
    <CrudListPage
      config={fundResource}
      description="Kelola Dana Amanah dan pembatas penggunaan dana."
      emptyMessage="Belum ada data dana amanah."
    />
  );
}
