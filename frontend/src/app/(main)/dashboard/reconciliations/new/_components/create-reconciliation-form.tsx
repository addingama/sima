"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";

import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowLeft } from "lucide-react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

import { RelationSelect } from "@/components/sima/crud/relation-select";
import { ErrorState } from "@/components/sima/error-state";
import { MoneyInput } from "@/components/sima/money-input";
import { PageHeader } from "@/components/sima/page-header";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useResourceCreate } from "@/hooks/use-resource-mutation";
import { ApiError } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { parseAmount } from "@/lib/format/amount";
import { type CreateReconciliationFormValues, createReconciliationSchema } from "@/lib/reconciliation/schema";
import type { BankReconciliation } from "@/lib/reconciliation/types";
import { useAuth } from "@/providers/auth-provider";

export default function CreateReconciliationForm() {
  const router = useRouter();
  const { user } = useAuth();
  const createMutation = useResourceCreate<BankReconciliation>("/bank-reconciliations");

  const form = useForm<CreateReconciliationFormValues>({
    resolver: zodResolver(createReconciliationSchema),
    defaultValues: {
      account_id: "",
      period_start: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
      period_end: new Date().toISOString().slice(0, 10),
      statement_balance: "",
      notes: "",
    },
  });

  if (!hasPermission(user, "reconciliation.manage")) {
    return (
      <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk membuat rekonsiliasi bank." />
    );
  }

  const onSubmit = async (values: CreateReconciliationFormValues) => {
    try {
      const result = await createMutation.mutateAsync({
        account_id: Number(values.account_id),
        period_start: values.period_start,
        period_end: values.period_end,
        statement_balance: parseAmount(values.statement_balance).toFixed(2),
        notes: values.notes?.trim() || null,
      });

      toast.success("Rekonsiliasi dibuat.");
      router.push(`/dashboard/reconciliations/${result.id}`);
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal menyimpan.");
    }
  };

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Buat Rekonsiliasi Bank"
        description="Isi periode dan saldo rekening koran. Saldo sistem dihitung otomatis dari ledger."
        actions={
          <Button variant="outline" asChild>
            <Link href="/dashboard/reconciliations">
              <ArrowLeft className="size-4" />
              Kembali
            </Link>
          </Button>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>Informasi Rekonsiliasi</CardTitle>
        </CardHeader>
        <CardContent>
          <form className="grid max-w-xl gap-4" onSubmit={form.handleSubmit(onSubmit)}>
            <Field>
              <FieldLabel>Rekening Kas/Bank</FieldLabel>
              <Controller
                control={form.control}
                name="account_id"
                render={({ field }) => (
                  <RelationSelect
                    resource="/accounts"
                    labelKey="name"
                    params={{ is_active: 1, per_page: 100 }}
                    value={field.value}
                    onChange={field.onChange}
                    placeholder="Pilih rekening..."
                  />
                )}
              />
              <FieldError>{form.formState.errors.account_id?.message}</FieldError>
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel>Awal Periode</FieldLabel>
                <Input type="date" {...form.register("period_start")} />
                <FieldError>{form.formState.errors.period_start?.message}</FieldError>
              </Field>
              <Field>
                <FieldLabel>Akhir Periode</FieldLabel>
                <Input type="date" {...form.register("period_end")} />
                <FieldError>{form.formState.errors.period_end?.message}</FieldError>
              </Field>
            </div>

            <Field>
              <FieldLabel>Saldo Rekening Koran</FieldLabel>
              <Controller
                control={form.control}
                name="statement_balance"
                render={({ field }) => <MoneyInput value={field.value} onChange={field.onChange} />}
              />
              <FieldError>{form.formState.errors.statement_balance?.message}</FieldError>
            </Field>

            <Field>
              <FieldLabel>Catatan</FieldLabel>
              <Textarea rows={3} {...form.register("notes")} placeholder="Opsional" />
            </Field>

            <div className="flex gap-2">
              <Button type="submit" disabled={createMutation.isPending}>
                {createMutation.isPending ? "Menyimpan..." : "Simpan Draft"}
              </Button>
              <Button type="button" variant="outline" asChild>
                <Link href="/dashboard/reconciliations">Batal</Link>
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
