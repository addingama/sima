import { expect, test } from "@playwright/test";

import { loginAs, type Role } from "../fixtures/auth";

const roleJourneys: Array<{ role: Role; pages: string[] }> = [
  {
    role: "admin",
    pages: [
      "/dashboard/settings",
      "/dashboard/roles",
      "/dashboard/funds",
      "/dashboard/accounts",
      "/dashboard/opening-balances",
    ],
  },
  {
    role: "asisten_bendahara",
    pages: [
      "/dashboard/receipts/new",
      "/dashboard/disbursements/new",
      "/dashboard/bank-fees/new",
      "/dashboard/transfers/new",
      "/dashboard/fund-transfers/new",
      "/dashboard/liabilities/new",
      "/dashboard/bantuan/new",
    ],
  },
  {
    role: "bendahara",
    pages: ["/dashboard/approvals", "/dashboard/reconciliations/new", "/dashboard/reports", "/dashboard/bantuan"],
  },
  { role: "verifikator", pages: ["/dashboard/approvals", "/dashboard/disbursements", "/dashboard/bantuan"] },
  { role: "petugas_bantuan", pages: ["/dashboard/default", "/dashboard/bantuan", "/dashboard/bantuan/new"] },
  { role: "ketua", pages: ["/dashboard/approvals", "/dashboard/bantuan", "/dashboard/reports/bantuan"] },
  { role: "auditor", pages: ["/dashboard/audits", "/dashboard/reports", "/dashboard/reports/ledger"] },
  { role: "donatur", pages: ["/dashboard/portal-donatur"] },
];

for (const journey of roleJourneys) {
  test(`${journey.role} dapat membuka seluruh halaman flow utamanya`, async ({ page }) => {
    await loginAs(page, journey.role);

    for (const path of journey.pages) {
      const response = await page.goto(path);

      expect(response?.status(), `${journey.role}: ${path}`).toBeLessThan(400);
      await expect(page.locator("main")).toBeVisible();
      await expect(page.getByText("Unauthorized", { exact: true })).toHaveCount(0);
      await expect(page.getByText("Forbidden", { exact: true })).toHaveCount(0);
    }
  });
}
