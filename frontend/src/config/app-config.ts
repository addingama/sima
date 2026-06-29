import packageJson from "../../package.json";

const currentYear = new Date().getFullYear();

export const APP_CONFIG = {
  name: "SIMA",
  version: packageJson.version,
  copyright: `© ${currentYear}, SIMA — Sistem Informasi Manajemen Amanah.`,
  meta: {
    title: "SIMA — Sistem Informasi Manajemen Amanah",
    description:
      "Sistem informasi manajemen dana amanah untuk lembaga sosial. Mencatat penerimaan, pengeluaran, ledger, dan rekonsiliasi dengan audit trail.",
  },
};
