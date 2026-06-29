"use client";

import { CrudListPage } from "@/components/sima/crud";
import { programResource } from "@/lib/resources";

export default function ProgramsPage() {
  return (
    <CrudListPage
      config={programResource}
      description="Kelola event dan program kegiatan."
      emptyMessage="Belum ada data program."
    />
  );
}
