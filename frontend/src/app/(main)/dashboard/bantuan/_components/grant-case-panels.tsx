"use client";

import { useState } from "react";

import { toast } from "sonner";

import { RelationSelect } from "@/components/sima/crud/relation-select";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useWorkflowAction } from "@/hooks/use-resource-mutation";
import { ApiError, apiPost } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { useAuth } from "@/providers/auth-provider";

export function buildGrantCompletionPayload(
  row: Record<string, unknown>,
  handedOverOn: string,
  recipientName: string,
  recipientNotes: string,
  actualCount: string,
) {
  return {
    handed_over_on: handedOverOn,
    handover_recipient_name: recipientName || null,
    handover_recipient_notes: recipientNotes || null,
    actual_beneficiary_count: String(row.beneficiary_scope) === "collective" ? Number(actualCount) : null,
  };
}

export function GrantCasePanels({ row, onRefresh }: { row: Record<string, unknown>; onRefresh: () => void }) {
  const { user } = useAuth();
  const id = Number(row.id);
  const status = String(row.status ?? "");
  const assignMutation = useWorkflowAction("/grant-applications", id);
  const [verifierId, setVerifierId] = useState(String(row.assigned_verifier_id ?? ""));
  const [handoverOfficerId, setHandoverOfficerId] = useState(String(row.assigned_handover_id ?? ""));
  const [handoverAssignmentReason, setHandoverAssignmentReason] = useState("");
  const [handoverDate, setHandoverDate] = useState(new Date().toISOString().slice(0, 10));
  const [handoverRecipientName, setHandoverRecipientName] = useState(String(row.organization_pic_name ?? ""));
  const [handoverRecipientNotes, setHandoverRecipientNotes] = useState("");
  const [actualBeneficiaryCount, setActualBeneficiaryCount] = useState("");
  const [disbursementDate, setDisbursementDate] = useState(new Date().toISOString().slice(0, 10));
  const [accountId, setAccountId] = useState("");
  const [fundId, setFundId] = useState("");
  const [busy, setBusy] = useState(false);

  const canAssignVerifier = hasPermission(user, "grant.assign") && (status === "draft" || status === "verification");
  const canAssignHandover =
    hasPermission(user, "grant.assign") &&
    (status === "draft" || status === "verification" || status === "pending_approval" || status === "approved");
  const canCreateDisbursement =
    hasPermission(user, "disbursement.create") && status === "approved" && !row.disbursement_id;
  const canComplete =
    hasPermission(user, "grant.handover") &&
    status === "approved" &&
    (hasPermission(user, "*") || Number(row.assigned_handover_id) === Number(user?.id));

  if (!canAssignVerifier && !canAssignHandover && !canCreateDisbursement && !canComplete) {
    return null;
  }

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      {canAssignVerifier ? (
        <Card>
          <CardHeader>
            <CardTitle>Tugaskan verifikator</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <RelationSelect
              resource="/grant-applications/verifiers"
              labelKey="name"
              value={verifierId}
              onChange={setVerifierId}
              placeholder="Pilih verifikator"
            />
            <Button
              size="sm"
              disabled={assignMutation.isPending || !verifierId}
              onClick={async () => {
                try {
                  await assignMutation.mutateAsync({
                    action: "assign",
                    body: { assigned_verifier_id: Number(verifierId) },
                  });
                  toast.success("Verifikator ditugaskan.");
                  onRefresh();
                } catch (error) {
                  toast.error(error instanceof Error ? error.message : "Gagal menugaskan.");
                }
              }}
            >
              Simpan verifikator
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {canAssignHandover ? (
        <Card>
          <CardHeader>
            <CardTitle>Tugaskan petugas serah terima</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <RelationSelect
              resource="/grant-applications/handover-officers"
              labelKey="name"
              value={handoverOfficerId}
              onChange={setHandoverOfficerId}
              placeholder="Pilih petugas serah terima"
            />
            {status === "approved" ? (
              <div className="space-y-1">
                <Label htmlFor="grant-handover-assignment-reason">Alasan pergantian</Label>
                <Input
                  id="grant-handover-assignment-reason"
                  value={handoverAssignmentReason}
                  onChange={(event) => setHandoverAssignmentReason(event.target.value)}
                  placeholder="Wajib setelah pengajuan disetujui"
                />
              </div>
            ) : null}
            <Button
              size="sm"
              disabled={
                assignMutation.isPending ||
                !handoverOfficerId ||
                (status === "approved" && !handoverAssignmentReason.trim())
              }
              onClick={async () => {
                try {
                  await assignMutation.mutateAsync({
                    action: "assign",
                    body: {
                      assigned_handover_id: Number(handoverOfficerId),
                      reason: handoverAssignmentReason || null,
                    },
                  });
                  toast.success("Petugas serah terima ditugaskan.");
                  onRefresh();
                } catch (error) {
                  toast.error(error instanceof Error ? error.message : "Gagal menugaskan.");
                }
              }}
            >
              Simpan petugas
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {canCreateDisbursement ? (
        <Card>
          <CardHeader>
            <CardTitle>Buat pengeluaran</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-muted-foreground text-sm">
              Nominal terkunci {String(row.approved_amount)} atas nama {String(row.recipient_name)}.
            </p>
            <div className="space-y-1">
              <Label htmlFor="grant-disbursement-date">Tanggal</Label>
              <Input
                id="grant-disbursement-date"
                type="date"
                value={disbursementDate}
                onChange={(event) => setDisbursementDate(event.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label>Akun kas/bank</Label>
              <RelationSelect
                resource="/accounts"
                labelKey="name"
                params={{ is_active: 1, per_page: 100 }}
                value={accountId}
                onChange={setAccountId}
              />
            </div>
            <div className="space-y-1">
              <Label>Sumber Dana Amanah</Label>
              <RelationSelect
                resource="/funds"
                labelKey="name"
                params={{ is_active: 1, per_page: 100 }}
                value={fundId}
                onChange={setFundId}
              />
            </div>
            <Button
              size="sm"
              disabled={busy || !accountId || !fundId}
              onClick={async () => {
                setBusy(true);
                try {
                  await apiPost(`/grant-applications/${id}/disbursements`, {
                    disbursement_date: disbursementDate,
                    account_id: Number(accountId),
                    sources: [{ fund_id: Number(fundId), amount: String(row.approved_amount) }],
                  });
                  toast.success("Pengeluaran draft dibuat. Lanjutkan approve di menu Pengeluaran.");
                  onRefresh();
                } catch (error) {
                  toast.error(error instanceof ApiError ? error.message : "Gagal membuat pengeluaran.");
                } finally {
                  setBusy(false);
                }
              }}
            >
              Buat pengeluaran
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {canComplete ? (
        <Card>
          <CardHeader>
            <CardTitle>Serah terima</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-muted-foreground text-sm">
              Unggah foto judul handover di tab Lampiran, lalu tandai selesai setelah pengeluaran approved.
            </p>
            <div className="space-y-1">
              <Label htmlFor="grant-handover-date">Tanggal serah terima</Label>
              <Input
                id="grant-handover-date"
                type="date"
                value={handoverDate}
                onChange={(event) => setHandoverDate(event.target.value)}
              />
            </div>
            {String(row.beneficiary_type) === "organization" ? (
              <>
                <div className="space-y-1">
                  <Label htmlFor="grant-handover-recipient">Penerima aktual</Label>
                  <Input
                    id="grant-handover-recipient"
                    value={handoverRecipientName}
                    onChange={(event) => setHandoverRecipientName(event.target.value)}
                    placeholder="Nama orang yang menerima bantuan"
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="grant-handover-notes">Keterangan jika bukan PIC</Label>
                  <Input
                    id="grant-handover-notes"
                    value={handoverRecipientNotes}
                    onChange={(event) => setHandoverRecipientNotes(event.target.value)}
                  />
                </div>
              </>
            ) : null}
            {String(row.beneficiary_scope) === "collective" ? (
              <div className="space-y-1">
                <Label htmlFor="grant-actual-beneficiary-count">Realisasi orang terbantu</Label>
                <Input
                  id="grant-actual-beneficiary-count"
                  type="number"
                  min={1}
                  value={actualBeneficiaryCount}
                  onChange={(event) => setActualBeneficiaryCount(event.target.value)}
                  placeholder={`Target: ${String(row.target_beneficiary_count ?? "-")}`}
                />
              </div>
            ) : null}
            <Button
              size="sm"
              disabled={
                busy ||
                (String(row.beneficiary_type) === "organization" && !handoverRecipientName) ||
                (String(row.beneficiary_scope) === "collective" && Number(actualBeneficiaryCount) < 1)
              }
              onClick={async () => {
                setBusy(true);
                try {
                  await apiPost(
                    `/grant-applications/${id}/complete`,
                    buildGrantCompletionPayload(
                      row,
                      handoverDate,
                      handoverRecipientName,
                      handoverRecipientNotes,
                      actualBeneficiaryCount,
                    ),
                  );
                  toast.success("Pengajuan ditandai selesai.");
                  onRefresh();
                } catch (error) {
                  toast.error(error instanceof ApiError ? error.message : "Belum bisa diselesaikan.");
                } finally {
                  setBusy(false);
                }
              }}
            >
              Tandai selesai
            </Button>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
