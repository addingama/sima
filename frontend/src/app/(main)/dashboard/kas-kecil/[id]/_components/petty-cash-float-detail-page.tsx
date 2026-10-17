// biome-ignore-all lint/style/noNestedTernary: Status query bersifat eksklusif dan lebih mudah dibaca dekat markup.
"use client";

import { useState } from "react";

import Link from "next/link";
import { useParams } from "next/navigation";

import { zodResolver } from "@hookform/resolvers/zod";
import { AlertCircle, ArrowLeft, CheckCircle2, Info, Plus, RefreshCw, Trash2, XCircle } from "lucide-react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

import { RelationSelect } from "@/components/sima/crud/relation-select";
import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { MoneyInput } from "@/components/sima/money-input";
import { PageHeader } from "@/components/sima/page-header";
import { PageShellSkeleton } from "@/components/sima/skeletons";
import { StatusBadge } from "@/components/sima/status-badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { useResourceCreate, useWorkflowAction } from "@/hooks/use-resource-mutation";
import { useDetailQuery } from "@/hooks/use-resource-query";
import { ApiError } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { normalizeAmountString } from "@/lib/format/amount";
import { formatDate, formatDateTime } from "@/lib/format/datetime";
import {
  type PettyCashCloseFormValues,
  type PettyCashDisbursementFormValues,
  type PettyCashLineFormValues,
  type PettyCashTopupRequestFormValues,
  pettyCashCloseSchema,
  pettyCashDisbursementSchema,
  pettyCashLineSchema,
  pettyCashTopupRequestSchema,
} from "@/lib/petty-cash/schema";
import type { PettyCashFloat, PettyCashLine } from "@/lib/petty-cash/types";
import { useAuth } from "@/providers/auth-provider";

// ─── helpers ─────────────────────────────────────────────────────────────────

function purposeLabel(p?: string) {
  if (p === "operational") return "Operasional";
  if (p === "event") return "Event";
  return p ?? "-";
}

// ─── Line form ────────────────────────────────────────────────────────────────

function AddLineForm({ floatId, onSuccess }: { floatId: number; onSuccess: () => void }) {
  const create = useResourceCreate<PettyCashLine>(`/petty-cash-floats/${floatId}/lines`);

  const form = useForm<PettyCashLineFormValues>({
    resolver: zodResolver(pettyCashLineSchema),
    defaultValues: {
      category_id: "",
      spent_on: new Date().toISOString().slice(0, 10),
      amount: "",
      description: "",
    },
  });

  const onSubmit = async (values: PettyCashLineFormValues) => {
    try {
      await create.mutateAsync({
        category_id: Number(values.category_id),
        spent_on: values.spent_on,
        amount: normalizeAmountString(values.amount),
        description: values.description.trim(),
      });
      toast.success("Baris belanja ditambahkan.");
      form.reset({
        category_id: "",
        spent_on: new Date().toISOString().slice(0, 10),
        amount: "",
        description: "",
      });
      onSuccess();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal menyimpan baris.");
    }
  };

  return (
    <form className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" onSubmit={form.handleSubmit(onSubmit)}>
      <Field>
        <FieldLabel>Kategori</FieldLabel>
        <Controller
          control={form.control}
          name="category_id"
          render={({ field }) => (
            <RelationSelect
              resource="/petty-cash-categories"
              labelKey="name"
              params={{ active_only: 1, per_page: 100 }}
              value={field.value}
              onChange={field.onChange}
              placeholder="Pilih kategori..."
            />
          )}
        />
        <FieldError>{form.formState.errors.category_id?.message}</FieldError>
      </Field>

      <Field>
        <FieldLabel>Tanggal Belanja</FieldLabel>
        <Input type="date" {...form.register("spent_on")} />
        <FieldError>{form.formState.errors.spent_on?.message}</FieldError>
      </Field>

      <Field>
        <FieldLabel>Nominal</FieldLabel>
        <Controller
          control={form.control}
          name="amount"
          render={({ field }) => <MoneyInput value={field.value} onChange={field.onChange} />}
        />
        <FieldError>{form.formState.errors.amount?.message}</FieldError>
      </Field>

      <Field>
        <FieldLabel>Keterangan</FieldLabel>
        <Input {...form.register("description")} placeholder="Keterangan belanja" />
        <FieldError>{form.formState.errors.description?.message}</FieldError>
      </Field>

      <div className="sm:col-span-2 lg:col-span-4">
        <Button type="submit" disabled={create.isPending}>
          <Plus className="size-4" />
          {create.isPending ? "Menyimpan..." : "Tambah Baris"}
        </Button>
      </div>
    </form>
  );
}

// ─── Initial Disbursement Form ─────────────────────────────────────────────────

function DisbursementForm({ floatId, onSuccess }: { floatId: number; onSuccess: () => void }) {
  const create = useResourceCreate<PettyCashFloat>(`/petty-cash-floats/${floatId}/disbursements`);

  const form = useForm<PettyCashDisbursementFormValues>({
    resolver: zodResolver(pettyCashDisbursementSchema),
    defaultValues: {
      amount: "",
      disbursement_date: new Date().toISOString().slice(0, 10),
      account_id: "",
      fund_id: "",
      description: "",
      notes: "",
    },
  });

  const onSubmit = async (values: PettyCashDisbursementFormValues) => {
    try {
      await create.mutateAsync({
        amount: normalizeAmountString(values.amount),
        disbursement_date: values.disbursement_date,
        account_id: Number(values.account_id),
        sources: [{ fund_id: Number(values.fund_id), amount: normalizeAmountString(values.amount) }],
        description: values.description?.trim() || null,
        notes: values.notes?.trim() || null,
      });
      toast.success("Pengeluaran pendanaan dibuat. Float sekarang dalam status reporting.");
      onSuccess();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal mencairkan.");
    }
  };

  return (
    <form className="grid max-w-xl gap-4" onSubmit={form.handleSubmit(onSubmit)}>
      <Field>
        <FieldLabel>Nominal</FieldLabel>
        <Controller
          control={form.control}
          name="amount"
          render={({ field }) => <MoneyInput value={field.value} onChange={field.onChange} />}
        />
        <FieldError>{form.formState.errors.amount?.message}</FieldError>
      </Field>

      <Field>
        <FieldLabel>Tanggal Pencairan</FieldLabel>
        <Input type="date" {...form.register("disbursement_date")} />
        <FieldError>{form.formState.errors.disbursement_date?.message}</FieldError>
      </Field>

      <Field>
        <FieldLabel>Rekening Kas/Bank</FieldLabel>
        <Controller
          control={form.control}
          name="account_id"
          render={({ field }) => (
            <RelationSelect
              resource="/accounts"
              labelKey="name"
              params={{ per_page: 100 }}
              value={field.value}
              onChange={field.onChange}
              placeholder="Pilih rekening..."
            />
          )}
        />
        <FieldError>{form.formState.errors.account_id?.message}</FieldError>
      </Field>

      <Field>
        <FieldLabel>Dana Amanah</FieldLabel>
        <Controller
          control={form.control}
          name="fund_id"
          render={({ field }) => (
            <RelationSelect
              resource="/funds"
              labelKey="name"
              params={{ is_active: 1, per_page: 100 }}
              value={field.value}
              onChange={field.onChange}
              placeholder="Pilih dana..."
            />
          )}
        />
        <FieldError>{form.formState.errors.fund_id?.message}</FieldError>
      </Field>

      <Field>
        <FieldLabel>Keterangan (opsional)</FieldLabel>
        <Textarea rows={2} {...form.register("description")} />
      </Field>

      <div>
        <Button type="submit" disabled={create.isPending}>
          {create.isPending ? "Memproses..." : "Cairkan Anggaran Awal"}
        </Button>
      </div>
    </form>
  );
}

// ─── Topup request form ────────────────────────────────────────────────────────

function TopupRequestForm({ floatId, onSuccess }: { floatId: number; onSuccess: () => void }) {
  const create = useResourceCreate(`/petty-cash-floats/${floatId}/topup-requests`);

  const form = useForm<PettyCashTopupRequestFormValues>({
    resolver: zodResolver(pettyCashTopupRequestSchema),
    defaultValues: { amount: "", reason: "" },
  });

  const onSubmit = async (values: PettyCashTopupRequestFormValues) => {
    try {
      await create.mutateAsync({
        amount: normalizeAmountString(values.amount),
        reason: values.reason.trim(),
      });
      toast.success("Permintaan top-up terkirim.");
      form.reset({ amount: "", reason: "" });
      onSuccess();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal mengirim permintaan.");
    }
  };

  return (
    <form className="grid max-w-xl gap-4" onSubmit={form.handleSubmit(onSubmit)}>
      <Field>
        <FieldLabel>Nominal Tambahan</FieldLabel>
        <Controller
          control={form.control}
          name="amount"
          render={({ field }) => <MoneyInput value={field.value} onChange={field.onChange} />}
        />
        <FieldError>{form.formState.errors.amount?.message}</FieldError>
      </Field>
      <Field>
        <FieldLabel>Alasan</FieldLabel>
        <Textarea rows={3} {...form.register("reason")} placeholder="Jelaskan kebutuhan tambahan anggaran..." />
        <FieldError>{form.formState.errors.reason?.message}</FieldError>
      </Field>
      <div>
        <Button type="submit" disabled={create.isPending}>
          {create.isPending ? "Mengirim..." : "Kirim Permintaan Top-up"}
        </Button>
      </div>
    </form>
  );
}

// ─── Close Dialog ──────────────────────────────────────────────────────────────

function CloseDialog({
  floatId,
  open,
  onOpenChange,
}: {
  floatId: number;
  open: boolean;
  onOpenChange: (v: boolean) => void;
}) {
  const action = useWorkflowAction<PettyCashFloat>("/petty-cash-floats", floatId);

  const form = useForm<PettyCashCloseFormValues>({
    resolver: zodResolver(pettyCashCloseSchema),
    defaultValues: { returned_amount: "", account_id: "", receipt_date: "" },
  });

  const onSubmit = async (values: PettyCashCloseFormValues) => {
    try {
      await action.mutateAsync({
        action: "close",
        body: {
          returned_amount: values.returned_amount ? normalizeAmountString(values.returned_amount) : null,
          account_id: values.account_id ? Number(values.account_id) : null,
          receipt_date: values.receipt_date || null,
        },
      });
      toast.success("Float ditutup.");
      onOpenChange(false);
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal menutup float.");
    }
  };

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Tutup float?</AlertDialogTitle>
          <AlertDialogDescription>
            Pastikan total belanja + sisa yang dikembalikan = dana yang dicairkan. Setelah ditutup, tidak dapat menambah
            baris belanja lagi.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <form
          className="space-y-4"
          onSubmit={(e) => {
            e.preventDefault();
            void form.handleSubmit(onSubmit)();
          }}
        >
          <Field>
            <FieldLabel>Nominal Dikembalikan (opsional)</FieldLabel>
            <Controller
              control={form.control}
              name="returned_amount"
              render={({ field }) => <MoneyInput value={field.value ?? ""} onChange={field.onChange} />}
            />
            <FieldError>{form.formState.errors.returned_amount?.message}</FieldError>
          </Field>
          <Field>
            <FieldLabel>Rekening Setor Kembali (opsional)</FieldLabel>
            <Controller
              control={form.control}
              name="account_id"
              render={({ field }) => (
                <RelationSelect
                  resource="/accounts"
                  labelKey="name"
                  params={{ per_page: 100 }}
                  value={field.value ?? ""}
                  onChange={field.onChange}
                  placeholder="Pilih rekening..."
                />
              )}
            />
          </Field>
          <Field>
            <FieldLabel>Tanggal Penerimaan Sisa (opsional)</FieldLabel>
            <Input type="date" {...form.register("receipt_date")} />
          </Field>
          <AlertDialogFooter>
            <AlertDialogCancel type="button" disabled={action.isPending}>
              Batal
            </AlertDialogCancel>
            <AlertDialogAction
              type="button"
              disabled={action.isPending}
              onClick={(e) => {
                e.preventDefault();
                void form.handleSubmit(onSubmit)();
              }}
            >
              {action.isPending ? "Memproses..." : "Tutup Float"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </form>
      </AlertDialogContent>
    </AlertDialog>
  );
}

// ─── Main detail page ──────────────────────────────────────────────────────────

export default function PettyCashFloatDetailPage() {
  const params = useParams<{ id: string }>();
  const { user } = useAuth();
  const canView = hasPermission(user, "petty_cash.view");
  const canUpdate = hasPermission(user, "petty_cash.update");
  const canClose = hasPermission(user, "petty_cash.close");
  const canFund = hasPermission(user, "petty_cash.fund");
  const canTopupRequest = hasPermission(user, "petty_cash.topup_request");

  const [closeOpen, setCloseOpen] = useState(false);
  const [showTopupForm, setShowTopupForm] = useState(false);
  const [showDisbursementForm, setShowDisbursementForm] = useState(false);
  const [deletingLineId, setDeletingLineId] = useState<number | null>(null);

  const { data, isLoading, isError, refetch } = useDetailQuery<PettyCashFloat>(
    "/petty-cash-floats",
    params.id,
    canView,
  );

  const cancelAction = useWorkflowAction<PettyCashFloat>("/petty-cash-floats", params.id);
  const reopenAction = useWorkflowAction<PettyCashFloat>("/petty-cash-floats", params.id);
  const _deleteLineMutation = useResourceCreate<void>(`/petty-cash-floats/${params.id}/lines`);

  if (!canView) {
    return <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk melihat float ini." />;
  }

  if (isLoading) return <PageShellSkeleton />;
  if (isError || !data) return <ErrorState onRetry={() => refetch()} />;

  const isReporting = data.status === "reporting";
  const isDraft = data.status === "draft";
  const isClosed = data.status === "closed";
  const isCancelled = data.status === "cancelled";

  // Custodian = user who can manage lines
  const isCustodian = user?.id === data.custodian_user_id;

  const canAddLine = canUpdate && isReporting && (isCustodian || canFund);
  const canDeleteLine = canUpdate && isReporting;

  const handleDeleteLine = async (lineId: number) => {
    try {
      await fetch(
        `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api"}/petty-cash-floats/${params.id}/lines/${lineId}`,
        {
          method: "DELETE",
          headers: {
            Authorization: `Bearer ${(await import("@/lib/auth/session.client")).getClientToken() ?? ""}`,
            Accept: "application/json",
          },
        },
      );
      toast.success("Baris dihapus.");
      await refetch();
    } catch {
      toast.error("Gagal menghapus baris.");
    } finally {
      setDeletingLineId(null);
    }
  };

  const handleCancel = async () => {
    try {
      await cancelAction.mutateAsync({ action: "cancel" });
      toast.success("Float dibatalkan.");
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal membatalkan.");
    }
  };

  const handleReopen = async () => {
    try {
      await reopenAction.mutateAsync({ action: "reopen" });
      toast.success("Float dibuka kembali.");
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal membuka kembali.");
    }
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Context banner */}
      <Alert>
        <Info className="size-4" />
        <AlertDescription>
          Float <strong>{data.float_number}</strong> — <strong>{data.title}</strong>. PJ: {data.custodian?.name ?? "-"}.
          Status: <StatusBadge status={data.status} />
        </AlertDescription>
      </Alert>

      <PageHeader
        title={data.float_number}
        description={data.title}
        actions={
          <div className="flex flex-wrap gap-2">
            {canClose && isReporting ? (
              <Button onClick={() => setCloseOpen(true)}>
                <CheckCircle2 className="size-4" />
                Tutup Float
              </Button>
            ) : null}
            {canTopupRequest && isReporting ? (
              <Button variant="outline" onClick={() => setShowTopupForm((v) => !v)}>
                {showTopupForm ? (
                  <>
                    <XCircle className="size-4" />
                    Batal Top-up
                  </>
                ) : (
                  <>
                    <Plus className="size-4" />
                    Minta Top-up
                  </>
                )}
              </Button>
            ) : null}
            {canFund && isClosed ? (
              <Button variant="outline" onClick={handleReopen}>
                <RefreshCw className="size-4" />
                Buka Kembali
              </Button>
            ) : null}
            {isDraft && canUpdate ? (
              <Button variant="destructive" onClick={handleCancel}>
                <XCircle className="size-4" />
                Batalkan Float
              </Button>
            ) : null}
            <Button variant="outline" asChild>
              <Link href="/dashboard/kas-kecil">
                <ArrowLeft className="size-4" />
                Kembali
              </Link>
            </Button>
          </div>
        }
      />

      {/* Topup request form */}
      {showTopupForm ? (
        <Card>
          <CardHeader>
            <CardTitle>Permintaan Top-up Anggaran</CardTitle>
          </CardHeader>
          <CardContent>
            <TopupRequestForm
              floatId={data.id}
              onSuccess={() => {
                setShowTopupForm(false);
                void refetch();
              }}
            />
          </CardContent>
        </Card>
      ) : null}

      {/* Summary */}
      <Card>
        <CardHeader>
          <CardTitle>Ringkasan Float</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <dt className="text-muted-foreground text-sm">Status</dt>
              <dd className="font-medium">
                <StatusBadge status={data.status} />
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Tujuan</dt>
              <dd className="font-medium">{purposeLabel(data.purpose)}</dd>
            </div>
            {data.period_start ? (
              <div>
                <dt className="text-muted-foreground text-sm">Periode</dt>
                <dd className="font-medium">
                  {formatDate(data.period_start)} – {data.period_end ? formatDate(data.period_end) : "—"}
                </dd>
              </div>
            ) : null}
            {data.program ? (
              <div>
                <dt className="text-muted-foreground text-sm">Event</dt>
                <dd className="font-medium">{data.program.name}</dd>
              </div>
            ) : null}
            <div>
              <dt className="text-muted-foreground text-sm">Dana Dicairkan</dt>
              <dd className="font-medium">
                <CurrencyDisplay value={data.funded_total ?? "0"} />
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Terpakai</dt>
              <dd className="font-medium">
                <CurrencyDisplay value={data.spent_total ?? "0"} />
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-sm">Sisa</dt>
              <dd className="font-medium">
                <CurrencyDisplay value={data.remaining_total ?? "0"} />
              </dd>
            </div>
            {data.returned_amount && Number(data.returned_amount) > 0 ? (
              <div>
                <dt className="text-muted-foreground text-sm">Dikembalikan</dt>
                <dd className="font-medium">
                  <CurrencyDisplay value={data.returned_amount} />
                </dd>
              </div>
            ) : null}
            {data.funded_at ? (
              <div>
                <dt className="text-muted-foreground text-sm">Pertama Dicairkan</dt>
                <dd className="font-medium">{formatDateTime(data.funded_at)}</dd>
              </div>
            ) : null}
            {data.closed_at ? (
              <div>
                <dt className="text-muted-foreground text-sm">Ditutup</dt>
                <dd className="font-medium">{formatDateTime(data.closed_at)}</dd>
              </div>
            ) : null}
            {data.notes ? (
              <div className="sm:col-span-2 lg:col-span-4">
                <dt className="text-muted-foreground text-sm">Catatan</dt>
                <dd className="whitespace-pre-wrap font-medium">{data.notes}</dd>
              </div>
            ) : null}
          </dl>
        </CardContent>
      </Card>

      {/* Initial disbursement (draft only, keuangan role) */}
      {isDraft && canFund ? (
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>Pencairan Anggaran Awal</CardTitle>
            <Button variant="outline" size="sm" onClick={() => setShowDisbursementForm((v) => !v)}>
              {showDisbursementForm ? "Sembunyikan" : "Cairkan Sekarang"}
            </Button>
          </CardHeader>
          {showDisbursementForm ? (
            <CardContent>
              <Alert className="mb-4">
                <AlertCircle className="size-4" />
                <AlertDescription>
                  Ini akan membuat Pengeluaran (DSB) baru. Float akan otomatis masuk status <em>reporting</em> setelah
                  DSB pertama di-approve.
                </AlertDescription>
              </Alert>
              <DisbursementForm
                floatId={data.id}
                onSuccess={() => {
                  setShowDisbursementForm(false);
                  void refetch();
                }}
              />
            </CardContent>
          ) : null}
        </Card>
      ) : null}

      {/* Funding list */}
      {data.fundings && data.fundings.length > 0 ? (
        <Card>
          <CardHeader>
            <CardTitle>
              Pendanaan DSB{" "}
              <Badge variant="secondary" className="ml-1">
                {data.fundings.length}
              </Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Jenis</TableHead>
                  <TableHead>Nominal</TableHead>
                  <TableHead>No. Pengeluaran</TableHead>
                  <TableHead>Status DSB</TableHead>
                  <TableHead>Tanggal</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.fundings.map((f) => (
                  <TableRow key={f.id}>
                    <TableCell className="capitalize">{f.kind}</TableCell>
                    <TableCell>
                      <CurrencyDisplay value={f.amount} />
                    </TableCell>
                    <TableCell>
                      {f.disbursement ? (
                        <Link
                          href={`/dashboard/disbursements/${f.disbursement.id}`}
                          className="font-medium hover:underline"
                        >
                          {f.disbursement.disbursement_number}
                        </Link>
                      ) : (
                        "-"
                      )}
                    </TableCell>
                    <TableCell>{f.disbursement ? <StatusBadge status={f.disbursement.status} /> : "-"}</TableCell>
                    <TableCell>
                      {f.disbursement?.disbursement_date ? formatDate(f.disbursement.disbursement_date) : "-"}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      ) : null}

      {/* Topup requests */}
      {data.topup_requests && data.topup_requests.length > 0 ? (
        <Card>
          <CardHeader>
            <CardTitle>
              Permintaan Top-up{" "}
              <Badge variant="secondary" className="ml-1">
                {data.topup_requests.length}
              </Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Nominal</TableHead>
                  <TableHead>Alasan</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Catatan</TableHead>
                  <TableHead>Waktu</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.topup_requests.map((tr) => (
                  <TableRow key={tr.id}>
                    <TableCell>
                      <CurrencyDisplay value={tr.amount} />
                    </TableCell>
                    <TableCell className="max-w-xs truncate">{tr.reason}</TableCell>
                    <TableCell>
                      <StatusBadge status={tr.status} />
                    </TableCell>
                    <TableCell>{tr.resolution_notes ?? "-"}</TableCell>
                    <TableCell>{tr.created_at ? formatDateTime(tr.created_at) : "-"}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      ) : null}

      {/* Lines table */}
      <Card>
        <CardHeader>
          <CardTitle>
            Baris Belanja{" "}
            <Badge variant="secondary" className="ml-1">
              {data.lines?.length ?? 0}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-6">
          {isReporting && !isClosed && !isCancelled ? (
            canAddLine ? (
              <div>
                <p className="mb-4 text-muted-foreground text-sm">
                  Tambahkan baris belanja yang sudah terjadi. Tidak perlu approval — langsung tercatat.
                </p>
                <AddLineForm floatId={data.id} onSuccess={() => void refetch()} />
              </div>
            ) : null
          ) : null}

          {!data.lines || data.lines.length === 0 ? (
            <p className="text-muted-foreground text-sm">Belum ada baris belanja.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Tanggal</TableHead>
                  <TableHead>Kategori</TableHead>
                  <TableHead>Keterangan</TableHead>
                  <TableHead className="text-right">Nominal</TableHead>
                  {canDeleteLine ? <TableHead /> : null}
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.lines.map((line) => (
                  <TableRow key={line.id}>
                    <TableCell>{formatDate(line.spent_on)}</TableCell>
                    <TableCell>{line.category?.name ?? "-"}</TableCell>
                    <TableCell className="max-w-xs truncate">{line.description}</TableCell>
                    <TableCell className="text-right">
                      <CurrencyDisplay value={line.amount} />
                    </TableCell>
                    {canDeleteLine ? (
                      <TableCell>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="text-destructive hover:text-destructive"
                          onClick={() => setDeletingLineId(line.id)}
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </TableCell>
                    ) : null}
                  </TableRow>
                ))}
                <TableRow className="font-semibold">
                  <TableCell colSpan={canDeleteLine ? 3 : 3} />
                  <TableCell className="text-right">
                    <CurrencyDisplay value={data.spent_total ?? "0"} />
                  </TableCell>
                  {canDeleteLine ? <TableCell /> : null}
                </TableRow>
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      {/* Close dialog */}
      <CloseDialog floatId={data.id} open={closeOpen} onOpenChange={setCloseOpen} />

      {/* Delete line confirm */}
      <AlertDialog
        open={deletingLineId !== null}
        onOpenChange={(open) => {
          if (!open) setDeletingLineId(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Hapus baris belanja?</AlertDialogTitle>
            <AlertDialogDescription>Baris ini akan dihapus dan tidak dapat dikembalikan.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Batal</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (deletingLineId !== null) void handleDeleteLine(deletingLineId);
              }}
            >
              Hapus
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
