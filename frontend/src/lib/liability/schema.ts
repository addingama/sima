import { z } from "zod";

import { parseAmount } from "@/lib/format/amount";

export const liabilityFormSchema = z.object({
  liability_date: z.string().min(1, "Tanggal wajib diisi."),
  creditor: z.string().min(1, "Kreditur wajib diisi.").max(255),
  description: z.string().optional(),
  fund_id: z.string().optional(),
  program_id: z.string().optional(),
  amount: z
    .string()
    .min(1, "Nominal wajib diisi.")
    .refine((value) => parseAmount(value) > 0, "Nominal harus lebih besar dari nol."),
  due_date: z.string().optional(),
});

export type LiabilityFormValues = z.infer<typeof liabilityFormSchema>;

export const settleLiabilitySchema = z.object({
  disbursement_id: z.string().min(1, "Pilih pengeluaran approved."),
});

export type SettleLiabilityFormValues = z.infer<typeof settleLiabilitySchema>;
