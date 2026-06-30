"use client";

import { ReportPage } from "@/components/sima/reports";
import { openingBalanceReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={openingBalanceReport} />;
}
