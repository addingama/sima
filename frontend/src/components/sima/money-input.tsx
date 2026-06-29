"use client";

import type { ComponentProps } from "react";

import { Input } from "@/components/ui/input";
import { amountToMoneyInput, formatMoneyInput } from "@/lib/format/amount";
import { cn } from "@/lib/utils";

type MoneyInputProps = Omit<ComponentProps<typeof Input>, "type" | "value" | "onChange" | "inputMode"> & {
  value: string | number | null | undefined;
  onChange: (value: string) => void;
};

export function MoneyInput({ value, onChange, className, placeholder = "0", ...props }: MoneyInputProps) {
  const displayValue = formatMoneyInput(value);

  return (
    <div className="relative">
      <span className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground text-sm">
        Rp
      </span>
      <Input
        {...props}
        type="text"
        inputMode="numeric"
        autoComplete="off"
        className={cn("pl-9 tabular-nums", className)}
        value={displayValue}
        placeholder={placeholder}
        onChange={(event) => {
          const digits = event.target.value.replace(/\D/g, "");
          onChange(digits === "" ? "" : amountToMoneyInput(digits));
        }}
      />
    </div>
  );
}
