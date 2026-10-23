import { PageHeader } from "@/components/sima/page-header";

import { ChangePasswordForm } from "./_components/change-password-form";

export default function Page() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Ubah password"
        description="Ganti password akun yang sedang login. Sesi di perangkat lain akan berakhir."
      />
      <ChangePasswordForm />
    </div>
  );
}
