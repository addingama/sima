import { z } from "zod";

import { parseAmount } from "@/lib/format/amount";
import type { FormFieldDef } from "@/lib/resources/types";

function toFormString(value: unknown): string {
  if (value === null || value === undefined) {
    return "";
  }

  return String(value);
}

function optionalStringSchema() {
  return z.preprocess(toFormString, z.string());
}

function requiredStringSchema(label: string) {
  return z.preprocess(toFormString, z.string().min(1, `${label} wajib diisi.`));
}

function optionalEmailSchema() {
  return z.preprocess(toFormString, z.union([z.literal(""), z.string().email("Email tidak valid.")]));
}

function fieldSchema(field: FormFieldDef) {
  if (field.autoGenerate || field.showOnEditOnly) {
    return optionalStringSchema();
  }

  switch (field.type) {
    case "email":
      return field.required ? z.string().email("Email tidak valid.") : optionalEmailSchema();
    case "checkbox":
      return z.preprocess((value) => (value === null || value === undefined ? false : Boolean(value)), z.boolean());
    case "number":
    case "currency":
      return field.required
        ? requiredStringSchema(field.label).refine((value) => parseAmount(value) > 0, "Nominal harus lebih dari 0.")
        : optionalStringSchema().refine((value) => value === "" || parseAmount(value) > 0, "Nominal tidak valid.");
    case "relation":
      return field.required ? requiredStringSchema(field.label) : optionalStringSchema();
    default:
      return field.required ? requiredStringSchema(field.label) : optionalStringSchema();
  }
}

export function buildFormSchema(fields: FormFieldDef[], lineItemsKey?: "allocations" | "sources") {
  const shape: Record<string, z.ZodTypeAny> = {};

  for (const field of fields) {
    shape[field.name] = fieldSchema(field);
  }

  if (lineItemsKey) {
    shape[lineItemsKey] = z
      .array(
        z.object({
          fund_id: z.string().min(1, "Dana Amanah wajib dipilih."),
          program_id: z.string().optional(),
          amount: requiredStringSchema("Nominal").refine(
            (value) => parseAmount(value) > 0,
            "Nominal harus lebih dari 0.",
          ),
          note: z.string().optional(),
        }),
      )
      .min(1, "Minimal satu baris sumber/alokasi.");
  }

  return z.object(shape);
}

/** Normalisasi nilai API (null) ke bentuk form sebelum reset/submit. */
export function normalizeFormValues(values: Record<string, unknown>, fields: FormFieldDef[]): Record<string, unknown> {
  const normalized = { ...values };

  for (const field of fields) {
    const value = normalized[field.name];

    if (field.showOnEditOnly) {
      continue;
    }

    if (field.type === "checkbox") {
      normalized[field.name] = Boolean(value);
      continue;
    }

    if (value === null || value === undefined) {
      normalized[field.name] = "";
    }
  }

  return normalized;
}

/** Kosongkan string opsional jadi null untuk payload API nullable. */
export function nullifyEmptyOptionalFields(
  values: Record<string, unknown>,
  fields: FormFieldDef[],
): Record<string, unknown> {
  const payload = { ...values };

  for (const field of fields) {
    if (field.required || field.type === "checkbox" || field.autoGenerate || field.showOnEditOnly) {
      continue;
    }

    const value = payload[field.name];
    if (value === "" || value === null || value === undefined) {
      payload[field.name] = null;
    }
  }

  return payload;
}
