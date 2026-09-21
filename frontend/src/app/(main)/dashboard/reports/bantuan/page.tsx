"use client";

import { ReportPage } from "@/components/sima/reports";
import { grantApplicationReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={grantApplicationReport} />;
}
