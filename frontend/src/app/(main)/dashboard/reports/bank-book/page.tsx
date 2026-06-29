"use client";

import { ReportPage } from "@/components/sima/reports";
import { bankBookReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={bankBookReport} />;
}
