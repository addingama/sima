export interface ApprovalRecord {
  id: number;
  action: string;
  actor?: { id: number; name: string } | null;
  actor_role: string | null;
  notes: string | null;
  acted_at: string;
}

export interface AttachmentRecord {
  id: number;
  title: string | null;
  original_name: string;
  mime_type: string;
  size: number;
  url: string;
  uploaded_by: number | null;
  uploader?: { id: number; name: string } | null;
  created_at: string;
}

export interface AuditRecord {
  id: number;
  event: string;
  auditable_type: string;
  auditable_id: number;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  tags: string[] | null;
  user?: { id: number; name: string } | null;
  created_at: string;
}

export interface Donor {
  id: number;
  code: string;
  name: string;
  type: "individu" | "lembaga";
  email: string | null;
  phone: string | null;
  identity_number: string | null;
  address: string | null;
  notes: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface Fund {
  id: number;
  code: string;
  name: string;
  description: string | null;
  type: "restricted" | "unrestricted" | "operational";
  is_system: boolean;
  is_active: boolean;
  balance?: string;
  created_at: string;
  updated_at: string;
}

export interface Account {
  id: number;
  code: string;
  name: string;
  type: "cash" | "bank";
  bank_name: string | null;
  account_number: string | null;
  account_holder: string | null;
  is_active: boolean;
  balance?: string;
  created_at: string;
  updated_at: string;
}

export interface Program {
  id: number;
  fund_id: number | null;
  code: string;
  name: string;
  description: string | null;
  budget: string | null;
  start_date: string | null;
  end_date: string | null;
  status: "planned" | "active" | "closed";
  is_active: boolean;
  fund?: Fund | null;
  created_at: string;
  updated_at: string;
}

export interface ReceiptAllocation {
  id?: number;
  fund_id: number;
  program_id?: number | null;
  amount: string;
  note?: string | null;
  status?: string;
  fund?: Fund | null;
  program?: Program | null;
}

export interface Receipt {
  id: number;
  receipt_number: string | null;
  receipt_date: string;
  account_id: number;
  donor_id: number | null;
  channel: "cash" | "transfer" | "qris" | "other";
  reference_number: string | null;
  amount: string;
  description: string | null;
  status: string;
  rejection_reason?: string | null;
  reversal_reason?: string | null;
  account?: Account | null;
  donor?: Donor | null;
  allocations?: ReceiptAllocation[];
  approvals?: ApprovalRecord[];
  attachments?: AttachmentRecord[];
  created_at: string;
  updated_at: string;
}

export interface ExpenseFundSource {
  id?: number;
  fund_id: number;
  program_id?: number | null;
  amount: string;
  note?: string | null;
  fund?: Fund | null;
  program?: Program | null;
}

export interface Disbursement {
  id: number;
  disbursement_number: string | null;
  disbursement_date: string;
  account_id: number;
  program_id: number | null;
  amount: string;
  payee: string | null;
  category: string | null;
  reference_number: string | null;
  description: string | null;
  status: string;
  rejection_reason?: string | null;
  reversal_reason?: string | null;
  account?: Account | null;
  program?: Program | null;
  fund_sources?: ExpenseFundSource[];
  approvals?: ApprovalRecord[];
  attachments?: AttachmentRecord[];
  created_at: string;
  updated_at: string;
}

export interface AllocationLineInput {
  fund_id: number;
  program_id?: number | null;
  amount: string;
  note?: string;
}

export interface SourceLineInput {
  fund_id: number;
  program_id?: number | null;
  amount: string;
  note?: string;
}
