"use client";

import { useEffect, useState } from "react";

import Link from "next/link";

import { Plus, Search } from "lucide-react";

import {
  ACTIVE_COLUMNS,
  ARCHIVE_COLUMNS,
  type GrantBoardMode,
  type GrantCard,
  GrantKanbanBoard,
} from "@/app/(main)/dashboard/bantuan/_components/grant-kanban-board";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { PageShellSkeleton } from "@/components/sima/skeletons";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useResourceQuery } from "@/hooks/use-resource-query";
import { hasPermission } from "@/lib/auth/permissions";
import { grantApplicationResource } from "@/lib/resources";
import { useAuth } from "@/providers/auth-provider";

const ACTIVE_STATUSES = ACTIVE_COLUMNS.map((column) => column.id).join(",");
const ARCHIVE_STATUSES = ARCHIVE_COLUMNS.map((column) => column.id).join(",");

export function GrantKanbanPage() {
  const { user } = useAuth();
  const canCreate = hasPermission(user, "grant.create");
  const [board, setBoard] = useState<GrantBoardMode>("active");
  const [query, setQuery] = useState("");
  const [debouncedQuery, setDebouncedQuery] = useState("");
  const [assignedToMe, setAssignedToMe] = useState(false);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedQuery(query.trim()), 300);

    return () => window.clearTimeout(timer);
  }, [query]);

  const { data, isLoading, isError, refetch } = useResourceQuery<GrantCard>("/grant-applications", {
    per_page: 100,
    sort: "created_at",
    direction: "desc",
    status: board === "active" ? ACTIVE_STATUSES : ARCHIVE_STATUSES,
    q: debouncedQuery || undefined,
    assigned_verifier_id: assignedToMe ? user?.id : undefined,
  });

  if (!hasPermission(user, "grant.view")) {
    return (
      <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk melihat pengajuan bantuan." />
    );
  }

  if (isLoading && !data) {
    return <PageShellSkeleton />;
  }

  if (isError) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  const total = data?.pagination?.total ?? data?.rows.length ?? 0;
  const shown = data?.rows.length ?? 0;

  return (
    <div className="flex flex-col gap-4">
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

      <div className="flex flex-wrap items-center gap-3">
        <Tabs value={board} onValueChange={(value) => setBoard(value as GrantBoardMode)}>
          <TabsList>
            <TabsTrigger value="active">Antrian</TabsTrigger>
            <TabsTrigger value="archive">Arsip</TabsTrigger>
          </TabsList>
        </Tabs>
        <div className="relative min-w-48 max-w-xs flex-1">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Cari nama atau nomor"
            className="pl-8"
            aria-label="Cari pengajuan"
          />
        </div>
        <Label className="font-normal text-muted-foreground">
          <Checkbox checked={assignedToMe} onCheckedChange={(checked) => setAssignedToMe(checked === true)} />
          Yang saya verifikasi
        </Label>
      </div>

      <GrantKanbanBoard grants={data?.rows ?? []} mode={board} onMoved={() => refetch()} />

      {total > shown ? (
        <p className="text-muted-foreground text-xs">
          Menampilkan {shown} dari {total} kartu. Saring nama/nomor atau buka tab lain untuk melihat sisanya.
        </p>
      ) : null}
    </div>
  );
}
