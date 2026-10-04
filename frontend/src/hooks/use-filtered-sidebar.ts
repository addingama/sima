"use client";

import type { SimaUser } from "@/lib/api/types";
import { hasAnyPermission, hasPermission } from "@/lib/auth/permissions";
import type { NavGroup, NavMainItem, NavMainParentItem, NavSubItem } from "@/navigation/sidebar/sidebar-items";
import { useAuth } from "@/providers/auth-provider";

function canAccess(
  user: SimaUser | null,
  permission?: string,
  permissionsAny?: string[],
  hiddenForRoles?: string[],
): boolean {
  if (hiddenForRoles?.some((role) => user?.roles.includes(role))) {
    return false;
  }

  if (permission) {
    return hasPermission(user, permission);
  }

  if (permissionsAny?.length) {
    return hasAnyPermission(user, permissionsAny);
  }

  return true;
}

function filterSubItems(subItems: NavSubItem[], user: SimaUser | null): NavSubItem[] {
  return subItems.filter((subItem) => canAccess(user, subItem.permission, subItem.permissionsAny));
}

function filterItems(items: NavMainItem[], user: SimaUser | null): NavMainItem[] {
  return items
    .map((item) => {
      if ("subItems" in item && item.subItems) {
        if (!canAccess(user, item.permission, item.permissionsAny, item.hiddenForRoles)) {
          return null;
        }

        const subItems = filterSubItems(item.subItems, user);

        if (subItems.length === 0) {
          return null;
        }

        return { ...item, subItems } satisfies NavMainParentItem;
      }

      if (!canAccess(user, item.permission, item.permissionsAny, item.hiddenForRoles)) {
        return null;
      }

      return item;
    })
    .filter(Boolean) as NavMainItem[];
}

export function useFilteredSidebarItems(items: readonly NavGroup[]): NavGroup[] {
  const { user } = useAuth();

  return filterSidebarItems(items, user);
}

export function filterSidebarItems(items: readonly NavGroup[], user: SimaUser | null): NavGroup[] {
  return items
    .map((group) => ({
      ...group,
      items: filterItems(group.items, user),
    }))
    .filter((group) => group.items.length > 0);
}
