"use client";

import { useMemo, useState } from "react";

import Link from "next/link";
import { useRouter } from "next/navigation";

import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowLeft, ArrowRight, CheckCircle2 } from "lucide-react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

import { OpeningBalanceLinesField } from "@/app/(main)/dashboard/opening-balances/_components/opening-balance-lines-field";
import { CurrencyDisplay } from "@/components/sima/currency-display";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useResourceCreate } from "@/hooks/use-resource-mutation";
import { useResourceQuery } from "@/hooks/use-resource-query";
import { ApiError } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { normalizeAmountString, parseAmount } from "@/lib/format/amount";
import { formatDate } from "@/lib/format/datetime";
import { type OpeningBalanceWizardFormValues, openingBalanceWizardSchema } from "@/lib/opening-balance/schema";
import type { OpeningBalanceBatch } from "@/lib/opening-balance/types";
import { useAuth } from "@/providers/auth-provider";

const STEPS = ["Informasi Cutover", "Baris Saldo", "Review & Posting"] as const;

function resolveName(rows: Record<string, unknown>[] | undefined, id: string, keys: string[]): string {
  const match = rows?.find((row) => String(row.id) === id);

  if (!match) {
    return `#${id}`;
  }

  for (const key of keys) {
    const value = match[key];

    if (value) {
      return String(value);
    }
  }

  return `#${id}`;
}

export default function OpeningBalanceWizard() {
  const router = useRouter();
  const { user } = useAuth();
  const [step, setStep] = useState(0);
  const createMutation = useResourceCreate<OpeningBalanceBatch>("/opening-balances");

  const { data: accountsData } = useResourceQuery<Record<string, unknown>>(
    "/accounts",
    { is_active: 1, per_page: 100 },
    step >= 2,
  );
  const { data: fundsData } = useResourceQuery<Record<string, unknown>>(
    "/funds",
    { is_active: 1, per_page: 100 },
    step >= 2,
  );

  const form = useForm<OpeningBalanceWizardFormValues>({
    resolver: zodResolver(openingBalanceWizardSchema),
    defaultValues: {
      opening_date: new Date().toISOString().slice(0, 10),
      reference: "",
      lines: [{ account_id: "", fund_id: "", amount: "" }],
    },
  });

  const watchedLines = form.watch("lines");
  const totalAmount = useMemo(
    () => watchedLines.reduce((sum, line) => sum + parseAmount(line.amount), 0),
    [watchedLines],
  );

  if (!hasPermission(user, "opening.manage")) {
    return <ErrorState title="Akses ditolak" description="Hanya admin yang dapat memposting saldo awal go-live." />;
  }

  const goNext = async () => {
    if (step === 0) {
      const valid = await form.trigger(["opening_date", "reference"]);

      if (valid) {
        setStep(1);
      }

      return;
    }

    if (step === 1) {
      const valid = await form.trigger("lines");

      if (valid) {
        setStep(2);
      }
    }
  };

  const onSubmit = async (values: OpeningBalanceWizardFormValues) => {
    const payload = {
      opening_date: values.opening_date,
      reference: values.reference?.trim() || undefined,
      lines: values.lines.map((line) => ({
        account_id: Number(line.account_id),
        fund_id: Number(line.fund_id),
        amount: normalizeAmountString(line.amount),
      })),
    };

    try {
      const result = await createMutation.mutateAsync(payload);

      toast.success("Saldo awal berhasil diposting ke ledger.");
      router.push(`/dashboard/opening-balances/${result.id}`);
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal memposting saldo awal.");
    }
  };

  const values = form.getValues();

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Posting Saldo Awal"
        description="Wizard go-live — posting batch saldo awal kas/bank ke Amanah Ledger. Tindakan ini tidak dapat diedit; koreksi hanya lewat reversal."
        actions={
          <Button variant="outline" asChild>
            <Link href="/dashboard/opening-balances">
              <ArrowLeft className="size-4" />
              Kembali
            </Link>
          </Button>
        }
      />

      <div className="flex flex-wrap gap-2">
        {STEPS.map((label, index) => (
          <div
            key={label}
            className={`rounded-full border px-3 py-1 text-sm ${
              index === step ? "border-primary bg-primary/10 font-medium" : "text-muted-foreground"
            }`}
          >
            {index + 1}. {label}
          </div>
        ))}
      </div>

      <form className="space-y-6" onSubmit={form.handleSubmit(onSubmit)}>
        {step === 0 ? (
          <Card>
            <CardHeader>
              <CardTitle>Informasi Cutover</CardTitle>
            </CardHeader>
            <CardContent className="grid max-w-xl gap-4">
              <Controller
                control={form.control}
                name="opening_date"
                render={({ field, fieldState }) => (
                  <Field>
                    <FieldLabel htmlFor="opening_date">Tanggal Cutover</FieldLabel>
                    <Input id="opening_date" type="date" {...field} />
                    {fieldState.error ? <p className="text-destructive text-xs">{fieldState.error.message}</p> : null}
                  </Field>
                )}
              />
              <Controller
                control={form.control}
                name="reference"
                render={({ field }) => (
                  <Field>
                    <FieldLabel htmlFor="reference">Referensi / Catatan</FieldLabel>
                    <Input id="reference" {...field} placeholder="Mis. Go-live SIMA Januari 2026" />
                  </Field>
                )}
              />
            </CardContent>
          </Card>
        ) : null}

        {step === 1 ? <OpeningBalanceLinesField control={form.control} /> : null}

        {step === 2 ? (
          <Card>
            <CardHeader>
              <CardTitle>Review Sebelum Posting</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <dl className="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-muted-foreground">Tanggal cutover</dt>
                  <dd className="font-medium">{formatDate(values.opening_date)}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Referensi</dt>
                  <dd className="font-medium">{values.reference?.trim() || "-"}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Total batch</dt>
                  <dd className="font-medium">
                    <CurrencyDisplay value={totalAmount} />
                  </dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Jumlah baris</dt>
                  <dd className="font-medium">{values.lines.length}</dd>
                </div>
              </dl>

              <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 text-left">
                    <tr>
                      <th className="px-3 py-2">#</th>
                      <th className="px-3 py-2">Rekening</th>
                      <th className="px-3 py-2">Dana Amanah</th>
                      <th className="px-3 py-2 text-right">Nominal</th>
                    </tr>
                  </thead>
                  <tbody>
                    {values.lines.map((line, index) => (
                      <tr key={`${line.account_id}-${line.fund_id}-${index}`} className="border-t">
                        <td className="px-3 py-2">{index + 1}</td>
                        <td className="px-3 py-2">
                          {resolveName(accountsData?.rows, line.account_id, ["name", "code"])}
                        </td>
                        <td className="px-3 py-2">{resolveName(fundsData?.rows, line.fund_id, ["name", "code"])}</td>
                        <td className="px-3 py-2 text-right">
                          <CurrencyDisplay value={parseAmount(line.amount)} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <p className="text-muted-foreground text-sm">
                Setelah dikonfirmasi, batch akan langsung diposting ke ledger dengan tipe transaksi{" "}
                <strong>opening</strong>. Pastikan worksheet sudah disetujui ketua sebelum melanjutkan.
              </p>
            </CardContent>
          </Card>
        ) : null}

        <div className="flex flex-wrap justify-between gap-3">
          <Button
            type="button"
            variant="outline"
            disabled={step === 0}
            onClick={() => setStep((current) => current - 1)}
          >
            Sebelumnya
          </Button>

          {step < 2 ? (
            <Button type="button" onClick={goNext}>
              Lanjut
              <ArrowRight className="size-4" />
            </Button>
          ) : (
            <Button type="submit" disabled={createMutation.isPending}>
              <CheckCircle2 className="size-4" />
              {createMutation.isPending ? "Memproses..." : "Posting ke Ledger"}
            </Button>
          )}
        </div>
      </form>
    </div>
  );
}
