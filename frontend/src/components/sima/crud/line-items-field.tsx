"use client";

import { Percent, Plus, Trash2 } from "lucide-react";
import { type Control, Controller, useFieldArray, useWatch } from "react-hook-form";
import { toast } from "sonner";

import { RelationSelect } from "@/components/sima/crud/relation-select";
import { MoneyInput } from "@/components/sima/money-input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useResourceQuery } from "@/hooks/use-resource-query";
import { parseAmount } from "@/lib/format/amount";

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
  amountField = "amount",
}: {
  control: Control<Record<string, unknown>>;
  name: LineItemsKey;
  label: string;
  amountField?: string;
}) {
  const { fields, append, remove, update } = useFieldArray({
    control: control as never,
    name: name as never,
  });
  const transactionAmount = useWatch({ control, name: amountField });
  const lines = (useWatch({ control, name }) ?? []) as Array<Record<string, unknown>>;
  const isReceiptAllocation = name === "allocations";
  const operationalFundQuery = useResourceQuery<Record<string, unknown>>(
    "/funds",
    { q: "SYS-OPERASIONAL", is_active: 1, per_page: 1 },
    isReceiptAllocation,
  );
  const operationalFund = operationalFundQuery.data?.rows.find((fund) => fund.code === "SYS-OPERASIONAL");

  function allocateOperationalTenPercent() {
    const total = parseAmount(transactionAmount as string | number | null | undefined);

    if (total <= 0) {
      toast.error("Isi nominal penerimaan terlebih dahulu.");

      return;
    }

    if (!operationalFund?.id) {
      toast.error("Dana Operasional sistem tidak ditemukan.");

      return;
    }

    const amount = String(Math.round(total * 0.1));
    const fundId = String(operationalFund.id);
    const operationalLine = {
      fund_id: fundId,
      program_id: "",
      amount,
      note: "Alokasi operasional 10%",
    };
    const existingIndex = lines.findIndex((line) => String(line.fund_id ?? "") === fundId);

    if (existingIndex >= 0) {
      update(existingIndex, {
        ...lines[existingIndex],
        ...operationalLine,
      });

      return;
    }

    const emptyIndex = lines.findIndex(
      (line) =>
        String(line.fund_id ?? "") === "" &&
        String(line.program_id ?? "") === "" &&
        String(line.amount ?? "") === "" &&
        String(line.note ?? "") === "",
    );

    if (emptyIndex >= 0) {
      update(emptyIndex, operationalLine);

      return;
    }

    append(operationalLine);
  }

  function fillRemainingAmount(index: number) {
    const total = parseAmount(transactionAmount as string | number | null | undefined);

    if (total <= 0) {
      toast.error("Isi nominal transaksi terlebih dahulu.");

      return;
    }

    const usedByOtherLines = lines.reduce((sum, line, lineIndex) => {
      if (lineIndex === index) {
        return sum;
      }

      return sum + parseAmount(line.amount as string | number | null | undefined);
    }, 0);
    const remaining = Math.round(total - usedByOtherLines);

    if (remaining <= 0) {
      toast.error("Tidak ada sisa nominal yang bisa diisi.");

      return;
    }

    update(index, {
      ...(lines[index] ?? emptyLine),
      amount: String(remaining),
    });
  }

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
        <CardTitle>{label}</CardTitle>
        <div className="flex flex-wrap gap-2">
          {isReceiptAllocation ? (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={allocateOperationalTenPercent}
              disabled={operationalFundQuery.isLoading}
            >
              <Percent className="size-4" />
              10% Operasional
            </Button>
          ) : null}
          <Button type="button" variant="outline" size="sm" onClick={() => append(emptyLine)}>
            <Plus className="size-4" />
            Tambah Baris
          </Button>
        </div>
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
                  <div className="space-y-2">
                    <MoneyInput
                      value={(formField.value ?? "") as string | number}
                      onChange={formField.onChange}
                      onBlur={formField.onBlur}
                    />
                    {String(formField.value ?? "") === "" ? (
                      <Button type="button" variant="ghost" size="xs" onClick={() => fillRemainingAmount(index)}>
                        Isi sisa
                      </Button>
                    ) : null}
                  </div>
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
