"use client";

import Link from "next/link";

import { PageHeader } from "@/components/sima/page-header";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { allReports } from "@/lib/reports";

export default function ReportsIndexPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Laporan" description="Kumpulan laporan keuangan SIMA dengan filter, grouping, dan ekspor." />

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {allReports.map((report) => (
          <Link key={report.id} href={report.path} className="block">
            <Card className="h-full transition-colors hover:bg-muted/30">
              <CardHeader>
                <CardTitle className="text-base">{report.title}</CardTitle>
                <CardDescription>{report.description}</CardDescription>
              </CardHeader>
              <CardContent>
                <p className="text-primary text-sm">Buka laporan →</p>
              </CardContent>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  );
}
