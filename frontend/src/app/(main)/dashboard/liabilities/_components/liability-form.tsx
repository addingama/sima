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
import { useResourceCreate, useResourceUpdate } from "@/hooks/use-resource-mutation";
import { ApiError } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { normalizeAmountString } from "@/lib/format/amount";
import { type LiabilityFormValues, liabilityFormSchema } from "@/lib/liability/schema";
import type { OperationalLiability } from "@/lib/liability/types";
import { useAuth } from "@/providers/auth-provider";

function toPayload(values: LiabilityFormValues) {
  return {
    liability_date: values.liability_date,
    creditor: values.creditor.trim(),
    description: values.description?.trim() || null,
    fund_id: values.fund_id ? Number(values.fund_id) : null,
    program_id: values.program_id ? Number(values.program_id) : null,
    amount: normalizeAmountString(values.amount),
    due_date: values.due_date || null,
  };
}

export function LiabilityForm({ mode, liability }: { mode: "create" | "edit"; liability?: OperationalLiability }) {
  const router = useRouter();
  const { user } = useAuth();
  const createMutation = useResourceCreate<OperationalLiability>("/liabilities");
  const updateMutation = useResourceUpdate<OperationalLiability>("/liabilities", liability?.id ?? 0);

  const form = useForm<LiabilityFormValues>({
    resolver: zodResolver(liabilityFormSchema),
    defaultValues: {
      liability_date: liability?.liability_date ?? new Date().toISOString().slice(0, 10),
      creditor: liability?.creditor ?? "",
      description: liability?.description ?? "",
      fund_id: liability?.fund_id ? String(liability.fund_id) : "",
      program_id: liability?.program_id ? String(liability.program_id) : "",
      amount: liability?.amount ? String(Math.round(Number.parseFloat(liability.amount))) : "",
      due_date: liability?.due_date ?? "",
    },
  });

  if (!hasPermission(user, "liability.manage")) {
    return (
      <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk mengelola liabilitas." />
    );
  }

  if (mode === "edit" && liability && liability.status !== "outstanding") {
    return (
      <ErrorState title="Tidak dapat diubah" description="Hanya liabilitas berstatus outstanding yang dapat diedit." />
    );
  }

  const onSubmit = async (values: LiabilityFormValues) => {
    try {
      const payload = toPayload(values);

      if (mode === "create") {
        const result = await createMutation.mutateAsync(payload);
        toast.success("Liabilitas dibuat.");
        router.push(`/dashboard/liabilities/${result.id}`);
        return;
      }

      if (!liability) {
        return;
      }

      await updateMutation.mutateAsync(payload);
      toast.success("Liabilitas diperbarui.");
      router.push(`/dashboard/liabilities/${liability.id}`);
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal menyimpan.");
    }
  };

  const pending = createMutation.isPending || updateMutation.isPending;

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={mode === "create" ? "Tambah Liabilitas" : `Edit ${liability?.liability_number ?? "Liabilitas"}`}
        description="Catat kewajiban operasional. Pembayaran dilakukan lewat pengeluaran yang kemudian di-settle."
        actions={
          <Button variant="outline" asChild>
            <Link href={liability ? `/dashboard/liabilities/${liability.id}` : "/dashboard/liabilities"}>
              <ArrowLeft className="size-4" />
              Kembali
            </Link>
          </Button>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>Informasi Liabilitas</CardTitle>
        </CardHeader>
        <CardContent>
          <form className="grid max-w-xl gap-4" onSubmit={form.handleSubmit(onSubmit)}>
            <Field>
              <FieldLabel>Tanggal</FieldLabel>
              <Input type="date" {...form.register("liability_date")} />
              <FieldError>{form.formState.errors.liability_date?.message}</FieldError>
            </Field>

            <Field>
              <FieldLabel>Kreditur</FieldLabel>
              <Input {...form.register("creditor")} placeholder="Nama pihak yang harus dibayar" />
              <FieldError>{form.formState.errors.creditor?.message}</FieldError>
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
              <FieldLabel>Dana Amanah (opsional)</FieldLabel>
              <Controller
                control={form.control}
                name="fund_id"
                render={({ field }) => (
                  <RelationSelect
                    resource="/funds"
                    labelKey="name"
                    params={{ is_active: 1, per_page: 100 }}
                    value={field.value ?? ""}
                    onChange={field.onChange}
                    placeholder="Pilih dana..."
                  />
                )}
              />
            </Field>

            <Field>
              <FieldLabel>Program / Event (opsional)</FieldLabel>
              <Controller
                control={form.control}
                name="program_id"
                render={({ field }) => (
                  <RelationSelect
                    resource="/programs"
                    labelKey="name"
                    params={{ is_active: 1, per_page: 100 }}
                    value={field.value ?? ""}
                    onChange={field.onChange}
                    placeholder="Pilih program..."
                  />
                )}
              />
            </Field>

            <Field>
              <FieldLabel>Jatuh Tempo (opsional)</FieldLabel>
              <Input type="date" {...form.register("due_date")} />
            </Field>

            <Field>
              <FieldLabel>Keterangan</FieldLabel>
              <Textarea rows={3} {...form.register("description")} />
            </Field>

            <div className="flex gap-2">
              <Button type="submit" disabled={pending}>
                {pending ? "Menyimpan..." : "Simpan"}
              </Button>
              <Button type="button" variant="outline" asChild>
                <Link href={liability ? `/dashboard/liabilities/${liability.id}` : "/dashboard/liabilities"}>
                  Batal
                </Link>
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
