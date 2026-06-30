"use client";

import Link from "next/link";

import { Plus } from "lucide-react";

import { ErrorState } from "@/components/sima/error-state";
import { ResourceListPage } from "@/components/sima/resource-list-page";
import { Button } from "@/components/ui/button";
import { hasPermission } from "@/lib/auth/permissions";
import { openingBalanceListColumns } from "@/lib/opening-balance/columns";
import { useAuth } from "@/providers/auth-provider";

export default function OpeningBalancesPage() {
  const { user } = useAuth();
  const canView = hasPermission(user, "opening.view");
  const canManage = hasPermission(user, "opening.manage");

  if (!canView) {
    return <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk melihat saldo awal." />;
  }

  return (
    <ResourceListPage
      title="Saldo Awal"
      description="Daftar batch posting saldo awal go-live. Koreksi hanya lewat reversal terkontrol."
      resource="/opening-balances"
      columns={openingBalanceListColumns}
      emptyMessage="Belum ada posting saldo awal."
      actions={
        canManage ? (
          <Button asChild>
            <Link href="/dashboard/opening-balances/new">
              <Plus className="size-4" />
              Posting Saldo Awal
            </Link>
          </Button>
        ) : null
      }
    />
  );
}
