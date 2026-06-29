import type { ColumnDef } from "@tanstack/react-table";
import type { ReactNode } from "react";

export type FieldType =
  | "text"
  | "email"
  | "textarea"
  | "select"
  | "number"
  | "date"
  | "currency"
  | "checkbox"
  | "relation";

export interface SelectOption {
  value: string;
  label: string;
}

export interface FormFieldDef {
  name: string;
  label: string;
  type: FieldType;
  required?: boolean;
  /** Kode/nomor dibuat server-side; field tampil read-only di form. */
  autoGenerate?: boolean;
  readOnly?: boolean;
  helperText?: string;
  placeholder?: string;
  options?: SelectOption[];
  relation?: {
    resource: string;
    labelKey: string;
    valueKey?: string;
    params?: Record<string, string | number | boolean>;
  };
  visibleWhen?: (values: Record<string, unknown>) => boolean;
  /** Tampil hanya pada form edit (mis. saldo computed). */
  showOnEditOnly?: boolean;
}

export interface FilterDef {
  name: string;
  label: string;
  type: "select" | "text";
  options?: SelectOption[];
  placeholder?: string;
  allLabel?: string;
}

export type DetailValue = string | number | boolean | null | undefined | ReactNode;

export interface DetailFieldDef {
  label: string;
  accessor: string | ((row: Record<string, unknown>) => DetailValue);
  type?: "text" | "currency" | "date" | "datetime" | "boolean" | "status";
}

export interface WorkflowActionDef {
  action: string;
  label: string;
  permission: string;
  statuses: string[];
  requiresReason?: boolean;
  reasonLabel?: string;
  reasonRequired?: boolean;
  notesOptional?: boolean;
  variant?: "default" | "destructive" | "outline";
  confirmTitle?: string;
  confirmDescription?: string;
}

export interface ResourceDef {
  resource: string;
  basePath: string;
  label: string;
  labelPlural: string;
  permissions: {
    view: string;
    manage?: string;
    create?: string;
    delete?: string;
  };
  titleField: string | ((row: Record<string, unknown>) => string);
  listColumns: ColumnDef<Record<string, unknown>, unknown>[];
  filters?: FilterDef[];
  defaultSort?: { field: string; direction: "asc" | "desc" };
  formFields: FormFieldDef[];
  detailFields: DetailFieldDef[];
  lineItems?: {
    key: "allocations" | "sources";
    label: string;
    amountField: string;
  };
  workflow?: WorkflowActionDef[];
  attachments?: {
    attachableType: "receipt" | "disbursement" | "bank_fee";
    managePermission: string;
  };
  audit?: {
    auditableType: string;
    permission: string;
  };
  canEdit?: (row: Record<string, unknown>) => boolean;
  canDelete?: (row: Record<string, unknown>) => boolean;
  getCreateDefaults?: () => Record<string, unknown>;
  mapToForm?: (row: Record<string, unknown>) => Record<string, unknown>;
  mapToPayload?: (values: Record<string, unknown>) => Record<string, unknown>;
}
