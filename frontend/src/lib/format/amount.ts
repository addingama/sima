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

/**
 * Parse nominal dari API (en: 100000.00) atau input ID (1.000.000 / 1.000.000,50).
 * Jangan strip titik pada string API decimal — itu menyebabkan nominal ×100.
 */
export function parseAmount(value: string | number | null | undefined): number {
  if (typeof value === "number") {
    return Number.isNaN(value) ? 0 : value;
  }

  const raw = String(value ?? "").trim();

  if (!raw) {
    return 0;
  }

  // State form: digit saja
  if (/^\d+$/.test(raw)) {
    return Number.parseInt(raw, 10);
  }

  // API / en-US: 100000.00 atau 2500.5
  if (/^-?\d+\.\d{1,2}$/.test(raw)) {
    return Number.parseFloat(raw);
  }

  // Locale ID dengan desimal koma: 1.000.000,50
  if (raw.includes(",")) {
    const normalized = raw.replace(/\./g, "").replace(",", ".");
    const amount = Number.parseFloat(normalized.replace(/[^\d.-]/g, ""));

    return Number.isNaN(amount) ? 0 : amount;
  }

  // Locale ID pemisah ribuan saja: 1.000.000
  if (/^-?\d{1,3}(\.\d{3})+$/.test(raw)) {
    return Number.parseInt(raw.replace(/\./g, ""), 10);
  }

  const amount = Number.parseFloat(raw.replace(/[^\d.-]/g, ""));

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
