export interface ApiEnvelope<T = unknown> {
  success: boolean;
  message: string | null;
  data: T;
  meta: ApiMeta | null;
  errors: ApiErrors | null;
}

export interface ApiErrors {
  code?: string;
  fields?: Record<string, string[]>;
}

export interface ApiMeta {
  pagination?: PaginationMeta;
  total_amount?: string;
  batch_count?: number;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  q?: string;
  sort?: string;
  direction?: "asc" | "desc";
  [key: string]: string | number | boolean | undefined;
}

export interface SimaUser {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  is_active: boolean;
  roles: string[];
  permissions: string[];
}

export interface LoginResult {
  token: string;
  user: SimaUser;
}

export interface DashboardSummary {
  total_kas_bank: string;
  total_dana_amanah: string;
  penerimaan_bulan_ini: string;
  pengeluaran_bulan_ini: string;
  receipts_pending: number;
  disbursements_pending: number;
  generated_at: string;
}

export interface FundBalanceRow {
  id: number;
  code: string;
  name: string;
  type: string;
  is_system: boolean;
  balance: string;
}

export interface FundBalancesReport {
  rows: FundBalanceRow[];
  total: string;
}

export interface ReconciliationSummary {
  total_kas_bank: string;
  total_dana_amanah: string;
  total_debit: string;
  total_credit: string;
  selisih_kas_dana: string;
  selisih_debit_credit: string;
  seimbang: boolean;
}

export interface LedgerEntryRow {
  id: number;
  transaction_type: string;
  transaction_id: number;
  ledger_account_type: string;
  ledger_account_id: number;
  debit: string;
  credit: string;
  reference: string | null;
  created_at: string;
}
