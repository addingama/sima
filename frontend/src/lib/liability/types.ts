export type LiabilityRelation = {
  id: number;
  code?: string;
  name?: string;
};

export type SettledDisbursement = {
  id: number;
  disbursement_number?: string;
  amount?: string;
  status?: string;
};

export type OperationalLiability = {
  id: number;
  liability_number: string;
  liability_date: string;
  creditor: string;
  description?: string | null;
  fund_id?: number | null;
  program_id?: number | null;
  amount: string;
  amount_settled: string;
  due_date?: string | null;
  status: string;
  settled_disbursement_id?: number | null;
  settled_at?: string | null;
  voided_at?: string | null;
  void_reason?: string | null;
  fund?: LiabilityRelation;
  program?: LiabilityRelation;
  settled_disbursement?: SettledDisbursement;
  created_at?: string;
  updated_at?: string;
};
