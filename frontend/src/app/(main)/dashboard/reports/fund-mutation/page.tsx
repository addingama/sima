"use client";

import { ReportPage } from "@/components/sima/reports";
import { fundMutationReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={fundMutationReport} />;
}
