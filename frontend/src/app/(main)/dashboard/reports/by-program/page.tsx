"use client";

import { ReportPage } from "@/components/sima/reports";
import { byProgramReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={byProgramReport} />;
}
