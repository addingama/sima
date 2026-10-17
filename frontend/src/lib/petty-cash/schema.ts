import { z } from "zod";

import { parseAmount } from "@/lib/format/amount";

// ─── Float ───────────────────────────────────────────────────────────────────

export const pettyCashFloatSchema = z.object({
  title: z.string().min(1, "Judul wajib diisi.").max(255),
  purpose: z.enum(["operational", "event"]),
  custodian_user_id: z.string().min(1, "PJ wajib dipilih."),
  program_id: z.string().optional(),
  period_start: z.string().optional(),
  period_end: z.string().optional(),
  notes: z.string().optional(),
});

export type PettyCashFloatFormValues = z.infer<typeof pettyCashFloatSchema>;

// ─── Line ─────────────────────────────────────────────────────────────────────

export const pettyCashLineSchema = z.object({
  category_id: z.string().min(1, "Kategori wajib dipilih."),
  spent_on: z.string().min(1, "Tanggal belanja wajib diisi."),
  amount: z
    .string()
    .min(1, "Nominal wajib diisi.")
    .refine((v) => parseAmount(v) > 0, "Nominal harus lebih besar dari nol."),
  description: z.string().min(1, "Keterangan wajib diisi.").max(500),
});

export type PettyCashLineFormValues = z.infer<typeof pettyCashLineSchema>;

// ─── Topup request ───────────────────────────────────────────────────────────

export const pettyCashTopupRequestSchema = z.object({
  amount: z
    .string()
    .min(1, "Nominal wajib diisi.")
    .refine((v) => parseAmount(v) > 0, "Nominal harus lebih besar dari nol."),
  reason: z.string().min(1, "Alasan wajib diisi.").max(1000),
});

export type PettyCashTopupRequestFormValues = z.infer<typeof pettyCashTopupRequestSchema>;

// ─── Close ───────────────────────────────────────────────────────────────────

export const pettyCashCloseSchema = z.object({
  returned_amount: z
    .string()
    .optional()
    .refine((v) => v === undefined || v === "" || parseAmount(v) >= 0, "Nominal harus ≥ 0."),
  account_id: z.string().optional(),
  receipt_date: z.string().optional(),
});

export type PettyCashCloseFormValues = z.infer<typeof pettyCashCloseSchema>;

// ─── Disbursement (initial funding) ──────────────────────────────────────────

export const pettyCashDisbursementSchema = z.object({
  amount: z
    .string()
    .min(1, "Nominal wajib diisi.")
    .refine((v) => parseAmount(v) > 0, "Nominal harus lebih besar dari nol."),
  disbursement_date: z.string().min(1, "Tanggal wajib diisi."),
  account_id: z.string().min(1, "Rekening wajib dipilih."),
  fund_id: z.string().min(1, "Dana Amanah wajib dipilih."),
  description: z.string().optional(),
  notes: z.string().optional(),
});

export type PettyCashDisbursementFormValues = z.infer<typeof pettyCashDisbursementSchema>;

// ─── Category ────────────────────────────────────────────────────────────────

export const pettyCashCategorySchema = z.object({
  code: z.string().min(1, "Kode wajib diisi.").max(50),
  name: z.string().min(1, "Nama wajib diisi.").max(255),
  description: z.string().optional(),
  sort_order: z.string().optional(),
});

export type PettyCashCategoryFormValues = z.infer<typeof pettyCashCategorySchema>;

// ─── Topup reject ────────────────────────────────────────────────────────────

export const pettyCashTopupRejectSchema = z.object({
  resolution_notes: z.string().min(1, "Catatan penolakan wajib diisi.").max(1000),
});

export type PettyCashTopupRejectFormValues = z.infer<typeof pettyCashTopupRejectSchema>;
