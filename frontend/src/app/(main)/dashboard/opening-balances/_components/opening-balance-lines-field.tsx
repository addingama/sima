"use client";

import { Plus, Trash2 } from "lucide-react";
import { type Control, Controller, useFieldArray } from "react-hook-form";

import { RelationSelect } from "@/components/sima/crud/relation-select";
import { MoneyInput } from "@/components/sima/money-input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import type { OpeningBalanceWizardFormValues } from "@/lib/opening-balance/schema";

const emptyLine = {
  account_id: "",
  fund_id: "",
  amount: "",
};

export function OpeningBalanceLinesField({ control }: { control: Control<OpeningBalanceWizardFormValues> }) {
  const { fields, append, remove } = useFieldArray({
    control,
    name: "lines",
  });

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-4">
        <CardTitle>Pemetaan Saldo Awal</CardTitle>
        <Button type="button" variant="outline" size="sm" onClick={() => append(emptyLine)}>
          <Plus className="size-4" />
          Tambah Baris
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <p className="text-muted-foreground text-sm">
          Setiap baris = satu pasangan rekening kas/bank + Dana Amanah + nominal. Akun yang sudah pernah diposting saldo
          awal akan ditolak oleh sistem.
        </p>
        {fields.map((field, index) => (
          <div key={field.id} className="grid grid-cols-1 gap-3 rounded-lg border p-4 md:grid-cols-12">
            <Controller
              control={control}
              name={`lines.${index}.account_id`}
              render={({ field: formField, fieldState }) => (
                <Field className="md:col-span-4">
                  <FieldLabel htmlFor={`line-account-${index}`}>Rekening Kas/Bank</FieldLabel>
                  <RelationSelect
                    resource="/accounts"
                    labelKey="name"
                    params={{ is_active: 1, per_page: 100 }}
                    value={String(formField.value ?? "")}
                    onChange={formField.onChange}
                    placeholder="Pilih rekening..."
                  />
                  {fieldState.error ? <p className="text-destructive text-xs">{fieldState.error.message}</p> : null}
                </Field>
              )}
            />
            <Controller
              control={control}
              name={`lines.${index}.fund_id`}
              render={({ field: formField, fieldState }) => (
                <Field className="md:col-span-4">
                  <FieldLabel htmlFor={`line-fund-${index}`}>Dana Amanah</FieldLabel>
                  <RelationSelect
                    resource="/funds"
                    labelKey="name"
                    params={{ is_active: 1, per_page: 100 }}
                    value={String(formField.value ?? "")}
                    onChange={formField.onChange}
                    placeholder="Pilih dana..."
                  />
                  {fieldState.error ? <p className="text-destructive text-xs">{fieldState.error.message}</p> : null}
                </Field>
              )}
            />
            <Controller
              control={control}
              name={`lines.${index}.amount`}
              render={({ field: formField, fieldState }) => (
                <Field className="md:col-span-3">
                  <FieldLabel htmlFor={`line-amount-${index}`}>Nominal</FieldLabel>
                  <MoneyInput
                    id={`line-amount-${index}`}
                    value={formField.value ?? ""}
                    onChange={formField.onChange}
                    onBlur={formField.onBlur}
                  />
                  {fieldState.error ? <p className="text-destructive text-xs">{fieldState.error.message}</p> : null}
                </Field>
              )}
            />
            <div className="flex items-end md:col-span-1">
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => remove(index)}
                disabled={fields.length <= 1}
                aria-label="Hapus baris"
              >
                <Trash2 className="size-4" />
              </Button>
            </div>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
