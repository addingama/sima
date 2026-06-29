"use client";

import { useRouter } from "next/navigation";

import { NavUser } from "@/app/(main)/dashboard/_components/sidebar/nav-user";
import { useAuth } from "@/providers/auth-provider";

export function AuthNavUser() {
  const { user, logout } = useAuth();
  const router = useRouter();

  if (!user) {
    return null;
  }

  return (
    <NavUser
      user={{
        name: user.name,
        email: user.email,
        avatar: "",
      }}
      onLogout={async () => {
        await logout();
        router.push("/auth/v2/login");
      }}
    />
  );
}
