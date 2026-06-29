"use client";

import { useAuth } from "@/providers/auth-provider";
import { hasPermission } from "@/lib/auth/permissions";
import type { NavGroup, NavMainItem, NavMainParentItem } from "@/navigation/sidebar/sidebar-items";

function filterItems(items: NavMainItem[], user: ReturnType<typeof useAuth>["user"]): NavMainItem[] {
  return items
    .map((item) => {
      if ("subItems" in item && item.subItems) {
        if (item.permission && !hasPermission(user, item.permission)) {
          return null;
        }

        const subItems = item.subItems.filter(
          (subItem) => !subItem.permission || hasPermission(user, subItem.permission),
        );

        if (subItems.length === 0) {
          return null;
        }

        return { ...item, subItems } satisfies NavMainParentItem;
      }

      if (item.permission && !hasPermission(user, item.permission)) {
        return null;
      }

      return item;
    })
    .filter(Boolean) as NavMainItem[];
}

export function useFilteredSidebarItems(items: readonly NavGroup[]): NavGroup[] {
  const { user } = useAuth();

  return items
    .map((group) => ({
      ...group,
      items: filterItems(group.items, user),
    }))
    .filter((group) => group.items.length > 0);
}
