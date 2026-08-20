import { z } from "zod";

import { parseAmount } from "@/lib/format/amount";

export const createReconciliationSchema = z
  .object({
    account_id: z.string().min(1, "Pilih rekening kas/bank."),
    period_start: z.string().min(1, "Tanggal mulai periode wajib diisi."),
    period_end: z.string().min(1, "Tanggal akhir periode wajib diisi."),
    statement_balance: z
      .string()
      .min(1, "Saldo rekening koran wajib diisi.")
      .refine((value) => parseAmount(value) >= 0, "Saldo tidak boleh negatif."),
    notes: z.string().optional(),
  })
  .refine((values) => values.period_end >= values.period_start, {
    message: "Akhir periode harus sama atau setelah awal periode.",
    path: ["period_end"],
  });

export type CreateReconciliationFormValues = z.infer<typeof createReconciliationSchema>;

export const addReconciliationLineSchema = z.object({
  statement_date: z.string().optional(),
  statement_ref: z.string().optional(),
  statement_amount: z.string().optional(),
  ledger_entry_id: z.string().optional(),
  is_matched: z.boolean(),
  note: z.string().optional(),
});

export type AddReconciliationLineFormValues = z.infer<typeof addReconciliationLineSchema>;
