"use client";

import { ReportPage } from "@/components/sima/reports";
import { approvalReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={approvalReport} />;
}
