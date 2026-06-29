export function formatIdr(value: string | number | null | undefined): string {
  const amount = Number.parseFloat(String(value ?? "0"));

  if (Number.isNaN(amount)) {
    return "Rp 0";
  }

  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}
