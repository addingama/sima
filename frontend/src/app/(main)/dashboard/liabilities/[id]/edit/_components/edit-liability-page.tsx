"use client";

import { useParams } from "next/navigation";

import { LiabilityForm } from "@/app/(main)/dashboard/liabilities/_components/liability-form";
import { ErrorState } from "@/components/sima/error-state";
import { PageShellSkeleton } from "@/components/sima/skeletons";
import { useDetailQuery } from "@/hooks/use-resource-query";
import { hasPermission } from "@/lib/auth/permissions";
import type { OperationalLiability } from "@/lib/liability/types";
import { useAuth } from "@/providers/auth-provider";

export default function EditLiabilityPage() {
  const params = useParams<{ id: string }>();
  const { user } = useAuth();
  const canManage = hasPermission(user, "liability.manage");
  const { data, isLoading, isError, refetch } = useDetailQuery<OperationalLiability>(
    "/liabilities",
    params.id,
    canManage,
  );

  if (!canManage) {
    return (
      <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk mengelola liabilitas." />
    );
  }

  if (isLoading) {
    return <PageShellSkeleton />;
  }

  if (isError || !data) {
    return <ErrorState onRetry={() => refetch()} />;
  }

  return <LiabilityForm mode="edit" liability={data} />;
}
