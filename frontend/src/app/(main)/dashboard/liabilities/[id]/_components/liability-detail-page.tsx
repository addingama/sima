"use client";

import { useState } from "react";

import Link from "next/link";
import { useParams } from "next/navigation";

import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowLeft, Pencil } from "lucide-react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

import { ConfirmActionDialog } from "@/components/sima/crud/confirm-action-dialog";
import { RelationSelect } from "@/components/sima/crud/relation-select";
import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { PageShellSkeleton } from "@/components/sima/skeletons";
import { StatusBadge } from "@/components/sima/status-badge";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { useWorkflowAction } from "@/hooks/use-resource-mutation";
import { useDetailQuery } from "@/hooks/use-resource-query";
import { ApiError } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { formatDate, formatDateTime } from "@/lib/format/datetime";
import { type SettleLiabilityFormValues, settleLiabilitySchema } from "@/lib/liability/schema";
import type { OperationalLiability } from "@/lib/liability/types";
import { useAuth } from "@/providers/auth-provider";

export default function LiabilityDetailPage() {
  const params = useParams<{ id: string }>();
  const { user } = useAuth();
  const canView = hasPermission(user, "liability.view");
  const canManage = hasPermission(user, "liability.manage");
  const [voidOpen, setVoidOpen] = useState(false);
  const [settleOpen, setSettleOpen] = useState(false);

  const { data, isLoading, isError, refetch } = useDetailQuery<OperationalLiability>(
    "/liabilities",
    params.id,
    canView,
  );

  const actionMutation = useWorkflowAction<OperationalLiability>("/liabilities", params.id);

  const settleForm = useForm<SettleLiabilityFormValues>({
    resolver: zodResolver(settleLiabilitySchema),
    defaultValues: { disbursement_id: "" },
  });

  if (!canView) {
    return (
      <ErrorState
        title="Akses ditolak"
        description="Anda tidak memiliki permission untuk melihat liabilitas operasional."
      />
    );
  }

  if (isLoading) {
    return <PageShellSkeleton />;
  }

  if (isError || !data) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  const canEdit = canManage && data.status === "outstanding";
  const canSettle = canManage && !["settled", "void"].includes(data.status);
  const canVoid = canManage && data.status !== "void";

  const onSettle = async (values: SettleLiabilityFormValues) => {
    try {
      await actionMutation.mutateAsync({
        action: "settle",
        body: { disbursement_id: Number(values.disbursement_id) },
      });
      toast.success("Liabilitas diselesaikan.");
      settleForm.reset({ disbursement_id: "" });
      setSettleOpen(false);
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal settle.");
    }
  };

  const onVoid = async (payload: { reason?: string }) => {
    try {
      await actionMutation.mutateAsync({
        action: "void",
        body: { reason: payload.reason },
      });
      toast.success("Liabilitas dibatalkan.");
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal void.");
      throw error;
    }
  };

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={data.liability_number}
        description="Detail kewajiban operasional."
        actions={
          <div className="flex flex-wrap gap-2">
            {canEdit ? (
              <Button variant="outline" asChild>
                <Link href={`/dashboard/liabilities/${data.id}/edit`}>
                  <Pencil className="size-4" />
                  Edit
                </Link>
              </Button>
            ) : null}
            {canSettle ? <Button onClick={() => setSettleOpen(true)}>Settle</Button> : null}
            {canVoid ? (
              <Button variant="destructive" onClick={() => setVoidOpen(true)}>
                Void
              </Button>
            ) : null}
            <Button variant="outline" asChild>
              <Link href="/dashboard/liabilities">
                <ArrowLeft className="size-4" />
                Kembali
              </Link>
            </Button>
          </div>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>Ringkasan</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <dt className="text-muted-foreground text-sm">Status</dt>
              <dd className="font-medium">
                <StatusBadge status={data.status} />
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Tanggal</dt>
              <dd className="font-medium">{formatDate(data.liability_date)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Kreditur</dt>
              <dd className="font-medium">{data.creditor}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Nominal</dt>
              <dd className="font-medium">
                <CurrencyDisplay value={data.amount} />
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Terbayar</dt>
              <dd className="font-medium">
                <CurrencyDisplay value={data.amount_settled} />
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Dana</dt>
              <dd className="font-medium">{data.fund?.name ?? "-"}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Program</dt>
              <dd className="font-medium">{data.program?.name ?? "-"}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Jatuh Tempo</dt>
              <dd className="font-medium">{data.due_date ? formatDate(data.due_date) : "-"}</dd>
            </div>
            {data.settled_disbursement ? (
              <div>
                <dt className="text-muted-foreground text-sm">Pengeluaran Settle</dt>
                <dd className="font-medium">
                  <Link href={`/dashboard/disbursements/${data.settled_disbursement.id}`} className="hover:underline">
                    {data.settled_disbursement.disbursement_number ?? `#${data.settled_disbursement.id}`}
                  </Link>
                </dd>
              </div>
            ) : null}
            {data.settled_at ? (
              <div>
                <dt className="text-muted-foreground text-sm">Diselesaikan</dt>
                <dd className="font-medium">{formatDateTime(data.settled_at)}</dd>
              </div>
            ) : null}
            {data.void_reason ? (
              <div className="sm:col-span-2">
                <dt className="text-muted-foreground text-sm">Alasan Void</dt>
                <dd className="font-medium">{data.void_reason}</dd>
              </div>
            ) : null}
            {data.description ? (
              <div className="sm:col-span-2 lg:col-span-3">
                <dt className="text-muted-foreground text-sm">Keterangan</dt>
                <dd className="whitespace-pre-wrap font-medium">{data.description}</dd>
              </div>
            ) : null}
          </dl>
        </CardContent>
      </Card>

      <AlertDialog
        open={settleOpen}
        onOpenChange={(open) => {
          setSettleOpen(open);
          if (!open) {
            settleForm.reset({ disbursement_id: "" });
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Settle liabilitas?</AlertDialogTitle>
            <AlertDialogDescription>
              Pilih pengeluaran berstatus approved yang menyelesaikan kewajiban ini. Ledger tidak berubah lewat settle —
              arus kas sudah tercatat di pengeluaran.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <form
            className="space-y-4"
            onSubmit={(event) => {
              event.preventDefault();
              void settleForm.handleSubmit(onSettle)();
            }}
          >
            <Field>
              <FieldLabel>Pengeluaran Approved</FieldLabel>
              <Controller
                control={settleForm.control}
                name="disbursement_id"
                render={({ field }) => (
                  <RelationSelect
                    resource="/disbursements"
                    labelKey="disbursement_number"
                    params={{ status: "approved", per_page: 100 }}
                    value={field.value}
                    onChange={field.onChange}
                    placeholder="Pilih pengeluaran..."
                  />
                )}
              />
              <FieldError>{settleForm.formState.errors.disbursement_id?.message}</FieldError>
            </Field>
            <AlertDialogFooter>
              <AlertDialogCancel type="button" disabled={actionMutation.isPending}>
                Batal
              </AlertDialogCancel>
              <AlertDialogAction
                type="button"
                disabled={actionMutation.isPending}
                onClick={(event) => {
                  event.preventDefault();
                  void settleForm.handleSubmit(onSettle)();
                }}
              >
                {actionMutation.isPending ? "Memproses..." : "Settle"}
              </AlertDialogAction>
            </AlertDialogFooter>
          </form>
        </AlertDialogContent>
      </AlertDialog>

      <ConfirmActionDialog
        open={voidOpen}
        onOpenChange={setVoidOpen}
        title="Void liabilitas?"
        description="Liabilitas akan dibatalkan dan tidak dapat diselesaikan lagi."
        confirmLabel="Void"
        destructive
        requiresReason
        reasonLabel="Alasan void"
        reasonRequired
        onConfirm={onVoid}
      />
    </div>
  );
}
