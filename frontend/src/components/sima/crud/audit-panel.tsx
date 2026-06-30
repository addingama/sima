"use client";

import { useQuery } from "@tanstack/react-query";

import { ErrorState } from "@/components/sima/error-state";
import { TableSkeleton } from "@/components/sima/skeletons";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { apiGet } from "@/lib/api/client";
import type { AuditRecord } from "@/lib/api/entities";
import { formatDateTime } from "@/lib/format/datetime";

export function AuditPanel({ auditableType, auditableId }: { auditableType: string; auditableId: number }) {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ["/audits", auditableType, auditableId],
    queryFn: async () => {
      const response = await apiGet<AuditRecord[]>("/audits", {
        auditable_type: auditableType,
        auditable_id: auditableId,
        per_page: 20,
        sort: "created_at",
        direction: "desc",
      });

      return response.data;
    },
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>Riwayat Audit</CardTitle>
      </CardHeader>
      <CardContent>
        {isError ? (
          <ErrorState onRetry={() => refetch()} />
        ) : isLoading ? (
          <TableSkeleton rows={4} />
        ) : !data?.length ? (
          <p className="text-muted-foreground text-sm">Belum ada riwayat audit.</p>
        ) : (
          <ul className="space-y-3">
            {data.map((audit) => (
              <li key={audit.id} className="rounded-lg border p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="font-medium text-sm capitalize">{audit.event}</span>
                  <span className="text-muted-foreground text-xs">{formatDateTime(audit.created_at)}</span>
                </div>
                <p className="mt-1 text-muted-foreground text-sm">{audit.user?.name ?? "Sistem"}</p>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
