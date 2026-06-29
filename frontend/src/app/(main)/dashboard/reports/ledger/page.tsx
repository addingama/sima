"use client";

import { ReportPage } from "@/components/sima/reports";
import { ledgerReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={ledgerReport} />;
}
