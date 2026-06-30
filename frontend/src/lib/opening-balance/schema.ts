import { z } from "zod";

import { parseAmount } from "@/lib/format/amount";

const lineSchema = z.object({
  account_id: z.string().min(1, "Pilih rekening kas/bank."),
  fund_id: z.string().min(1, "Pilih Dana Amanah."),
  amount: z
    .string()
    .min(1, "Nominal wajib diisi.")
    .refine((value) => parseAmount(value) > 0, "Nominal harus lebih besar dari nol."),
});

export const openingBalanceWizardSchema = z.object({
  opening_date: z.string().min(1, "Tanggal cutover wajib diisi."),
  reference: z.string().optional(),
  lines: z.array(lineSchema).min(1, "Minimal satu baris saldo awal."),
});

export type OpeningBalanceWizardFormValues = z.infer<typeof openingBalanceWizardSchema>;
