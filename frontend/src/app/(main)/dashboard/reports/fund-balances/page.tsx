"use client";

import { ReportPage } from "@/components/sima/reports";
import { fundBalancesReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={fundBalancesReport} />;
}
