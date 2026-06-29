"use client";

import { Store } from "lucide-react";

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { PageHeader } from "@/components/sima/page-header";

export default function VendorsPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Vendor"
        description="Master data vendor untuk pengeluaran dan biaya operasional."
      />

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Store className="size-5" />
            Modul Belum Tersedia
          </CardTitle>
          <CardDescription>
            Backend SIMA belum memiliki API vendor. Halaman ini disiapkan agar CRUD vendor dapat diaktifkan
            setelah modul backend selesai.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-muted-foreground text-sm">
            Saat ini pengeluaran menggunakan field penerima (`payee`) sebagai teks bebas. Setelah modul vendor
            tersedia, halaman ini akan memakai komponen CRUD yang sama dengan modul master data lainnya.
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
