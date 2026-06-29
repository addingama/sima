"use client";

import { ReportPage } from "@/components/sima/reports";
import { byDonorReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={byDonorReport} />;
}
