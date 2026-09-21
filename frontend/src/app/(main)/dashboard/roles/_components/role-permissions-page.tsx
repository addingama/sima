"use client";

import { useEffect, useMemo, useState } from "react";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { LockKeyhole, Save } from "lucide-react";
import { toast } from "sonner";

import { ErrorState } from "@/components/sima/error-state";
import { PageHeader } from "@/components/sima/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { ApiError, apiGet, apiPut } from "@/lib/api/client";
import { groupPermissions, permissionLabel } from "@/lib/roles/permission-groups";

export interface RolePermissionRow {
  id: number;
  name: string;
  label: string;
  permissions: string[];
  users_count: number;
  is_locked: boolean;
}

interface RolePermissionData {
  roles: RolePermissionRow[];
  permissions: string[];
}

const queryKey = ["role-permissions"];

export default function RolePermissionsPage() {
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<number>();
  const [draft, setDraft] = useState<string[]>([]);
  const query = useQuery({
    queryKey,
    queryFn: async () => (await apiGet<RolePermissionData>("/roles")).data,
  });
  const selected = query.data?.roles.find((role) => role.id === selectedId) ?? query.data?.roles[0];

  useEffect(() => {
    if (selected) {
      setSelectedId(selected.id);
      setDraft(selected.permissions);
    }
  }, [selected]);

  const groups = useMemo(() => groupPermissions(query.data?.permissions ?? []), [query.data?.permissions]);
  const mutation = useMutation({
    mutationFn: async () =>
      (await apiPut<RolePermissionData>(`/roles/${selected?.id}/permissions`, { permissions: draft })).data,
    onSuccess: (data) => {
      queryClient.setQueryData(queryKey, data);
      toast.success("Permission role berhasil diperbarui.");
    },
    onError: (error: Error) => toast.error(error instanceof ApiError ? error.message : "Gagal menyimpan permission."),
  });

  if (query.isLoading) return <Skeleton className="h-96 w-full" />;
  if (query.isError || !query.data || !selected) return <ErrorState onRetry={() => query.refetch()} />;

  const changed = [...draft].sort().join("|") !== [...selected.permissions].sort().join("|");

  function togglePermission(permission: string, checked: boolean) {
    setDraft((current) =>
      checked ? [...new Set([...current, permission])] : current.filter((item) => item !== permission),
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Role & Permission"
        description="Atur akses setiap role. Perubahan berlaku untuk seluruh pengguna dengan role tersebut dan tercatat di audit trail."
      />

      <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(22rem,3fr)]">
        <Card>
          <CardHeader>
            <CardTitle>Daftar role</CardTitle>
            <CardDescription>Pilih role yang akan diatur.</CardDescription>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Role</TableHead>
                  <TableHead>Pengguna</TableHead>
                  <TableHead>Permission</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {query.data.roles.map((role) => (
                  <TableRow
                    key={role.id}
                    className={selected.id === role.id ? "bg-muted/60" : "cursor-pointer"}
                    onClick={() => setSelectedId(role.id)}
                  >
                    <TableCell>
                      <button type="button" className="flex items-center gap-2 text-left font-medium">
                        {role.label}
                        {role.is_locked ? (
                          <LockKeyhole aria-label="Terkunci" className="size-3.5 text-muted-foreground" />
                        ) : null}
                      </button>
                    </TableCell>
                    <TableCell>{role.users_count}</TableCell>
                    <TableCell>{role.permissions.length}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex-row items-start justify-between gap-4">
            <div>
              <CardTitle>{selected.label}</CardTitle>
              <CardDescription>
                {selected.is_locked
                  ? "Role Administrator selalu memiliki seluruh permission."
                  : "Centang akses yang diperbolehkan."}
              </CardDescription>
            </div>
            <Badge variant="secondary">{draft.length} akses</Badge>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2">
              {groups.map((group) => (
                <fieldset key={group.name} className="space-y-3 rounded-lg border p-4" disabled={selected.is_locked}>
                  <legend className="px-1 font-medium text-sm">{group.name}</legend>
                  {group.permissions.map((permission) => {
                    const id = `permission-${selected.id}-${permission}`;
                    return (
                      <div key={permission} className="flex items-center gap-2">
                        <Checkbox
                          id={id}
                          checked={draft.includes(permission)}
                          onCheckedChange={(checked) => togglePermission(permission, checked === true)}
                        />
                        <Label htmlFor={id} className="capitalize">
                          {permissionLabel(permission)}
                        </Label>
                      </div>
                    );
                  })}
                </fieldset>
              ))}
            </div>
            {!selected.is_locked ? (
              <div className="flex justify-end">
                <Button disabled={!changed || mutation.isPending} onClick={() => mutation.mutate()}>
                  <Save className="size-4" />
                  {mutation.isPending ? "Menyimpan..." : "Simpan perubahan"}
                </Button>
              </div>
            ) : null}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
