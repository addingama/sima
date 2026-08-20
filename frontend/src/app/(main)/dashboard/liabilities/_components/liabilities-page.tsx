"use client";

import Link from "next/link";

import { Plus } from "lucide-react";

import { ErrorState } from "@/components/sima/error-state";
import { ResourceListPage } from "@/components/sima/resource-list-page";
import { Button } from "@/components/ui/button";
import { hasPermission } from "@/lib/auth/permissions";
import { liabilityListColumns } from "@/lib/liability/columns";
import { useAuth } from "@/providers/auth-provider";

export default function LiabilitiesPage() {
  const { user } = useAuth();
  const canView = hasPermission(user, "liability.view");
  const canManage = hasPermission(user, "liability.manage");

  if (!canView) {
    return (
      <ErrorState
        title="Akses ditolak"
        description="Anda tidak memiliki permission untuk melihat liabilitas operasional."
      />
    );
  }

  return (
    <ResourceListPage
      title="Liabilitas Operasional"
      description="Register kewajiban operasional. Arus kas terjadi saat diselesaikan lewat pengeluaran approved."
      resource="/liabilities"
      columns={liabilityListColumns}
      emptyMessage="Belum ada data liabilitas."
      actions={
        canManage ? (
          <Button asChild>
            <Link href="/dashboard/liabilities/new">
              <Plus className="size-4" />
              Tambah Liabilitas
            </Link>
          </Button>
        ) : null
      }
    />
  );
}
