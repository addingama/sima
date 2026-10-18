"use client";

import { useParams } from "next/navigation";

import { GrantCasePanels } from "@/app/(main)/dashboard/bantuan/_components/grant-case-panels";
import { GrantComments } from "@/app/(main)/dashboard/bantuan/_components/grant-comments";
import { CrudDetailPage } from "@/components/sima/crud/crud-detail-page";
import { grantApplicationResource } from "@/lib/resources";

export default function Page() {
  const params = useParams<{ id: string }>();

  return (
    <CrudDetailPage
      config={grantApplicationResource}
      id={String(params.id)}
      extras={({ row, refetch }) => (
        <div className="flex flex-col gap-4">
          <GrantCasePanels row={row} onRefresh={refetch} />
          <GrantComments
            grantId={Number(row.id)}
            mentionAccess={
              Array.isArray(row.mention_access) ? (row.mention_access as { id: number; name: string }[]) : []
            }
            onChanged={refetch}
          />
        </div>
      )}
    />
  );
}
