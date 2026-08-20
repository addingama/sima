"use client";

import { useState } from "react";

import Link from "next/link";
import { useParams } from "next/navigation";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

import { ConfirmActionDialog } from "@/components/sima/crud/confirm-action-dialog";
import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { MoneyInput } from "@/components/sima/money-input";
import { PageHeader } from "@/components/sima/page-header";
import { PageShellSkeleton } from "@/components/sima/skeletons";
import { StatusBadge } from "@/components/sima/status-badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useWorkflowAction } from "@/hooks/use-resource-mutation";
import { useDetailQuery } from "@/hooks/use-resource-query";
import { ApiError, apiPost } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { parseAmount } from "@/lib/format/amount";
import { formatDate, formatDateTime } from "@/lib/format/datetime";
import { type AddReconciliationLineFormValues, addReconciliationLineSchema } from "@/lib/reconciliation/schema";
import type { BankReconciliation } from "@/lib/reconciliation/types";
import { useAuth } from "@/providers/auth-provider";

export default function ReconciliationDetailPage() {
  const params = useParams<{ id: string }>();
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canView = hasPermission(user, "reconciliation.view");
  const canManage = hasPermission(user, "reconciliation.manage");
  const [completeOpen, setCompleteOpen] = useState(false);
  const [addingLine, setAddingLine] = useState(false);

  const { data, isLoading, isError, refetch } = useDetailQuery<BankReconciliation>(
    "/bank-reconciliations",
    params.id,
    canView,
  );

  const completeMutation = useWorkflowAction<BankReconciliation>("/bank-reconciliations", params.id);

  const lineForm = useForm<AddReconciliationLineFormValues>({
    resolver: zodResolver(addReconciliationLineSchema),
    defaultValues: {
      statement_date: "",
      statement_ref: "",
      statement_amount: "",
      ledger_entry_id: "",
      is_matched: false,
      note: "",
    },
  });

  if (!canView) {
    return (
      <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk melihat rekonsiliasi bank." />
    );
  }

  if (isLoading) {
    return <PageShellSkeleton />;
  }

  if (isError || !data) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  const isDraft = data.status === "draft";

  const onAddLine = async (values: AddReconciliationLineFormValues) => {
    setAddingLine(true);

    try {
      const payload: Record<string, unknown> = {
        is_matched: values.is_matched,
      };

      if (values.statement_date) {
        payload.statement_date = values.statement_date;
      }

      if (values.statement_ref?.trim()) {
        payload.statement_ref = values.statement_ref.trim();
      }

      if (values.statement_amount) {
        payload.statement_amount = parseAmount(values.statement_amount).toFixed(2);
      }

      if (values.ledger_entry_id?.trim()) {
        payload.ledger_entry_id = Number(values.ledger_entry_id);
      }

      if (values.note?.trim()) {
        payload.note = values.note.trim();
      }

      await apiPost(`/bank-reconciliations/${params.id}/lines`, payload);
      toast.success("Baris rekonsiliasi ditambahkan.");
      lineForm.reset({
        statement_date: "",
        statement_ref: "",
        statement_amount: "",
        ledger_entry_id: "",
        is_matched: false,
        note: "",
      });
      await queryClient.invalidateQueries({ queryKey: ["/bank-reconciliations", params.id] });
      await queryClient.invalidateQueries({ queryKey: ["/bank-reconciliations"] });
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal menambah baris.");
    } finally {
      setAddingLine(false);
    }
  };

  const onComplete = async () => {
    try {
      await completeMutation.mutateAsync({ action: "complete" });
      toast.success("Rekonsiliasi diselesaikan.");
      setCompleteOpen(false);
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal menyelesaikan.");
    }
  };

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={`Rekonsiliasi #${data.id}`}
        description="Detail rekonsiliasi bank — menyelesaikan tidak mengubah ledger."
        actions={
          <div className="flex flex-wrap gap-2">
            {canManage && isDraft ? (
              <Button onClick={() => setCompleteOpen(true)} disabled={completeMutation.isPending}>
                Selesaikan
              </Button>
            ) : null}
            <Button variant="outline" asChild>
              <Link href="/dashboard/reconciliations">
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
              <dt className="text-muted-foreground text-sm">Rekening</dt>
              <dd className="font-medium">{data.account?.name ?? `#${data.account_id}`}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Periode</dt>
              <dd className="font-medium">
                {formatDate(data.period_start)} — {formatDate(data.period_end)}
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Saldo Rekening Koran</dt>
              <dd className="font-medium">
                <CurrencyDisplay value={data.statement_balance} />
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Saldo Sistem</dt>
              <dd className="font-medium">
                <CurrencyDisplay value={data.system_balance} />
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Selisih</dt>
              <dd className="font-medium">
                <CurrencyDisplay value={data.difference} />
              </dd>
            </div>
            {data.reconciling_total !== undefined ? (
              <div>
                <dt className="text-muted-foreground text-sm">Total Reconciling</dt>
                <dd className="font-medium">
                  <CurrencyDisplay value={data.reconciling_total} />
                </dd>
              </div>
            ) : null}
            {data.adjusted_difference !== undefined ? (
              <div>
                <dt className="text-muted-foreground text-sm">Selisih Disesuaikan</dt>
                <dd className="font-medium">
                  <CurrencyDisplay value={data.adjusted_difference} />
                </dd>
              </div>
            ) : null}
            {data.reconciled_at ? (
              <div>
                <dt className="text-muted-foreground text-sm">Diselesaikan</dt>
                <dd className="font-medium">{formatDateTime(data.reconciled_at)}</dd>
              </div>
            ) : null}
            {data.notes ? (
              <div className="sm:col-span-2 lg:col-span-3">
                <dt className="text-muted-foreground text-sm">Catatan</dt>
                <dd className="whitespace-pre-wrap font-medium">{data.notes}</dd>
              </div>
            ) : null}
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Item Reconciling</CardTitle>
          <CardDescription>
            Biaya bank deferred dan item lain yang menjelaskan selisih tanpa mengubah buku besar.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {(data.reconciling_items ?? []).length === 0 ? (
            <p className="text-muted-foreground text-sm">Tidak ada item reconciling.</p>
          ) : (
            <div className="overflow-x-auto rounded-lg border">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                  <tr>
                    <th className="px-3 py-2">Tipe</th>
                    <th className="px-3 py-2">Referensi</th>
                    <th className="px-3 py-2">Tanggal</th>
                    <th className="px-3 py-2">Keterangan</th>
                    <th className="px-3 py-2 text-right">Nominal</th>
                  </tr>
                </thead>
                <tbody>
                  {(data.reconciling_items ?? []).map((item, index) => (
                    <tr key={`${item.type}-${item.bank_fee_id ?? index}`} className="border-t">
                      <td className="px-3 py-2">{item.type}</td>
                      <td className="px-3 py-2">{item.fee_number ?? item.liability_number ?? "-"}</td>
                      <td className="px-3 py-2">{item.fee_date ? formatDate(item.fee_date) : "-"}</td>
                      <td className="px-3 py-2">{item.description ?? "-"}</td>
                      <td className="px-3 py-2 text-right">
                        <CurrencyDisplay value={item.amount} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Baris Pencocokan</CardTitle>
          <CardDescription>Catatan pencocokan baris rekening koran dengan ledger (opsional).</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-6">
          {(data.lines ?? []).length === 0 ? (
            <p className="text-muted-foreground text-sm">Belum ada baris pencocokan.</p>
          ) : (
            <div className="overflow-x-auto rounded-lg border">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                  <tr>
                    <th className="px-3 py-2">Tanggal</th>
                    <th className="px-3 py-2">Ref</th>
                    <th className="px-3 py-2">Ledger Entry</th>
                    <th className="px-3 py-2">Matched</th>
                    <th className="px-3 py-2">Catatan</th>
                    <th className="px-3 py-2 text-right">Nominal</th>
                  </tr>
                </thead>
                <tbody>
                  {(data.lines ?? []).map((line) => (
                    <tr key={line.id} className="border-t">
                      <td className="px-3 py-2">{line.statement_date ? formatDate(line.statement_date) : "-"}</td>
                      <td className="px-3 py-2">{line.statement_ref ?? "-"}</td>
                      <td className="px-3 py-2">{line.ledger_entry_id ? `#${line.ledger_entry_id}` : "-"}</td>
                      <td className="px-3 py-2">{line.is_matched ? "Ya" : "Tidak"}</td>
                      <td className="px-3 py-2">{line.note ?? "-"}</td>
                      <td className="px-3 py-2 text-right">
                        {line.statement_amount ? <CurrencyDisplay value={line.statement_amount} /> : "-"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {canManage && isDraft ? (
            <form className="grid max-w-2xl gap-4 rounded-lg border p-4" onSubmit={lineForm.handleSubmit(onAddLine)}>
              <h3 className="font-medium">Tambah Baris</h3>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field>
                  <FieldLabel>Tanggal Rekening Koran</FieldLabel>
                  <Input type="date" {...lineForm.register("statement_date")} />
                </Field>
                <Field>
                  <FieldLabel>Referensi</FieldLabel>
                  <Input {...lineForm.register("statement_ref")} placeholder="No. transaksi bank" />
                </Field>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field>
                  <FieldLabel>Nominal</FieldLabel>
                  <Controller
                    control={lineForm.control}
                    name="statement_amount"
                    render={({ field }) => <MoneyInput value={field.value} onChange={field.onChange} />}
                  />
                </Field>
                <Field>
                  <FieldLabel>ID Ledger Entry</FieldLabel>
                  <Input {...lineForm.register("ledger_entry_id")} placeholder="Opsional" />
                  <FieldError>{lineForm.formState.errors.ledger_entry_id?.message}</FieldError>
                </Field>
              </div>
              <Field>
                <FieldLabel>Catatan</FieldLabel>
                <Textarea rows={2} {...lineForm.register("note")} />
              </Field>
              <Field orientation="horizontal" className="items-center gap-2">
                <Controller
                  control={lineForm.control}
                  name="is_matched"
                  render={({ field }) => (
                    <Checkbox checked={field.value} onCheckedChange={(checked) => field.onChange(checked === true)} />
                  )}
                />
                <FieldLabel className="font-normal">Sudah dicocokkan</FieldLabel>
              </Field>
              <div>
                <Button type="submit" size="sm" disabled={addingLine}>
                  {addingLine ? "Menyimpan..." : "Tambah Baris"}
                </Button>
              </div>
            </form>
          ) : null}
        </CardContent>
      </Card>

      <ConfirmActionDialog
        open={completeOpen}
        onOpenChange={setCompleteOpen}
        title="Selesaikan rekonsiliasi?"
        description="Status akan menjadi completed. Ledger tidak berubah. Pastikan selisih sudah dipahami."
        confirmLabel="Selesaikan"
        onConfirm={onComplete}
      />
    </div>
  );
}
