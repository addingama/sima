import { PageHeader } from "@/components/sima/page-header";

export default function Page() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Portal Donatur"
        description="Ringkasan donasi dan profil untuk peran donatur."
      />
      <p className="text-muted-foreground text-sm">Modul ini akan segera tersedia.</p>
    </div>
  );
}
