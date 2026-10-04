"use client";

import Link from "next/link";

import { CheckCircle2, ClipboardList, HandHeart, Plus, SearchCheck } from "lucide-react";

import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { DashboardSkeleton } from "@/components/sima/skeletons";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { useResourceQuery } from "@/hooks/use-resource-query";
import { useAuth } from "@/providers/auth-provider";

export interface GrantDashboardRow {
  id: number;
  application_number: string;
  recipient_name: string;
  status: string;
  status_label?: string;
  assigned_verifier_id?: number | null;
  assigned_handover_id?: number | null;
  updated_at?: string;
}

interface GrantOfficerSummary {
  recommendation: number;
  verification: number;
  handover: number;
  completed: number;
}

export function summarizeGrantOfficerWork(rows: GrantDashboardRow[], userId: number | undefined): GrantOfficerSummary {
  return rows.reduce<GrantOfficerSummary>(
    (summary, row) => {
      if (row.status === "draft") summary.recommendation += 1;
      if (row.status === "verification" && row.assigned_verifier_id === userId) summary.verification += 1;
      if (row.status === "approved" && row.assigned_handover_id === userId) summary.handover += 1;
      if (row.status === "completed") summary.completed += 1;

      return summary;
    },
    { recommendation: 0, verification: 0, handover: 0, completed: 0 },
  );
}

const statusClasses: Record<string, string> = {
  draft: "bg-slate-100 text-slate-700",
  verification: "bg-blue-100 text-blue-700",
  pending_approval: "bg-amber-100 text-amber-700",
  approved: "bg-emerald-100 text-emerald-700",
  completed: "bg-teal-100 text-teal-700",
};

export function GrantOfficerDashboard() {
  const { user } = useAuth();
  const { data, isLoading, isError, refetch } = useResourceQuery<GrantDashboardRow>("/grant-applications", {
    mine: true,
    per_page: 100,
    sort: "created_at",
    direction: "desc",
  });

  if (isLoading && !data) {
    return <DashboardSkeleton />;
  }

  if (isError) {
    return <ErrorState title="Dashboard bantuan belum dapat dimuat" onRetry={() => refetch()} />;
  }

  const rows = data?.rows ?? [];
  const summary = summarizeGrantOfficerWork(rows, user?.id);
  const recent = rows.filter((row) => row.status !== "rejected").slice(0, 5);
  const metrics = [
    {
      label: "Rekomendasi",
      value: summary.recommendation,
      description: "Draft yang perlu dilengkapi",
      icon: ClipboardList,
    },
    {
      label: "Perlu diverifikasi",
      value: summary.verification,
      description: "Ditugaskan kepada saya",
      icon: SearchCheck,
    },
    { label: "Siap diserahkan", value: summary.handover, description: "Menunggu serah terima saya", icon: HandHeart },
    { label: "Selesai", value: summary.completed, description: "Kasus yang sudah ditangani", icon: CheckCircle2 },
  ];

  return (
    <div className="@container/main flex flex-col gap-4 md:gap-6">
      <PageHeader
        title={`Selamat datang, ${user?.name ?? "Petugas Bantuan"}`}
        description="Pantau rekomendasi, verifikasi lapangan, dan serah terima bantuan yang menjadi tanggung jawab Anda."
        actions={
          <Button asChild size="sm">
            <Link href="/dashboard/bantuan/new">
              <Plus className="size-4" />
              Ajukan bantuan
            </Link>
          </Button>
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {metrics.map((metric) => (
          <Card key={metric.label}>
            <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
              <div>
                <CardDescription>{metric.label}</CardDescription>
                <CardTitle className="mt-1 text-3xl">{metric.value}</CardTitle>
              </div>
              <metric.icon className="size-5 text-muted-foreground" />
            </CardHeader>
            <CardContent className="text-muted-foreground text-xs">{metric.description}</CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>Kasus terbaru</CardTitle>
            <CardDescription>Pengajuan yang Anda buat, verifikasi, atau serahkan.</CardDescription>
          </div>
          <Button asChild variant="outline" size="sm">
            <Link href="/dashboard/bantuan">Buka papan bantuan</Link>
          </Button>
        </CardHeader>
        <CardContent>
          {recent.length === 0 ? (
            <div className="rounded-lg border border-dashed p-6 text-center text-muted-foreground text-sm">
              Belum ada kasus bantuan. Gunakan tombol Ajukan bantuan untuk membuat rekomendasi pertama.
            </div>
          ) : (
            <div className="divide-y rounded-lg border">
              {recent.map((row) => (
                <Link
                  key={row.id}
                  href={`/dashboard/bantuan/${row.id}`}
                  className="flex items-center justify-between gap-4 p-3 transition-colors hover:bg-muted/50"
                >
                  <div className="min-w-0">
                    <p className="truncate font-medium text-sm">{row.recipient_name}</p>
                    <p className="text-muted-foreground text-xs">{row.application_number}</p>
                  </div>
                  <Badge className={statusClasses[row.status]} variant="secondary">
                    {row.status_label ?? row.status}
                  </Badge>
                </Link>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
