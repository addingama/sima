// biome-ignore-all lint/style/noNestedTernary: Status query bersifat eksklusif dan lebih mudah dibaca dekat markup.
"use client";

import { useState } from "react";

import Link from "next/link";

import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowLeft, Pencil, Plus, Trash2, X } from "lucide-react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";

import { ConfirmActionDialog } from "@/components/sima/crud/confirm-action-dialog";
import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { TableSkeleton } from "@/components/sima/skeletons";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { useResourceCreate, useResourceDelete, useResourceUpdate } from "@/hooks/use-resource-mutation";
import { useResourceQuery } from "@/hooks/use-resource-query";
import { ApiError } from "@/lib/api/client";
import { hasPermission } from "@/lib/auth/permissions";
import { type PettyCashCategoryFormValues, pettyCashCategorySchema } from "@/lib/petty-cash/schema";
import type { PettyCashCategory } from "@/lib/petty-cash/types";
import { useAuth } from "@/providers/auth-provider";

function CategoryForm({
  category,
  onSuccess,
  onCancel,
}: {
  category?: PettyCashCategory;
  onSuccess: () => void;
  onCancel: () => void;
}) {
  const create = useResourceCreate<PettyCashCategory>("/petty-cash-categories");
  const update = useResourceUpdate<PettyCashCategory>("/petty-cash-categories", category?.id ?? 0);

  const form = useForm<PettyCashCategoryFormValues>({
    resolver: zodResolver(pettyCashCategorySchema),
    defaultValues: {
      code: category?.code ?? "",
      name: category?.name ?? "",
      description: category?.description ?? "",
      sort_order: category?.sort_order !== undefined && category.sort_order !== null ? String(category.sort_order) : "",
    },
  });

  const onSubmit = async (values: PettyCashCategoryFormValues) => {
    try {
      const payload = {
        code: values.code.trim().toUpperCase(),
        name: values.name.trim(),
        description: values.description?.trim() || null,
        sort_order: values.sort_order ? Number(values.sort_order) : null,
      };

      if (category) {
        await update.mutateAsync(payload);
        toast.success("Kategori diperbarui.");
      } else {
        await create.mutateAsync(payload);
        toast.success("Kategori ditambahkan.");
      }
      onSuccess();
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal menyimpan kategori.");
    }
  };

  const pending = create.isPending || update.isPending;

  return (
    <form className="grid grid-cols-1 gap-4 sm:grid-cols-2" onSubmit={form.handleSubmit(onSubmit)}>
      <Field>
        <FieldLabel>Kode</FieldLabel>
        <Input {...form.register("code")} placeholder="cth. ATK" className="uppercase" />
        <FieldError>{form.formState.errors.code?.message}</FieldError>
      </Field>
      <Field>
        <FieldLabel>Nama</FieldLabel>
        <Input {...form.register("name")} placeholder="cth. Alat Tulis Kantor" />
        <FieldError>{form.formState.errors.name?.message}</FieldError>
      </Field>
      <Field>
        <FieldLabel>Keterangan (opsional)</FieldLabel>
        <Textarea rows={2} {...form.register("description")} />
      </Field>
      <Field>
        <FieldLabel>Urutan Tampil (opsional)</FieldLabel>
        <Input type="number" {...form.register("sort_order")} placeholder="cth. 10" />
      </Field>
      <div className="flex gap-2 sm:col-span-2">
        <Button type="submit" disabled={pending}>
          {pending ? "Menyimpan..." : category ? "Simpan Perubahan" : "Tambah Kategori"}
        </Button>
        <Button type="button" variant="outline" onClick={onCancel}>
          <X className="size-4" />
          Batal
        </Button>
      </div>
    </form>
  );
}

export default function PettyCashCategoriesPage() {
  const { user } = useAuth();
  const canManage = hasPermission(user, "petty_cash.category.manage");

  const [editingCategory, setEditingCategory] = useState<PettyCashCategory | null>(null);
  const [showNewForm, setShowNewForm] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const { data, isLoading, isError, refetch } = useResourceQuery<PettyCashCategory>(
    "/petty-cash-categories",
    { per_page: 100, sort: "sort_order", direction: "asc" },
    canManage,
  );

  const deleteMutation = useResourceDelete("/petty-cash-categories");

  if (!canManage) {
    return (
      <ErrorState
        title="Akses ditolak"
        description="Anda tidak memiliki permission untuk mengelola kategori kas kecil."
      />
    );
  }

  const handleDeactivate = async () => {
    if (!deletingId) return;
    try {
      await deleteMutation.mutateAsync(deletingId);
      toast.success("Kategori dinonaktifkan.");
      setDeletingId(null);
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Gagal menonaktifkan.");
      throw error;
    }
  };

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Kategori Belanja Kas Kecil"
        description="Master kategori untuk baris belanja kas kecil."
        actions={
          <div className="flex gap-2">
            <Button
              onClick={() => {
                setShowNewForm((v) => !v);
                setEditingCategory(null);
              }}
            >
              <Plus className="size-4" />
              Tambah Kategori
            </Button>
            <Button variant="outline" asChild>
              <Link href="/dashboard/kas-kecil">
                <ArrowLeft className="size-4" />
                Kembali
              </Link>
            </Button>
          </div>
        }
      />

      {showNewForm ? (
        <Card>
          <CardHeader>
            <CardTitle>Tambah Kategori Baru</CardTitle>
          </CardHeader>
          <CardContent>
            <CategoryForm
              onSuccess={() => {
                setShowNewForm(false);
                void refetch();
              }}
              onCancel={() => setShowNewForm(false)}
            />
          </CardContent>
        </Card>
      ) : null}

      {editingCategory ? (
        <Card>
          <CardHeader>
            <CardTitle>Edit Kategori: {editingCategory.name}</CardTitle>
          </CardHeader>
          <CardContent>
            <CategoryForm
              category={editingCategory}
              onSuccess={() => {
                setEditingCategory(null);
                void refetch();
              }}
              onCancel={() => setEditingCategory(null)}
            />
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle>
            Daftar Kategori{" "}
            <Badge variant="secondary" className="ml-1">
              {data?.rows?.length ?? "—"}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent>
          {isError ? (
            <ErrorState onRetry={() => refetch()} />
          ) : isLoading ? (
            <TableSkeleton />
          ) : !data?.rows?.length ? (
            <p className="text-muted-foreground text-sm">Belum ada kategori.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Kode</TableHead>
                  <TableHead>Nama</TableHead>
                  <TableHead>Keterangan</TableHead>
                  <TableHead>Urutan</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.rows.map((cat) => (
                  <TableRow key={cat.id} className={!cat.is_active ? "opacity-50" : undefined}>
                    <TableCell className="font-medium font-mono">{cat.code}</TableCell>
                    <TableCell>{cat.name}</TableCell>
                    <TableCell className="max-w-xs truncate text-sm">{cat.description ?? "-"}</TableCell>
                    <TableCell>{cat.sort_order ?? "-"}</TableCell>
                    <TableCell>
                      <Badge variant={cat.is_active ? "default" : "secondary"}>
                        {cat.is_active ? "Aktif" : "Nonaktif"}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => {
                            setEditingCategory(cat);
                            setShowNewForm(false);
                          }}
                        >
                          <Pencil className="size-4" />
                        </Button>
                        {cat.is_active ? (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setDeletingId(cat.id)}
                          >
                            <Trash2 className="size-4" />
                          </Button>
                        ) : null}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <ConfirmActionDialog
        open={deletingId !== null}
        onOpenChange={(open) => {
          if (!open) setDeletingId(null);
        }}
        title="Nonaktifkan kategori?"
        description="Kategori tidak akan dihapus permanen, hanya dinonaktifkan sehingga tidak bisa dipilih pada baris belanja baru."
        confirmLabel="Nonaktifkan"
        destructive
        onConfirm={handleDeactivate}
      />
    </div>
  );
}
