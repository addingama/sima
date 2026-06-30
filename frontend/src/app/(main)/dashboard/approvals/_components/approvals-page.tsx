"use client";

import { CrudListPage } from "@/components/sima/crud";
import { PageHeader } from "@/components/sima/page-header";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { disbursementResource, receiptResource } from "@/lib/resources";

export default function ApprovalsPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Approval" description="Antrian persetujuan penerimaan dan pengeluaran." />

      <Tabs defaultValue="receipts">
        <TabsList>
          <TabsTrigger value="receipts">Penerimaan</TabsTrigger>
          <TabsTrigger value="disbursements-submitted">Pengeluaran (Verifikasi)</TabsTrigger>
          <TabsTrigger value="disbursements-verified">Pengeluaran (Persetujuan)</TabsTrigger>
        </TabsList>

        <TabsContent value="receipts">
          <CrudListPage
            config={receiptResource}
            description="Penerimaan dengan status submitted menunggu persetujuan ketua."
            initialFilters={{ status: "submitted" }}
            hideCreate
          />
        </TabsContent>

        <TabsContent value="disbursements-submitted">
          <CrudListPage
            config={disbursementResource}
            description="Pengeluaran submitted menunggu verifikasi."
            initialFilters={{ status: "submitted" }}
            hideCreate
          />
        </TabsContent>

        <TabsContent value="disbursements-verified">
          <CrudListPage
            config={disbursementResource}
            description="Pengeluaran verified menunggu persetujuan ketua."
            initialFilters={{ status: "verified" }}
            hideCreate
          />
        </TabsContent>
      </Tabs>
    </div>
  );
}
