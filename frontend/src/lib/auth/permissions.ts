import type { SimaUser } from "@/lib/api/types";

export function hasPermission(user: SimaUser | null | undefined, permission: string): boolean {
  if (!user) {
    return false;
  }

  if (user.permissions.includes("*")) {
    return true;
  }

  return user.permissions.includes(permission);
}

export function hasAnyPermission(user: SimaUser | null | undefined, permissions: string[]): boolean {
  return permissions.some((permission) => hasPermission(user, permission));
}
