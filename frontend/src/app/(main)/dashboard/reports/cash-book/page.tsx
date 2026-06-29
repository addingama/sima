"use client";

import { ReportPage } from "@/components/sima/reports";
import { cashBookReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={cashBookReport} />;
}
