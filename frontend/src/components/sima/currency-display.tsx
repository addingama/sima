import { formatIdr } from "@/lib/format/currency";
import { cn } from "@/lib/utils";

export function CurrencyDisplay({
  value,
  className,
}: {
  value: string | number | null | undefined;
  className?: string;
}) {
  return <span className={cn("tabular-nums", className)}>{formatIdr(value)}</span>;
}
