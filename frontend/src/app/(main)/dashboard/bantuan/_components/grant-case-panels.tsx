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

export function GrantCasePanels({ row, onRefresh }: { row: Record<string, unknown>; onRefresh: () => void }) {
  const { user } = useAuth();
  const id = Number(row.id);
  const status = String(row.status ?? "");
  const assignMutation = useWorkflowAction("/grant-applications", id);
  const [verifierId, setVerifierId] = useState(String(row.assigned_verifier_id ?? ""));
  const [handoverDate, setHandoverDate] = useState(new Date().toISOString().slice(0, 10));
  const [disbursementDate, setDisbursementDate] = useState(new Date().toISOString().slice(0, 10));
  const [accountId, setAccountId] = useState("");
  const [fundId, setFundId] = useState("");
  const [busy, setBusy] = useState(false);

  const canAssign = hasPermission(user, "grant.assign") && (status === "draft" || status === "verification");
  const canCreateDisbursement =
    hasPermission(user, "disbursement.create") && status === "approved" && !row.disbursement_id;
  const canComplete = hasPermission(user, "grant.handover") && status === "approved";

  if (!canAssign && !canCreateDisbursement && !canComplete) {
    return null;
  }

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      {canAssign ? (
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
            <Button
              size="sm"
              disabled={busy}
              onClick={async () => {
                setBusy(true);
                try {
                  await apiPost(`/grant-applications/${id}/complete`, { handed_over_on: handoverDate });
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
