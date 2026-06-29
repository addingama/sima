"use client";

import type { ReactNode } from "react";

import { createContext, useContext, useEffect, useMemo, useState } from "react";

import { apiPost } from "@/lib/api/client";
import type { LoginResult, SimaUser } from "@/lib/api/types";
import { clearClientSession, getClientUser, setClientSession } from "@/lib/auth/session.client";

interface AuthContextValue {
  user: SimaUser | null;
  isLoading: boolean;
  login: (email: string, password: string, remember?: boolean) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<SimaUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    setUser(getClientUser());
    setIsLoading(false);
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      isLoading,
      refreshUser: () => setUser(getClientUser()),
      login: async (email, password, remember = false) => {
        const response = await apiPost<LoginResult>("/login", {
          email,
          password,
          device_name: "sima-web",
        });

        setClientSession(response.data.token, response.data.user, remember);
        setUser(response.data.user);
      },
      logout: async () => {
        try {
          await apiPost("/logout");
        } catch {
          // Ignore logout API errors and clear local session anyway.
        }

        clearClientSession();
        setUser(null);
      },
    }),
    [user, isLoading],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used within AuthProvider");
  }

  return context;
}
