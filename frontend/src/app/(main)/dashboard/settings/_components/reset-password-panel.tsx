"use client";

import { useState } from "react";

import { useParams } from "next/navigation";

import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { apiPost } from "@/lib/api/client";

export function ResetPasswordPanel() {
  const params = useParams<{ id: string }>();
  const [password, setPassword] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();

    if (password.length < 8) {
      toast.error("Password minimal 8 karakter.");
      return;
    }

    setIsSubmitting(true);

    try {
      await apiPost(`/users/${params.id}/reset-password`, { password });
      toast.success("Password berhasil direset. Sesi login pengguna dicabut.");
      setPassword("");
    } catch {
      toast.error("Gagal mereset password.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Reset Password</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="flex max-w-md flex-col gap-4">
          <div className="space-y-2">
            <Label htmlFor="new-password">Password baru</Label>
            <Input
              id="new-password"
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              placeholder="Min. 8 karakter"
              autoComplete="new-password"
            />
          </div>
          <Button type="submit" variant="outline" disabled={isSubmitting}>
            Reset password
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
