export type ReconciliationAccount = {
  id: number;
  code?: string;
  name?: string;
};

export type ReconciliationLine = {
  id: number;
  bank_reconciliation_id: number;
  ledger_entry_id?: number | null;
  statement_date?: string | null;
  statement_ref?: string | null;
  statement_amount?: string | null;
  is_matched: boolean;
  note?: string | null;
  created_at?: string;
};

export type ReconcilingItem = {
  type: string;
  bank_fee_id?: number;
  fee_number?: string;
  fee_date?: string;
  amount: string;
  operational_liability_id?: number | null;
  liability_number?: string | null;
  description?: string;
};

export type BankReconciliation = {
  id: number;
  account_id: number;
  period_start: string;
  period_end: string;
  statement_balance: string;
  system_balance: string;
  difference: string;
  status: string;
  notes?: string | null;
  reconciled_at?: string | null;
  account?: ReconciliationAccount;
  lines?: ReconciliationLine[];
  reconciling_items?: ReconcilingItem[];
  reconciling_total?: string;
  adjusted_difference?: string;
  created_at?: string;
};
