"use client";

import Link from "next/link";

import { Plus } from "lucide-react";

import { type GrantCard, GrantKanbanBoard } from "@/app/(main)/dashboard/bantuan/_components/grant-kanban-board";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { PageShellSkeleton } from "@/components/sima/skeletons";
import { Button } from "@/components/ui/button";
import { useResourceQuery } from "@/hooks/use-resource-query";
import { hasPermission } from "@/lib/auth/permissions";
import { grantApplicationResource } from "@/lib/resources";
import { useAuth } from "@/providers/auth-provider";

export function GrantKanbanPage() {
  const { user } = useAuth();
  const canCreate = hasPermission(user, "grant.create");
  const { data, isLoading, isError, refetch } = useResourceQuery<GrantCard>("/grant-applications", {
    per_page: 100,
    sort: "created_at",
    direction: "desc",
  });

  if (!hasPermission(user, "grant.view")) {
    return (
      <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk melihat pengajuan bantuan." />
    );
  }

  if (isLoading) {
    return <PageShellSkeleton />;
  }

  if (isError) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Pengajuan Bantuan"
        description="Antrian kasus per penerima. Geser kartu hanya untuk transisi yang diizinkan."
        actions={
          canCreate ? (
            <Button asChild size="sm">
              <Link href={`${grantApplicationResource.basePath}/new`}>
                <Plus className="size-4" />
                Rekomendasi baru
              </Link>
            </Button>
          ) : null
        }
      />
      <GrantKanbanBoard grants={data?.rows ?? []} onMoved={() => refetch()} />
    </div>
  );
}
