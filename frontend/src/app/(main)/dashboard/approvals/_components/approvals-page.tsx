"use client";

import { useMemo } from "react";

import { CrudListPage } from "@/components/sima/crud";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { hasAnyPermission, hasPermission } from "@/lib/auth/permissions";
import { disbursementResource, receiptResource } from "@/lib/resources";
import { useAuth } from "@/providers/auth-provider";

type ApprovalTab = {
  value: string;
  label: string;
  description: string;
  emptyMessage: string;
  config: typeof receiptResource | typeof disbursementResource;
  status: string;
  visible: boolean;
};

export default function ApprovalsPage() {
  const { user } = useAuth();

  const canApproveReceipt = hasPermission(user, "receipt.approve");
  const canVerifyDisbursement = hasPermission(user, "disbursement.verify");
  const canApproveDisbursement = hasPermission(user, "disbursement.approve");
  const canAccess = hasAnyPermission(user, ["receipt.approve", "disbursement.verify", "disbursement.approve"]);

  const tabs = useMemo<ApprovalTab[]>(
    () =>
      [
        {
          value: "receipts",
          label: "Penerimaan",
          description: "Penerimaan submitted menunggu persetujuan ketua.",
          emptyMessage: "Tidak ada penerimaan menunggu persetujuan.",
          config: receiptResource,
          status: "submitted",
          visible: canApproveReceipt,
        },
        {
          value: "disbursements-submitted",
          label: "Pengeluaran (Verifikasi)",
          description: "Pengeluaran submitted menunggu verifikasi.",
          emptyMessage: "Tidak ada pengeluaran menunggu verifikasi.",
          config: disbursementResource,
          status: "submitted",
          visible: canVerifyDisbursement,
        },
        {
          value: "disbursements-verified",
          label: "Pengeluaran (Persetujuan)",
          description: "Pengeluaran verified menunggu persetujuan ketua.",
          emptyMessage: "Tidak ada pengeluaran menunggu persetujuan.",
          config: disbursementResource,
          status: "verified",
          visible: canApproveDisbursement,
        },
      ].filter((tab) => tab.visible),
    [canApproveReceipt, canVerifyDisbursement, canApproveDisbursement],
  );

  const defaultTab = tabs[0]?.value ?? "receipts";

  if (!canAccess) {
    return (
      <ErrorState
        title="Akses ditolak"
        description="Halaman Approval hanya untuk verifikator atau ketua yang memiliki wewenang aksi."
      />
    );
  }

  if (tabs.length === 0) {
    return <ErrorState title="Tidak ada antrian" description="Akun Anda tidak memiliki tab approval yang relevan." />;
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Approval"
        description="Antrian dokumen yang membutuhkan tindakan Anda. Buka detail untuk verifikasi, setujui, atau tolak."
      />

      <Tabs key={defaultTab} defaultValue={defaultTab}>
        <TabsList>
          {tabs.map((tab) => (
            <TabsTrigger key={tab.value} value={tab.value}>
              {tab.label}
            </TabsTrigger>
          ))}
        </TabsList>

        {tabs.map((tab) => (
          <TabsContent key={tab.value} value={tab.value}>
            <CrudListPage
              config={tab.config}
              title={tab.label}
              description={tab.description}
              emptyMessage={tab.emptyMessage}
              initialFilters={{ status: tab.status }}
              hideCreate
              hideFilters
              hideHeader
            />
          </TabsContent>
        ))}
      </Tabs>
    </div>
  );
}
