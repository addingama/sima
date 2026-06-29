"use client";

import { ReportPage } from "@/components/sima/reports";
import { byVendorReport } from "@/lib/reports/definitions";

export default function Page() {
  return <ReportPage config={byVendorReport} />;
}
