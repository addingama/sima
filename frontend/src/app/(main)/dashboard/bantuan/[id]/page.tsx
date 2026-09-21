"use client";

import { useParams } from "next/navigation";

import { GrantCasePanels } from "@/app/(main)/dashboard/bantuan/_components/grant-case-panels";
import { CrudDetailPage } from "@/components/sima/crud/crud-detail-page";
import { grantApplicationResource } from "@/lib/resources";

export default function Page() {
  const params = useParams<{ id: string }>();

  return (
    <CrudDetailPage
      config={grantApplicationResource}
      id={String(params.id)}
      extras={({ row, refetch }) => <GrantCasePanels row={row} onRefresh={refetch} />}
    />
  );
}
