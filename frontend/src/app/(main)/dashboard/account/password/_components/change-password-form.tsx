"use client";

import { useState } from "react";

import { zodResolver } from "@hookform/resolvers/zod";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { ApiError, apiPut } from "@/lib/api/client";

const formSchema = z
  .object({
    current_password: z.string().min(1, { message: "Masukkan password saat ini." }),
    password: z.string().min(8, { message: "Password minimal 8 karakter." }),
    password_confirmation: z.string().min(1, { message: "Ulangi password baru." }),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Konfirmasi password tidak sama.",
    path: ["password_confirmation"],
  })
  .refine((data) => data.password !== data.current_password, {
    message: "Password baru harus berbeda dari password saat ini.",
    path: ["password"],
  });

type FormValues = z.infer<typeof formSchema>;

const fields = [
  { name: "current_password", label: "Password saat ini", autoComplete: "current-password" },
  { name: "password", label: "Password baru", autoComplete: "new-password" },
  { name: "password_confirmation", label: "Ulangi password baru", autoComplete: "new-password" },
] as const;

export function ChangePasswordForm() {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      current_password: "",
      password: "",
      password_confirmation: "",
    },
  });

  const onSubmit = async (values: FormValues) => {
    setIsSubmitting(true);

    try {
      await apiPut("/me/password", values);
      toast.success("Password berhasil diubah.");
      form.reset();
    } catch (error) {
      if (error instanceof ApiError && error.fields) {
        for (const field of fields) {
          const message = error.fields[field.name]?.[0];

          if (message) {
            form.setError(field.name, { message });
          }
        }
      }

      toast.error(error instanceof ApiError ? error.message : "Gagal mengubah password.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Card className="max-w-lg">
      <CardHeader>
        <CardTitle>Password akun</CardTitle>
      </CardHeader>
      <CardContent>
        <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <FieldGroup className="gap-4">
            {fields.map((field) => (
              <Controller
                key={field.name}
                control={form.control}
                name={field.name}
                render={({ field: formField, fieldState }) => (
                  <Field className="gap-1.5" data-invalid={fieldState.invalid}>
                    <FieldLabel htmlFor={field.name}>{field.label}</FieldLabel>
                    <Input
                      {...formField}
                      id={field.name}
                      type="password"
                      autoComplete={field.autoComplete}
                      aria-invalid={fieldState.invalid}
                    />
                    {fieldState.invalid ? <FieldError errors={[fieldState.error]} /> : null}
                  </Field>
                )}
              />
            ))}
          </FieldGroup>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? "Menyimpan..." : "Simpan password"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
