"use client";

import { useRouter } from "next/navigation";
import { useEffect, useMemo } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { buildCrudBreadcrumbs, CrudBreadcrumb } from "@/components/sima/crud/crud-breadcrumb";
import { CrudFormFields } from "@/components/sima/crud/crud-form-fields";
import { LineItemsField } from "@/components/sima/crud/line-items-field";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { PageShellSkeleton } from "@/components/sima/skeletons";
import { useDetailQuery } from "@/hooks/use-resource-query";
import { useResourceCreate, useResourceUpdate } from "@/hooks/use-resource-mutation";
import { ApiError } from "@/lib/api/client";
import { buildFormSchema, normalizeFormValues, nullifyEmptyOptionalFields } from "@/lib/resources/form-schema";
import type { ResourceDef } from "@/lib/resources/types";

export function CrudFormPage({
  config,
  id,
}: {
  config: ResourceDef;
  id?: string;
}) {
  const router = useRouter();
  const isEdit = Boolean(id);
  const createMutation = useResourceCreate(config.resource);
  const updateMutation = useResourceUpdate(config.resource, id ?? "0");
  const { data, isLoading, isError, refetch } = useDetailQuery<Record<string, unknown>>(
    config.resource,
    id ?? null,
    isEdit,
  );

  const schema = useMemo(
    () => buildFormSchema(config.formFields, config.lineItems?.key),
    [config.formFields, config.lineItems?.key],
  );

  const defaultValues = useMemo(() => {
    const base = config.getCreateDefaults?.() ?? {};

    for (const field of config.formFields) {
      if (base[field.name] === undefined) {
        base[field.name] = field.type === "checkbox" ? true : "";
      }
    }

    return base;
  }, [config]);

  const form = useForm<Record<string, unknown>>({
    resolver: zodResolver(schema),
    defaultValues,
    shouldUnregister: false,
  });

  useEffect(() => {
    if (isEdit && data) {
      const mapped = config.mapToForm?.(data) ?? data;
      form.reset(normalizeFormValues({ ...defaultValues, ...mapped }, config.formFields));
    }
  }, [isEdit, data, config, form, defaultValues]);

  const onSubmit = async (values: Record<string, unknown>) => {
    const normalized = nullifyEmptyOptionalFields(values, config.formFields);
    const payload = config.mapToPayload?.(normalized) ?? normalized;

    try {
      const result = isEdit
        ? await updateMutation.mutateAsync(payload)
        : await createMutation.mutateAsync(payload);

      toast.success(isEdit ? "Data berhasil diperbarui." : "Data berhasil dibuat.");
      router.push(`${config.basePath}/${(result as Record<string, unknown>).id ?? id}`);
    } catch (error) {
      if (error instanceof ApiError && error.fields) {
        for (const [field, messages] of Object.entries(error.fields)) {
          form.setError(field, { message: messages[0] });
        }
      }

      toast.error(error instanceof ApiError ? error.message : "Gagal menyimpan data.");
    }
  };

  if (isEdit && isLoading) {
    return <PageShellSkeleton />;
  }

  if (isEdit && (isError || !data)) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  if (isEdit && data && config.canEdit && !config.canEdit(data)) {
    return (
      <ErrorState
        title="Tidak dapat diedit"
        description="Data ini tidak lagi berstatus draft."
        onRetry={() => router.push(`${config.basePath}/${id}`)}
      />
    );
  }

  const breadcrumbs = buildCrudBreadcrumbs(config, isEdit ? "edit" : "create", data ?? undefined);

  return (
    <div className="flex flex-col gap-6">
      <CrudBreadcrumb items={breadcrumbs} />
      <PageHeader
        title={isEdit ? `Edit ${config.label}` : `Tambah ${config.label}`}
        description={isEdit ? "Perbarui data draft." : `Buat data ${config.label.toLowerCase()} baru.`}
      />

      <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Informasi Utama</CardTitle>
          </CardHeader>
          <CardContent>
            <CrudFormFields fields={config.formFields} control={form.control} isEdit={isEdit} />
          </CardContent>
        </Card>

        {config.lineItems ? (
          <LineItemsField control={form.control} name={config.lineItems.key} label={config.lineItems.label} />
        ) : null}

        <div className="flex flex-wrap gap-2">
          <Button type="submit" disabled={createMutation.isPending || updateMutation.isPending}>
            {createMutation.isPending || updateMutation.isPending ? "Menyimpan..." : "Simpan"}
          </Button>
          <Button type="button" variant="outline" onClick={() => router.back()}>
            Batal
          </Button>
        </div>
      </form>
    </div>
  );
}
