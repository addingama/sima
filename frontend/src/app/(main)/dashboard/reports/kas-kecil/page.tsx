"use client";

import { ReportPage } from "@/components/sima/reports";
import { pettyCashReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={pettyCashReport} />;
}
