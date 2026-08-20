"use client";

import Link from "next/link";

import { Plus } from "lucide-react";

import { ErrorState } from "@/components/sima/error-state";
import { ResourceListPage } from "@/components/sima/resource-list-page";
import { Button } from "@/components/ui/button";
import { hasPermission } from "@/lib/auth/permissions";
import { reconciliationListColumns } from "@/lib/reconciliation/columns";
import { useAuth } from "@/providers/auth-provider";

export default function ReconciliationsPage() {
  const { user } = useAuth();
  const canView = hasPermission(user, "reconciliation.view");
  const canManage = hasPermission(user, "reconciliation.manage");

  if (!canView) {
    return (
      <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk melihat rekonsiliasi bank." />
    );
  }

  return (
    <ResourceListPage
      title="Rekonsiliasi Bank"
      description="Cocokkan saldo rekening koran dengan saldo sistem. Rekonsiliasi tidak mengubah ledger."
      resource="/bank-reconciliations"
      columns={reconciliationListColumns}
      emptyMessage="Belum ada data rekonsiliasi."
      actions={
        canManage ? (
          <Button asChild>
            <Link href="/dashboard/reconciliations/new">
              <Plus className="size-4" />
              Buat Rekonsiliasi
            </Link>
          </Button>
        ) : null
      }
    />
  );
}
