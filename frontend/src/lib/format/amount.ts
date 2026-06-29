/** Digit-only string untuk state form (tanpa pemisah ribuan). */
export function amountToMoneyInput(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === "") {
    return "";
  }

  const amount = Number.parseFloat(String(value));

  if (Number.isNaN(amount) || amount <= 0) {
    return "";
  }

  return String(Math.round(amount));
}

/** Tampilan input uang locale id-ID, mis. 1.000.000 */
export function formatMoneyInput(value: string | number | null | undefined): string {
  const raw = amountToMoneyInput(value);

  if (!raw) {
    return "";
  }

  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 0,
  }).format(Number(raw));
}

export function parseAmount(value: string | number | null | undefined): number {
  if (typeof value === "number") {
    return Number.isNaN(value) ? 0 : value;
  }

  const raw = String(value ?? "").trim();

  if (!raw) {
    return 0;
  }

  if (/^\d+$/.test(raw)) {
    return Number.parseInt(raw, 10);
  }

  const normalized = raw.replace(/\./g, "").replace(",", ".");
  const amount = Number.parseFloat(normalized.replace(/[^\d.]/g, ""));

  return Number.isNaN(amount) ? 0 : amount;
}

/** Normalisasi ke string numerik untuk API Laravel (2 desimal). */
export function normalizeAmountString(value: string | number | null | undefined): string {
  const amount = parseAmount(value);

  if (amount <= 0) {
    return "";
  }

  return amount.toFixed(2);
}

export function formatPercent(value: number, fractionDigits = 1): string {
  return `${value.toFixed(fractionDigits)}%`;
}
