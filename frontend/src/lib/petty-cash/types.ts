export type PettyCashPurpose = "operational" | "event";
export type PettyCashFloatStatus = "draft" | "reporting" | "closed" | "cancelled";
export type PettyCashFundingKind = "initial" | "topup";
export type PettyCashTopupRequestStatus = "pending" | "funded" | "rejected";

export type PettyCashCustodian = {
  id: number;
  name: string;
  email?: string | null;
};

export type PettyCashFunding = {
  id: number;
  kind: PettyCashFundingKind;
  amount: string;
  disbursement_id?: number | null;
  disbursement?: {
    id: number;
    disbursement_number: string;
    status: string;
    amount: string;
    disbursement_date: string;
  } | null;
  notes?: string | null;
};

export type PettyCashTopupRequest = {
  id: number;
  petty_cash_float_id: number;
  requested_by: number;
  amount: string;
  reason: string;
  status: PettyCashTopupRequestStatus;
  resolution_notes?: string | null;
  resolved_at?: string | null;
  created_at?: string;
  updated_at?: string;
};

export type PettyCashCategory = {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  is_active: boolean;
  sort_order?: number | null;
  created_at?: string;
  updated_at?: string;
};

export type PettyCashLine = {
  id: number;
  petty_cash_float_id: number;
  category_id: number;
  category?: {
    id: number;
    code: string;
    name: string;
  } | null;
  spent_on: string;
  amount: string;
  description: string;
  created_by?: number | null;
  created_at?: string;
  updated_at?: string;
};

export type PettyCashFloat = {
  id: number;
  float_number: string;
  title: string;
  purpose: PettyCashPurpose;
  status: PettyCashFloatStatus;
  custodian_user_id: number;
  custodian?: PettyCashCustodian | null;
  program_id?: number | null;
  program?: { id: number; name: string } | null;
  period_start?: string | null;
  period_end?: string | null;
  return_receipt_id?: number | null;
  returned_amount: string;
  funded_total?: string | null;
  spent_total?: string | null;
  remaining_total?: string | null;
  notes?: string | null;
  funded_at?: string | null;
  closed_at?: string | null;
  created_by?: number | null;
  created_by_user?: { id: number; name: string } | null;
  lines?: PettyCashLine[];
  fundings?: PettyCashFunding[];
  topup_requests?: PettyCashTopupRequest[];
  created_at?: string;
  updated_at?: string;
};
