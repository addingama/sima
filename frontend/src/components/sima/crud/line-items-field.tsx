"use client";

import { Plus, Trash2 } from "lucide-react";
import { type Control, Controller, useFieldArray } from "react-hook-form";

import { RelationSelect } from "@/components/sima/crud/relation-select";
import { MoneyInput } from "@/components/sima/money-input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";

type LineItemsKey = "allocations" | "sources";

const emptyLine = {
  fund_id: "",
  program_id: "",
  amount: "",
  note: "",
};

export function LineItemsField({
  control,
  name,
  label,
}: {
  control: Control<Record<string, unknown>>;
  name: LineItemsKey;
  label: string;
}) {
  const { fields, append, remove } = useFieldArray({
    control: control as never,
    name: name as never,
  });

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-4">
        <CardTitle>{label}</CardTitle>
        <Button type="button" variant="outline" size="sm" onClick={() => append(emptyLine)}>
          <Plus className="size-4" />
          Tambah Baris
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        {fields.map((field, index) => (
          <div key={field.id} className="grid grid-cols-1 gap-3 rounded-lg border p-4 md:grid-cols-12">
            <Controller
              control={control}
              name={`${name}.${index}.fund_id`}
              render={({ field: formField }) => (
                <Field className="md:col-span-4">
                  <FieldLabel>Dana Amanah</FieldLabel>
                  <RelationSelect
                    resource="/funds"
                    labelKey="name"
                    params={{ is_active: 1, per_page: 100 }}
                    value={String(formField.value ?? "")}
                    onChange={formField.onChange}
                  />
                </Field>
              )}
            />
            <Controller
              control={control}
              name={`${name}.${index}.program_id`}
              render={({ field: formField }) => (
                <Field className="md:col-span-3">
                  <FieldLabel>Program</FieldLabel>
                  <RelationSelect
                    resource="/programs"
                    labelKey="name"
                    params={{ is_active: 1, per_page: 100 }}
                    value={String(formField.value ?? "")}
                    onChange={formField.onChange}
                  />
                </Field>
              )}
            />
            <Controller
              control={control}
              name={`${name}.${index}.amount`}
              render={({ field: formField }) => (
                <Field className="md:col-span-2">
                  <FieldLabel>Nominal</FieldLabel>
                  <MoneyInput
                    value={(formField.value ?? "") as string | number}
                    onChange={formField.onChange}
                    onBlur={formField.onBlur}
                  />
                </Field>
              )}
            />
            <Controller
              control={control}
              name={`${name}.${index}.note`}
              render={({ field: formField }) => (
                <Field className="md:col-span-2">
                  <FieldLabel>Catatan</FieldLabel>
                  <Input {...formField} value={String(formField.value ?? "")} placeholder="Opsional" />
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
