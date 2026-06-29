"use client";

import { CrudListPage } from "@/components/sima/crud";
import { donorResource } from "@/lib/resources";

export default function DonorsPage() {
  return (
    <CrudListPage
      config={donorResource}
      description="Kelola data donatur dan informasi kontak."
      emptyMessage="Belum ada data donatur."
    />
  );
}
