"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";

import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowLeft } from "lucide-react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

import { RelationSelect } from "@/components/sima/crud/relation-select";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useResourceCreate } from "@/hooks/use-resource-mutation";
import { ApiError } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { type PettyCashFloatFormValues, pettyCashFloatSchema } from "@/lib/petty-cash/schema";
import type { PettyCashFloat } from "@/lib/petty-cash/types";
import { useAuth } from "@/providers/auth-provider";

export default function CreatePettyCashFloatPage() {
  const router = useRouter();
  const { user } = useAuth();
  const createMutation = useResourceCreate<PettyCashFloat>("/petty-cash-floats");

  const form = useForm<PettyCashFloatFormValues>({
    resolver: zodResolver(pettyCashFloatSchema),
    defaultValues: {
      title: "",
      purpose: "operational",
      custodian_user_id: "",
      program_id: "",
      period_start: "",
      period_end: "",
      notes: "",
    },
  });

  const watchPurpose = form.watch("purpose");

  if (!hasPermission(user, "petty_cash.create")) {
    return (
      <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk membuat float kas kecil." />
    );
  }

  const onSubmit = async (values: PettyCashFloatFormValues) => {
    try {
      const payload = {
        title: values.title.trim(),
        purpose: values.purpose,
        custodian_user_id: Number(values.custodian_user_id),
        program_id: values.program_id ? Number(values.program_id) : null,
        period_start: values.period_start || null,
        period_end: values.period_end || null,
        notes: values.notes?.trim() || null,
      };
      const result = await createMutation.mutateAsync(payload);
      toast.success("Float kas kecil dibuat.");
      router.push(`/dashboard/kas-kecil/${result.id}`);
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal menyimpan.");
    }
  };

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Buat Float Kas Kecil"
        description="Float adalah kantong uang muka untuk satu periode operasional atau satu event."
        actions={
          <Button variant="outline" asChild>
            <Link href="/dashboard/kas-kecil">
              <ArrowLeft className="size-4" />
              Kembali
            </Link>
          </Button>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>Informasi Float</CardTitle>
        </CardHeader>
        <CardContent>
          <form className="grid max-w-xl gap-4" onSubmit={form.handleSubmit(onSubmit)}>
            <Field>
              <FieldLabel>Judul</FieldLabel>
              <Input {...form.register("title")} placeholder="cth. Operasional Oktober 2026" />
              <FieldError>{form.formState.errors.title?.message}</FieldError>
            </Field>

            <Field>
              <FieldLabel>Tujuan</FieldLabel>
              <Controller
                control={form.control}
                name="purpose"
                render={({ field }) => (
                  <Select value={field.value} onValueChange={field.onChange}>
                    <SelectTrigger>
                      <SelectValue placeholder="Pilih tujuan..." />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="operational">Operasional</SelectItem>
                      <SelectItem value="event">Event</SelectItem>
                    </SelectContent>
                  </Select>
                )}
              />
              <FieldError>{form.formState.errors.purpose?.message}</FieldError>
            </Field>

            <Field>
              <FieldLabel>PJ (Penanggung Jawab)</FieldLabel>
              <Controller
                control={form.control}
                name="custodian_user_id"
                render={({ field }) => (
                  <RelationSelect
                    resource="/petty-cash-floats/custodians"
                    labelKey="name"
                    params={{ per_page: 100 }}
                    value={field.value}
                    onChange={field.onChange}
                    placeholder="Cari nama PJ..."
                    searchable
                  />
                )}
              />
              <FieldError>{form.formState.errors.custodian_user_id?.message}</FieldError>
            </Field>

            {watchPurpose === "event" ? (
              <Field>
                <FieldLabel>Event / Program (opsional)</FieldLabel>
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
                      placeholder="Cari event / program..."
                      searchable
                      allowClear
                    />
                  )}
                />
              </Field>
            ) : null}

            {watchPurpose === "operational" ? (
              <div className="grid grid-cols-2 gap-4">
                <Field>
                  <FieldLabel>Periode Mulai (opsional)</FieldLabel>
                  <Input type="date" {...form.register("period_start")} />
                  <FieldError>{form.formState.errors.period_start?.message}</FieldError>
                </Field>
                <Field>
                  <FieldLabel>Periode Akhir (opsional)</FieldLabel>
                  <Input type="date" {...form.register("period_end")} />
                  <FieldError>{form.formState.errors.period_end?.message}</FieldError>
                </Field>
              </div>
            ) : null}

            <Field>
              <FieldLabel>Catatan (opsional)</FieldLabel>
              <Textarea rows={3} {...form.register("notes")} />
            </Field>

            <div className="flex gap-2">
              <Button type="submit" disabled={createMutation.isPending}>
                {createMutation.isPending ? "Menyimpan..." : "Buat Float"}
              </Button>
              <Button type="button" variant="outline" asChild>
                <Link href="/dashboard/kas-kecil">Batal</Link>
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
