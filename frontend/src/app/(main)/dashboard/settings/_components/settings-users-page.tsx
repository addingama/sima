"use client";

import { CrudListPage } from "@/components/sima/crud";
import { userResource } from "@/lib/resources";

export default function SettingsUsersPage() {
  return (
    <CrudListPage
      config={userResource}
      description="Kelola akun staff & portal. Untuk role Donatur, isi field Tautkan Donatur agar akun bisa masuk Portal Donatur. Alternatif: tautkan dari Master Data → Donatur → Akun Login Portal."
      emptyMessage="Belum ada pengguna."
    />
  );
}
