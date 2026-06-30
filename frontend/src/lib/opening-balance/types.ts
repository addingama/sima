export interface OpeningBalanceLine {
  id: number;
  line_number: number;
  account_id: number;
  fund_id: number;
  amount: string;
  account?: { id: number; code: string; name: string };
  fund?: { id: number; code: string; name: string };
}

export interface OpeningBalanceBatch {
  id: number;
  batch_number: string;
  opening_date: string;
  reference: string | null;
  total_amount: string;
  posted_at: string;
  posted_by: number;
  lines?: OpeningBalanceLine[];
  posted_by_user?: { id: number; name: string; email: string };
  created_at?: string;
}

export interface OpeningBalanceLineInput {
  account_id: string;
  fund_id: string;
  amount: string;
}

export interface OpeningBalanceWizardValues {
  opening_date: string;
  reference: string;
  lines: OpeningBalanceLineInput[];
}
