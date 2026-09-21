import { hasPermission } from "@/lib/auth/permissions";

import { currencyColumn, linkColumn, nestedNameColumn, statusColumn } from "../columns";
import type { ResourceDef } from "../types";

const basePath = "/dashboard/bantuan";

export const grantApplicationResource: ResourceDef = {
  resource: "/grant-applications",
  basePath,
  label: "Pengajuan Bantuan",
  labelPlural: "Pengajuan Bantuan",
  permissions: {
    view: "grant.view",
    create: "grant.create",
    update: "grant.update",
  },
  titleField: (row) => String(row.application_number ?? row.recipient_name ?? `Pengajuan #${row.id}`),
  listColumns: [
    linkColumn("application_number", "No. Pengajuan", basePath, (row) =>
      String(row.application_number ?? `#${row.id}`),
    ),
    { accessorKey: "recipient_name", header: "Penerima" },
    currencyColumn("recommended_amount", "Nominal usulan"),
    statusColumn(),
    nestedNameColumn("assigned_verifier", "Verifikator"),
  ],
  filters: [
    {
      name: "status",
      label: "Status",
      type: "select",
      allLabel: "Semua status",
      options: [
        { value: "draft", label: "Rekomendasi" },
        { value: "verification", label: "Verifikasi" },
        { value: "pending_approval", label: "Menunggu approval" },
        { value: "approved", label: "Siap diserahkan" },
        { value: "completed", label: "Selesai" },
        { value: "rejected", label: "Ditolak" },
      ],
    },
  ],
  defaultSort: { field: "created_at", direction: "desc" },
  formFields: [
    { name: "recipient_name", label: "Nama penerima", type: "text", required: true },
    {
      name: "recipient_phone",
      label: "Telepon penerima",
      type: "text",
      helperText: "Boleh dilengkapi saat verifikasi.",
    },
    {
      name: "recipient_address",
      label: "Alamat / cara menemui",
      type: "textarea",
      helperText: "Wajib sebelum approval. Verifikator mengisi jika masih kosong.",
    },
    {
      name: "recipient_identity_number",
      label: "NIK / nomor identitas",
      type: "text",
      helperText: "Wajib NIK atau lampiran judul identity sebelum approval.",
    },
    {
      name: "recommended_amount",
      label: "Nominal usulan",
      type: "currency",
      required: true,
      helperText: "Terkunci setelah kirim ke verifikasi. Koreksi nominal memakai hasil verifikasi.",
    },
    {
      name: "verified_amount",
      label: "Nominal hasil verifikasi",
      type: "currency",
      showOnEditOnly: true,
      helperText: "Default sama dengan usulan. Verifikator boleh mengubah.",
    },
    { name: "reason", label: "Alasan / jenis bantuan", type: "textarea", required: true },
    { name: "recommender_name", label: "Nama pemberi rekomendasi", type: "text", required: true },
    { name: "recommender_contact", label: "Kontak pemberi rekomendasi", type: "text" },
    {
      name: "payment_method",
      label: "Cara bayar",
      type: "select",
      required: true,
      options: [
        { value: "cash", label: "Tunai" },
        { value: "transfer", label: "Transfer" },
      ],
    },
    {
      name: "bank_name",
      label: "Bank",
      type: "text",
      visibleWhen: (values) => values.payment_method === "transfer",
    },
    {
      name: "bank_account_number",
      label: "No. rekening",
      type: "text",
      visibleWhen: (values) => values.payment_method === "transfer",
    },
    {
      name: "bank_account_holder",
      label: "Atas nama",
      type: "text",
      visibleWhen: (values) => values.payment_method === "transfer",
    },
    {
      name: "program_id",
      label: "Program",
      type: "relation",
      relation: { resource: "/programs", labelKey: "name", params: { is_active: 1, per_page: 100 } },
    },
    {
      name: "assigned_verifier_id",
      label: "Verifikator",
      type: "relation",
      showOnCreateOnly: true,
      helperText: "Boleh dikosongkan saat input. Setelah itu tugaskan dari halaman detail.",
      relation: { resource: "/grant-applications/verifiers", labelKey: "name", params: { per_page: 100 } },
    },
    { name: "notes", label: "Catatan", type: "textarea" },
    {
      name: "verifier_notes",
      label: "Catatan verifikator",
      type: "textarea",
      showOnEditOnly: true,
      helperText: "Wajib sebelum diajukan ke approval.",
    },
  ],
  detailFields: [
    { label: "No. Pengajuan", accessor: "application_number" },
    { label: "Status", accessor: "status_label" },
    { label: "Penerima", accessor: "recipient_name" },
    { label: "Telepon", accessor: "recipient_phone" },
    { label: "Alamat", accessor: "recipient_address" },
    { label: "Identitas", accessor: "recipient_identity_number" },
    { label: "Nominal usulan", accessor: "recommended_amount", type: "currency" },
    { label: "Nominal verifikasi", accessor: "verified_amount", type: "currency" },
    { label: "Nominal disetujui", accessor: "approved_amount", type: "currency" },
    { label: "Alasan", accessor: "reason" },
    { label: "Pemberi rekomendasi", accessor: "recommender_name" },
    { label: "Kontak rekomendasi", accessor: "recommender_contact" },
    { label: "Cara bayar", accessor: "payment_method" },
    { label: "Bank", accessor: "bank_name" },
    { label: "No. rekening", accessor: "bank_account_number" },
    { label: "Atas nama", accessor: "bank_account_holder" },
    { label: "Catatan verifikator", accessor: "verifier_notes" },
    { label: "Alasan pengembalian", accessor: "return_reason" },
    { label: "Alasan penolakan", accessor: "rejection_reason" },
    { label: "Tanggal serah terima", accessor: "handed_over_on", type: "date" },
  ],
  workflow: [
    {
      action: "send-to-verification",
      label: "Kirim ke verifikasi",
      permission: "grant.view",
      statuses: ["draft"],
      confirmTitle: "Kirim ke verifikasi?",
      confirmDescription: "Verifikator akan melengkapi data penerima dan lampiran pendukung.",
    },
    {
      action: "submit-for-approval",
      label: "Ajukan ke approval",
      permission: "grant.verify",
      statuses: ["verification"],
      confirmTitle: "Berkas siap di-approval?",
      confirmDescription: "Pastikan data dasar sudah benar dan lampiran pendukung sudah diunggah.",
    },
    {
      action: "approve",
      label: "Setujui",
      permission: "grant.approve",
      statuses: ["pending_approval"],
      notesOptional: true,
      confirmTitle: "Setujui pengajuan bantuan?",
    },
    {
      action: "return",
      label: "Kembalikan",
      permission: "grant.view",
      statuses: ["pending_approval"],
      requiresReason: true,
      reasonLabel: "Alasan pengembalian",
      reasonRequired: true,
      variant: "outline",
      confirmTitle: "Kembalikan ke verifikasi?",
    },
    {
      action: "reject",
      label: "Tolak",
      permission: "grant.approve",
      statuses: ["pending_approval"],
      requiresReason: true,
      reasonLabel: "Alasan penolakan",
      reasonRequired: true,
      variant: "destructive",
      confirmTitle: "Tolak pengajuan?",
    },
  ],
  attachments: {
    attachableType: "grant_application",
    managePermission: "attachment.manage",
    helperText:
      "Verifikator: unggah data tambahan (KK, foto, surat). Identitas: judul identity. Foto serah terima tunai: judul handover.",
  },
  canEdit: (row, user) => {
    const status = String(row.status ?? "");
    if (status === "draft") {
      return true;
    }

    if (status !== "verification") {
      return false;
    }

    if (hasPermission(user, "*") || user?.roles.includes("admin")) {
      return true;
    }

    return Number(row.assigned_verifier_id) === Number(user?.id);
  },
  getCreateDefaults: () => ({ payment_method: "cash" }),
  mapToPayload: (values) => {
    const payload = { ...values };
    if (String(values.status ?? "") === "verification") {
      delete payload.recommended_amount;
      delete payload.assigned_verifier_id;
    }
    delete payload.status;
    delete payload.status_label;
    delete payload.application_number;
    delete payload.assigned_verifier;
    delete payload.attachments;
    delete payload.disbursement;
    delete payload.created_by;
    return payload;
  },
};
