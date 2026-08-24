"use client";

import { Percent, Plus, Trash2 } from "lucide-react";
import { type Control, Controller, useFieldArray, useWatch } from "react-hook-form";
import { toast } from "sonner";

import { RelationSelect } from "@/components/sima/crud/relation-select";
import { CurrencyDisplay } from "@/components/sima/currency-display";
import { MoneyInput } from "@/components/sima/money-input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldDescription, FieldLabel } from "@/components/ui/field";
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
  const isExpenseSource = name === "sources";
  const showProgramColumn = isReceiptAllocation;
  const fundsQuery = useResourceQuery<Record<string, unknown>>(
    "/funds",
    { is_active: 1, per_page: 100 },
    isExpenseSource,
  );
  const operationalFundQuery = useResourceQuery<Record<string, unknown>>(
    "/funds",
    { q: "SYS-OPERASIONAL", is_active: 1, per_page: 1 },
    isReceiptAllocation,
  );
  const operationalFund = operationalFundQuery.data?.rows.find((fund) => fund.code === "SYS-OPERASIONAL");

  function fundBalance(fundId: string): number | null {
    if (!fundId) {
      return null;
    }

    const fund = fundsQuery.data?.rows.find((row) => String(row.id ?? "") === fundId);

    if (!fund) {
      return null;
    }

    return parseAmount(fund.balance as string | number | null | undefined);
  }

  function capAmountToFundBalance(index: number, value: string): string {
    if (!isExpenseSource || value === "") {
      return value;
    }

    const selectedFundId = String(lines[index]?.fund_id ?? "");
    const balance = fundBalance(selectedFundId);

    if (balance === null) {
      return value;
    }

    const amount = parseAmount(value);

    if (amount <= balance) {
      return value;
    }

    toast.error("Nominal melebihi saldo Dana Amanah. Nilai disesuaikan ke saldo tersedia.");

    return balance > 0 ? String(Math.round(balance)) : "";
  }

  function selectFund(index: number, value: string, onChange: (value: string) => void) {
    if (
      isExpenseSource &&
      value !== "" &&
      lines.some((line, lineIndex) => lineIndex !== index && String(line.fund_id ?? "") === value)
    ) {
      toast.error("Dana Amanah sudah dipilih di baris lain. Gabungkan nominal pada satu baris.");

      return;
    }

    onChange(value);
  }

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
    let remaining = Math.round(total - usedByOtherLines);

    if (remaining <= 0) {
      toast.error("Tidak ada sisa nominal yang bisa diisi.");

      return;
    }

    if (isExpenseSource) {
      const selectedFundId = String(lines[index]?.fund_id ?? "");
      const balance = fundBalance(selectedFundId);

      if (balance !== null && remaining > balance) {
        remaining = Math.round(balance);
        toast.error("Sisa nominal melebihi saldo Dana Amanah. Nilai disesuaikan ke saldo tersedia.");
      }

      if (remaining <= 0) {
        toast.error("Dana Amanah yang dipilih tidak memiliki saldo tersedia.");

        return;
      }
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
                <Field className={showProgramColumn ? "md:col-span-4" : "md:col-span-5"}>
                  <FieldLabel>Dana Amanah</FieldLabel>
                  <RelationSelect
                    resource="/funds"
                    labelKey="name"
                    params={{ is_active: 1, per_page: 100 }}
                    value={String(formField.value ?? "")}
                    onChange={(value) => selectFund(index, value, formField.onChange)}
                  />
                  {isExpenseSource && String(formField.value ?? "") !== "" ? (
                    <FieldDescription>
                      Saldo tersedia:{" "}
                      <CurrencyDisplay value={fundBalance(String(formField.value ?? "")) ?? 0} className="font-medium" />
                    </FieldDescription>
                  ) : null}
                </Field>
              )}
            />
            {showProgramColumn ? (
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
            ) : null}
            <Controller
              control={control}
              name={`${name}.${index}.amount`}
              render={({ field: formField }) => (
                <Field className={showProgramColumn ? "md:col-span-2" : "md:col-span-3"}>
                  <FieldLabel>Nominal</FieldLabel>
                  <div className="flex items-center gap-2">
                    <MoneyInput
                      value={(formField.value ?? "") as string | number}
                      onChange={(value) => formField.onChange(capAmountToFundBalance(index, value))}
                      onBlur={formField.onBlur}
                      className="min-w-0"
                    />
                    {String(formField.value ?? "") === "" ? (
                      <Button
                        type="button"
                        variant="outline"
                        size="xs"
                        className="shrink-0"
                        onClick={() => fillRemainingAmount(index)}
                      >
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
                <Field className={showProgramColumn ? "md:col-span-2" : "md:col-span-3"}>
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
