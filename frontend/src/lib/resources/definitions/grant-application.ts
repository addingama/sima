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
    { accessorKey: "beneficiary_type_label", header: "Jenis" },
    { accessorKey: "recipient_name", header: "Penerima" },
    currencyColumn("recommended_amount", "Nominal usulan"),
    statusColumn(),
    nestedNameColumn("assigned_verifier", "Verifikator"),
    nestedNameColumn("assigned_handover", "Petugas serah terima"),
  ],
  filters: [
    {
      name: "beneficiary_type",
      label: "Jenis penerima",
      type: "select",
      allLabel: "Semua jenis",
      options: [
        { value: "individual", label: "Perorangan" },
        { value: "organization", label: "Organisasi / Instansi" },
      ],
    },
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
    {
      name: "beneficiary_type",
      label: "Jenis penerima",
      type: "select",
      required: true,
      options: [
        { value: "individual", label: "Perorangan" },
        { value: "organization", label: "Organisasi / Instansi" },
      ],
    },
    { name: "recipient_name", label: "Nama penerima / organisasi", type: "text", required: true },
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
      visibleWhen: (values) => values.beneficiary_type !== "organization",
    },
    {
      name: "organization_pic_name",
      label: "Nama PIC organisasi",
      type: "text",
      visibleWhen: (values) => values.beneficiary_type === "organization",
      helperText: "Boleh dilengkapi saat draft; wajib sebelum diajukan ke approval.",
    },
    {
      name: "organization_pic_contact",
      label: "Kontak PIC",
      type: "text",
      visibleWhen: (values) => values.beneficiary_type === "organization",
    },
    {
      name: "organization_pic_relationship",
      label: "Hubungan PIC dengan organisasi",
      type: "text",
      visibleWhen: (values) => values.beneficiary_type === "organization",
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
    {
      name: "recommender_name",
      label: "Sumber rekomendasi eksternal",
      type: "text",
      helperText: "Opsional jika informasi berasal dari pihak lain di luar akun pengaju.",
    },
    { name: "recommender_contact", label: "Kontak sumber eksternal", type: "text" },
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
      name: "bank_account_owner_type",
      label: "Pemilik rekening",
      type: "select",
      visibleWhen: (values) => values.payment_method === "transfer" && values.beneficiary_type === "organization",
      options: [
        { value: "beneficiary", label: "Organisasi" },
        { value: "pic", label: "PIC / rekening pribadi" },
      ],
    },
    {
      name: "bank_account_holder_relationship",
      label: "Hubungan pemilik rekening",
      type: "text",
      visibleWhen: (values) =>
        values.payment_method === "transfer" &&
        values.beneficiary_type === "organization" &&
        values.bank_account_owner_type === "pic",
    },
    {
      name: "bank_account_use_reason",
      label: "Alasan memakai rekening pribadi",
      type: "textarea",
      visibleWhen: (values) =>
        values.payment_method === "transfer" &&
        values.beneficiary_type === "organization" &&
        values.bank_account_owner_type === "pic",
    },
    {
      name: "program_id",
      label: "Program",
      type: "relation",
      relation: { resource: "/programs", labelKey: "name", params: { is_active: 1, per_page: 100 } },
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
    { label: "Jenis penerima", accessor: "beneficiary_type_label" },
    { label: "Penerima", accessor: "recipient_name" },
    { label: "Telepon", accessor: "recipient_phone" },
    { label: "Alamat", accessor: "recipient_address" },
    { label: "Identitas", accessor: "recipient_identity_number" },
    { label: "PIC organisasi", accessor: "organization_pic_name" },
    { label: "Kontak PIC", accessor: "organization_pic_contact" },
    { label: "Hubungan PIC", accessor: "organization_pic_relationship" },
    { label: "Nominal usulan", accessor: "recommended_amount", type: "currency" },
    { label: "Nominal verifikasi", accessor: "verified_amount", type: "currency" },
    { label: "Nominal disetujui", accessor: "approved_amount", type: "currency" },
    { label: "Alasan", accessor: "reason" },
    { label: "Sumber rekomendasi eksternal", accessor: "recommender_name" },
    { label: "Kontak sumber eksternal", accessor: "recommender_contact" },
    { label: "Cara bayar", accessor: "payment_method" },
    { label: "Bank", accessor: "bank_name" },
    { label: "No. rekening", accessor: "bank_account_number" },
    { label: "Atas nama", accessor: "bank_account_holder" },
    { label: "Hubungan pemilik rekening", accessor: "bank_account_holder_relationship" },
    { label: "Alasan rekening pribadi", accessor: "bank_account_use_reason" },
    { label: "Verifikator", accessor: "assigned_verifier.name" },
    { label: "Petugas serah terima", accessor: "assigned_handover.name" },
    { label: "Catatan verifikator", accessor: "verifier_notes" },
    { label: "Alasan pengembalian", accessor: "return_reason" },
    { label: "Alasan penolakan", accessor: "rejection_reason" },
    { label: "Tanggal serah terima", accessor: "handed_over_on", type: "date" },
    { label: "Penerima aktual", accessor: "handover_recipient_name" },
    { label: "Catatan penerima aktual", accessor: "handover_recipient_notes" },
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
  getCreateDefaults: () => ({
    beneficiary_type: "individual",
    payment_method: "cash",
    bank_account_owner_type: "beneficiary",
  }),
  mapToPayload: (values) => {
    const payload = { ...values };
    if (String(values.status ?? "") === "verification") {
      delete payload.recommended_amount;
    }
    delete payload.status;
    delete payload.status_label;
    delete payload.application_number;
    delete payload.assigned_verifier;
    delete payload.assigned_verifier_id;
    delete payload.assigned_handover;
    delete payload.assigned_handover_id;
    delete payload.attachments;
    delete payload.disbursement;
    delete payload.created_by;
    return payload;
  },
};
