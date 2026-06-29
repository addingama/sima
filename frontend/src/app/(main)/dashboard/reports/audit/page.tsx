"use client";

import { ReportPage } from "@/components/sima/reports";
import { auditReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={auditReport} />;
}
