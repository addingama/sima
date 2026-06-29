"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";

import { toast } from "sonner";
import { Pencil, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ApprovalTimeline } from "@/components/sima/crud/approval-timeline";
import { AttachmentPanel } from "@/components/sima/crud/attachment-panel";
import { AuditPanel } from "@/components/sima/crud/audit-panel";
import { buildCrudBreadcrumbs, CrudBreadcrumb } from "@/components/sima/crud/crud-breadcrumb";
import { DetailFieldGrid } from "@/components/sima/crud/detail-field-grid";
import { ConfirmActionDialog } from "@/components/sima/crud/confirm-action-dialog";
import { WorkflowActionsBar } from "@/components/sima/crud/workflow-actions-bar";
import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { StatusBadge } from "@/components/sima/status-badge";
import { PageShellSkeleton } from "@/components/sima/skeletons";
import type { ApprovalRecord } from "@/lib/api/entities";
import { useDetailQuery } from "@/hooks/use-resource-query";
import { useResourceDelete } from "@/hooks/use-resource-mutation";
import { hasPermission } from "@/lib/auth/permissions";
import type { ResourceDef } from "@/lib/resources/types";
import { useAuth } from "@/providers/auth-provider";
import { useState } from "react";

function LineItemsTable({
  label,
  rows,
}: {
  label: string;
  rows: Array<Record<string, unknown>>;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{label}</CardTitle>
      </CardHeader>
      <CardContent>
        {!rows.length ? (
          <p className="text-muted-foreground text-sm">Tidak ada data.</p>
        ) : (
          <div className="overflow-hidden rounded-lg border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50">
                <tr>
                  <th className="px-3 py-2 text-left">Dana Amanah</th>
                  <th className="px-3 py-2 text-left">Program</th>
                  <th className="px-3 py-2 text-right">Nominal</th>
                  <th className="px-3 py-2 text-left">Catatan</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row, index) => (
                  <tr key={String(row.id ?? index)} className="border-t">
                    <td className="px-3 py-2">{(row.fund as { name?: string } | undefined)?.name ?? "-"}</td>
                    <td className="px-3 py-2">{(row.program as { name?: string } | undefined)?.name ?? "-"}</td>
                    <td className="px-3 py-2 text-right">
                      <CurrencyDisplay value={row.amount as string | number} />
                    </td>
                    <td className="px-3 py-2">{String(row.note ?? "-")}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export function CrudDetailPage({
  config,
  id,
}: {
  config: ResourceDef;
  id: string;
}) {
  const router = useRouter();
  const { user } = useAuth();
  const [deleteOpen, setDeleteOpen] = useState(false);
  const deleteMutation = useResourceDelete(config.resource, id);
  const { data, isLoading, isError, refetch } = useDetailQuery<Record<string, unknown>>(config.resource, id);

  if (isLoading) {
    return <PageShellSkeleton />;
  }

  if (isError || !data) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  const title =
    typeof config.titleField === "function" ? config.titleField(data) : String(data[config.titleField] ?? config.label);
  const hasManagePermission = hasPermission(
    user,
    config.permissions.manage ?? config.permissions.create ?? "",
  );
  const canEdit = hasManagePermission && (config.canEdit?.(data) ?? true);
  const canDelete =
    hasManagePermission && (config.canDelete?.(data) ?? false);
  const lineItems =
    config.lineItems?.key === "allocations"
      ? (data.allocations as Array<Record<string, unknown>> | undefined) ?? []
      : config.lineItems?.key === "sources"
        ? (data.fund_sources as Array<Record<string, unknown>> | undefined) ?? []
        : [];

  return (
    <div className="flex flex-col gap-6">
      <CrudBreadcrumb items={buildCrudBreadcrumbs(config, "detail", data)} />

      <PageHeader
        title={title}
        description={`Detail ${config.label.toLowerCase()}.`}
        actions={
          <div className="flex flex-wrap gap-2">
            {data.status ? <StatusBadge status={String(data.status)} /> : null}
            {canEdit ? (
              <Button asChild variant="outline" size="sm">
                <Link href={`${config.basePath}/${id}/edit`}>
                  <Pencil className="size-4" />
                  Edit
                </Link>
              </Button>
            ) : null}
            {canDelete ? (
              <Button variant="destructive" size="sm" onClick={() => setDeleteOpen(true)}>
                <Trash2 className="size-4" />
                Hapus
              </Button>
            ) : null}
          </div>
        }
      />

      {config.workflow ? (
        <WorkflowActionsBar
          resource={config.resource}
          id={id}
          status={String(data.status ?? "")}
          actions={config.workflow}
          onCompleted={() => refetch()}
        />
      ) : null}

      <Tabs defaultValue="detail">
        <TabsList>
          <TabsTrigger value="detail">Detail</TabsTrigger>
          {config.workflow ? <TabsTrigger value="timeline">Timeline</TabsTrigger> : null}
          {config.attachments ? <TabsTrigger value="attachments">Lampiran</TabsTrigger> : null}
          {config.audit && hasPermission(user, config.audit.permission) ? (
            <TabsTrigger value="audit">Audit</TabsTrigger>
          ) : null}
        </TabsList>

        <TabsContent value="detail" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Informasi</CardTitle>
            </CardHeader>
            <CardContent>
              <DetailFieldGrid fields={config.detailFields} data={data} />
            </CardContent>
          </Card>
          {config.lineItems ? <LineItemsTable label={config.lineItems.label} rows={lineItems} /> : null}
        </TabsContent>

        {config.workflow ? (
          <TabsContent value="timeline">
            <ApprovalTimeline approvals={(data.approvals as ApprovalRecord[] | undefined) ?? []} />
          </TabsContent>
        ) : null}

        {config.attachments ? (
          <TabsContent value="attachments">
            <AttachmentPanel
              attachableType={config.attachments.attachableType}
              attachableId={Number(id)}
              managePermission={config.attachments.managePermission}
            />
          </TabsContent>
        ) : null}

        {config.audit && hasPermission(user, config.audit.permission) ? (
          <TabsContent value="audit">
            <AuditPanel auditableType={config.audit.auditableType} auditableId={Number(id)} />
          </TabsContent>
        ) : null}
      </Tabs>

      <ConfirmActionDialog
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
        title={`Hapus ${config.label}?`}
        description="Data akan dinonaktifkan dari sistem."
        confirmLabel="Hapus"
        destructive
        onConfirm={async () => {
          try {
            await deleteMutation.mutateAsync(id);
            toast.success("Data berhasil dihapus.");
            router.push(config.basePath);
          } catch (error) {
            toast.error(error instanceof Error ? error.message : "Gagal menghapus data.");
          }
        }}
      />
    </div>
  );
}
