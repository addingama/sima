"use client";

import { CrudListPage } from "@/components/sima/crud";
import { userResource } from "@/lib/resources";

export default function SettingsUsersPage() {
  return (
    <CrudListPage
      config={userResource}
      description="Kelola akun staff, role, dan status aktif."
      emptyMessage="Belum ada pengguna."
    />
  );
}
