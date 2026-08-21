"use client";

import { CrudListPage } from "@/components/sima/crud";
import { programResource } from "@/lib/resources";

export default function ProgramsPage() {
  return (
    <CrudListPage
      config={programResource}
      description="Event/program kegiatan yang bisa dikaitkan ke pengeluaran (dan opsional ke Dana Amanah tertentu). Gunakan untuk melacak anggaran dan realisasi per kegiatan, misalnya bakti sosial atau penggalangan dana. Status planned → active → closed membantu membedakan program yang masih berjalan. Ini bukan pengganti Dana Amanah — dana tetap menentukan batas penggunaan uang."
      emptyMessage="Belum ada event/program. Tambah program jika ingin melacak pengeluaran per kegiatan."
    />
  );
}
