// biome-ignore-all lint/style/noNestedTernary: Status query bersifat eksklusif dan lebih mudah dibaca dekat markup.
"use client";

import { useMemo, useState } from "react";

import Link from "next/link";

import { Plus, Search } from "lucide-react";

import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { PaginatedDataTable } from "@/components/sima/paginated-data-table";
import { TableSkeleton } from "@/components/sima/skeletons";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useResourceQuery } from "@/hooks/use-resource-query";
import { hasPermission } from "@/lib/auth/permissions";
import { pettyCashFloatListColumns } from "@/lib/petty-cash/columns";
import { useAuth } from "@/providers/auth-provider";

const STATUS_OPTIONS = [
  { value: "all", label: "Semua Status" },
  { value: "draft", label: "Draft" },
  { value: "reporting", label: "Reporting" },
  { value: "closed", label: "Closed" },
  { value: "cancelled", label: "Cancelled" },
];

const PURPOSE_OPTIONS = [
  { value: "all", label: "Semua Tujuan" },
  { value: "operational", label: "Operasional" },
  { value: "event", label: "Event" },
];

export default function PettyCashFloatsPage() {
  const { user } = useAuth();
  const canView = hasPermission(user, "petty_cash.view");
  const canCreate = hasPermission(user, "petty_cash.create");
  const canManageCategory = hasPermission(user, "petty_cash.category.manage");

  const [pageIndex, setPageIndex] = useState(0);
  const [pageSize] = useState(15);
  const [q, setQ] = useState("");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [purpose, setPurpose] = useState("all");

  const params = useMemo(
    () => ({
      page: pageIndex + 1,
      per_page: pageSize,
      q: search || undefined,
      status: status !== "all" ? status : undefined,
      purpose: purpose !== "all" ? purpose : undefined,
    }),
    [pageIndex, pageSize, search, status, purpose],
  );

  const { data, isLoading, isError, refetch } = useResourceQuery<Record<string, unknown>>(
    "/petty-cash-floats",
    params,
    canView,
  );

  if (!canView) {
    return <ErrorState title="Akses ditolak" description="Anda tidak memiliki permission untuk melihat kas kecil." />;
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Kas Kecil"
        description="Kelola float kas kecil operasional dan event."
        actions={
          <div className="flex flex-wrap gap-2">
            {canManageCategory ? (
              <Button variant="outline" asChild>
                <Link href="/dashboard/kas-kecil/kategori">Kelola Kategori</Link>
              </Button>
            ) : null}
            {canCreate ? (
              <Button asChild>
                <Link href="/dashboard/kas-kecil/new">
                  <Plus className="size-4" />
                  Buat Float
                </Link>
              </Button>
            ) : null}
          </div>
        }
      />

      <Card>
        <CardHeader className="gap-4">
          <CardTitle>
            Daftar Float{" "}
            <Badge variant="secondary" className="ml-1">
              {data?.pagination?.total ?? "—"}
            </Badge>
          </CardTitle>
          <div className="flex flex-wrap gap-3">
            <form
              className="max-w-xs flex-1"
              onSubmit={(event) => {
                event.preventDefault();
                setSearch(q);
                setPageIndex(0);
              }}
            >
              <InputGroup>
                <InputGroupAddon>
                  <Search className="size-4" />
                </InputGroupAddon>
                <InputGroupInput
                  value={q}
                  onChange={(event) => setQ(event.target.value)}
                  placeholder="Cari judul / nomor..."
                />
              </InputGroup>
            </form>

            <Select
              value={status}
              onValueChange={(v) => {
                setStatus(v);
                setPageIndex(0);
              }}
            >
              <SelectTrigger className="w-44">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {STATUS_OPTIONS.map((o) => (
                  <SelectItem key={o.value} value={o.value}>
                    {o.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select
              value={purpose}
              onValueChange={(v) => {
                setPurpose(v);
                setPageIndex(0);
              }}
            >
              <SelectTrigger className="w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {PURPOSE_OPTIONS.map((o) => (
                  <SelectItem key={o.value} value={o.value}>
                    {o.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </CardHeader>

        <CardContent>
          {isError ? (
            <ErrorState onRetry={() => refetch()} />
          ) : isLoading ? (
            <TableSkeleton />
          ) : (
            <PaginatedDataTable
              columns={pettyCashFloatListColumns}
              data={data?.rows ?? []}
              pagination={data?.pagination}
              pageIndex={pageIndex}
              pageSize={pageSize}
              onPaginationChange={(p) => setPageIndex(p.pageIndex)}
              emptyMessage="Belum ada data float kas kecil."
            />
          )}
        </CardContent>
      </Card>
    </div>
  );
}
