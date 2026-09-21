export interface PermissionGroup {
  name: string;
  permissions: string[];
}

const GROUP_LABELS: Record<string, string> = {
  account: "Rekening",
  audit: "Audit",
  bank_fee: "Biaya Bank",
  dashboard: "Dashboard",
  disbursement: "Pengeluaran",
  donor: "Donatur",
  event: "Event / Program",
  fund: "Dana Amanah",
  grant: "Penyaluran",
  opening: "Saldo Awal",
  portal: "Portal Donatur",
  receipt: "Penerimaan",
  reconciliation: "Rekonsiliasi",
  report: "Laporan",
  user: "Pengguna",
  vendor: "Vendor",
};

export function groupPermissions(permissions: string[]): PermissionGroup[] {
  const groups = new Map<string, string[]>();

  for (const permission of [...permissions].sort()) {
    const prefix = permission.split(".")[0] ?? permission;
    groups.set(prefix, [...(groups.get(prefix) ?? []), permission]);
  }

  return [...groups.entries()].map(([prefix, values]) => ({
    name: GROUP_LABELS[prefix] ?? prefix,
    permissions: values,
  }));
}

export function permissionLabel(permission: string): string {
  const action = permission.split(".").slice(1).join(" ");
  return action ? action.replaceAll("_", " ") : permission;
}
