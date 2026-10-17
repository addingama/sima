import { expect, type Page } from "@playwright/test";

import { loginAs, type Role } from "../fixtures/auth";

export const HANDOVER_PNG = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=",
  "base64",
);

export async function caption(page: Page, text: string, holdMs = 900) {
  await page.evaluate((label) => {
    let el = document.getElementById("sima-demo-caption");
    if (!el) {
      el = document.createElement("div");
      el.id = "sima-demo-caption";
      el.setAttribute("aria-hidden", "true");
      el.style.cssText = [
        "position:fixed",
        "top:14px",
        "left:50%",
        "transform:translateX(-50%)",
        "z-index:2147483647",
        "background:#0B1F3A",
        "color:#fff",
        "padding:10px 22px",
        "border-radius:10px",
        "font:600 17px/1.35 Inter,Calibri,sans-serif",
        "box-shadow:0 10px 28px rgba(11,31,58,.35)",
        "border:2px solid #0D7A6F",
        "max-width:92vw",
        "text-align:center",
        "pointer-events:none",
      ].join(";");
      document.body.appendChild(el);
    }
    el.textContent = label;
  }, text);
  await page.waitForTimeout(holdMs);
}

export async function confirmAction(page: Page, label: string) {
  await page.getByRole("button", { name: label, exact: true }).click();
  const dialog = page.getByRole("alertdialog");
  await expect(dialog).toBeVisible();
  await dialog.getByRole("button", { name: label, exact: true }).click();
}

export async function switchRole(page: Page, role: Role) {
  const avatar = page.locator("header [data-slot='avatar']");
  if (await avatar.isVisible()) {
    await avatar.click();
    const keluar = page.getByRole("menuitem", { name: "Keluar" });
    if (await keluar.isVisible()) {
      await keluar.click();
      await expect(page).toHaveURL(/\/auth\/v2\/login/);
      await loginAs(page, role);
      return;
    }
  }

  await page.context().clearCookies();
  await page.goto("/auth/v2/login");
  await loginAs(page, role);
}

export async function openGrantFromBoard(page: Page, recipient: string) {
  await page.goto("/dashboard/bantuan");
  await expect(page.getByRole("link", { name: recipient })).toBeVisible();
  await page.getByRole("link", { name: recipient }).click();
  await expect(page).toHaveURL(/\/dashboard\/bantuan\/\d+$/);
}

export async function approveGrant(page: Page, notes: string) {
  await page.getByRole("button", { name: "Setujui", exact: true }).click();
  const dialog = page.getByRole("alertdialog");
  await expect(dialog).toBeVisible();
  await dialog.getByLabel("Catatan").fill(notes);
  await dialog.getByRole("button", { name: "Setujui", exact: true }).click();
  await expect(page.getByText("Setujui berhasil.")).toBeVisible();
  await expect(page.getByText("Siap diserahkan", { exact: true }).first()).toBeVisible();
}

export async function createAndApproveLinkedDisbursement(
  page: Page,
  applicationNumber: string,
  accountLabel = "Kas Kantor (Demo)",
  fundLabel = "Dana Sosial Umum (Demo)",
) {
  const createExpense = page.getByRole("button", { name: "Buat pengeluaran" });
  await expect(createExpense).toBeVisible();

  const expenseCard = page
    .locator("[data-slot='card']")
    .filter({ has: page.getByRole("button", { name: "Buat pengeluaran" }) });
  await expenseCard.getByRole("combobox").nth(0).selectOption({ label: accountLabel });
  await expenseCard.getByRole("combobox").nth(1).selectOption({ label: fundLabel });
  await createExpense.click();
  await expect(page.getByText("Pengeluaran draft dibuat")).toBeVisible();

  await page.goto("/dashboard/disbursements");
  await page.getByPlaceholder("Cari...").fill(applicationNumber);
  await page.getByPlaceholder("Cari...").press("Enter");
  await expect(page.getByRole("link", { name: /DSB\// })).toBeVisible();
  await page.getByRole("link", { name: /DSB\// }).first().click();
  await expect(page).toHaveURL(/\/dashboard\/disbursements\/\d+$/);

  await confirmAction(page, "Submit");
  await expect(page.getByText("Submit berhasil.")).toBeVisible();
  await confirmAction(page, "Verifikasi");
  await expect(page.getByText("Verifikasi berhasil.")).toBeVisible();
  await confirmAction(page, "Setujui");
  await expect(page.getByText("Setujui berhasil.")).toBeVisible();
  await expect(page.getByText("approved", { exact: true }).first()).toBeVisible();
}

export async function selectRelation(page: Page, label: string, option: string) {
  await page.getByRole("group").filter({ hasText: label }).getByRole("combobox").selectOption({ label: option });
}

/** RelationSelect searchable (Combobox base-ui). */
export async function selectSearchable(page: Page, placeholder: string, optionLabel: string) {
  const input = page.getByPlaceholder(placeholder);
  await input.click();
  await input.fill(optionLabel.slice(0, Math.min(10, optionLabel.length)));
  await page.locator('[data-slot="combobox-item"]').filter({ hasText: optionLabel }).click();
}

export async function selectRadixOption(page: Page, fieldLabel: string, optionLabel: string) {
  await page.getByRole("group").filter({ hasText: fieldLabel }).getByRole("combobox").click();
  await page.getByRole("option", { name: optionLabel, exact: true }).click();
}

export async function fillMoneyInGroup(page: Page, groupLabel: string, amount: string) {
  await page
    .getByRole("group")
    .filter({ hasText: groupLabel })
    .locator('input[inputmode="numeric"]')
    .fill(amount);
}

/** Cairkan anggaran awal dari detail float (masih draft), lalu approve DSB (bendahara saja). */
export async function fundAndApproveFloatDisbursement(
  page: Page,
  opts: {
    amount: string;
    accountLabel?: string;
    fundLabel?: string;
    description?: string;
  },
) {
  const accountLabel = opts.accountLabel ?? "BCA Operasional (Demo)";
  const fundLabel = opts.fundLabel ?? "Dana Sosial Umum (Demo)";

  await page.getByRole("button", { name: "Cairkan Sekarang" }).click();
  const fundForm = page.locator("form").filter({ has: page.getByRole("button", { name: "Cairkan Anggaran Awal" }) });
  await fundForm.locator('input[inputmode="numeric"]').fill(opts.amount);
  await fundForm.getByRole("group").filter({ hasText: "Rekening Kas/Bank" }).getByRole("combobox").selectOption({
    label: accountLabel,
  });
  await fundForm.getByRole("group").filter({ hasText: "Dana Amanah" }).getByRole("combobox").selectOption({
    label: fundLabel,
  });
  if (opts.description) {
    await fundForm.locator("textarea").fill(opts.description);
  }
  await fundForm.getByRole("button", { name: "Cairkan Anggaran Awal" }).click();
  await expect(page.getByText("Pengeluaran pendanaan dibuat")).toBeVisible();

  const dsbLink = page.getByRole("link", { name: /DSB\// }).first();
  await expect(dsbLink).toBeVisible();
  const floatUrl = page.url();
  await dsbLink.click();
  await expect(page).toHaveURL(/\/dashboard\/disbursements\/\d+$/);

  // Bendahara punya submit + verify + approve — tanpa ganti role agar video lebih cepat.
  await confirmAction(page, "Submit");
  await expect(page.getByText("Submit berhasil.")).toBeVisible();
  await confirmAction(page, "Verifikasi");
  await expect(page.getByText("Verifikasi berhasil.")).toBeVisible();
  await confirmAction(page, "Setujui");
  await expect(page.getByText("Setujui berhasil.")).toBeVisible();
  await expect(page.getByText("approved", { exact: true }).first()).toBeVisible();

  await page.goto(floatUrl);
  await expect(page.getByText("reporting", { exact: true }).first()).toBeVisible();
}

export async function addPettyCashLine(
  page: Page,
  opts: { category: string; amount: string; description: string },
) {
  const form = page.locator("form").filter({ has: page.getByRole("button", { name: "Tambah Baris" }) });
  const categorySelect = form.getByRole("combobox").first();
  const descriptionInput = form.getByPlaceholder("Keterangan belanja");
  const amountInput = form.locator('input[inputmode="numeric"]');

  await expect(categorySelect).toBeVisible({ timeout: 20_000 });
  await expect
    .poll(async () => categorySelect.locator("option").count(), { timeout: 20_000 })
    .toBeGreaterThan(1);
  // Tunggu reset form setelah baris sebelumnya (hindari race RHF).
  await expect(descriptionInput).toHaveValue("");
  await expect(amountInput).toHaveValue("");

  await categorySelect.selectOption({ label: opts.category });
  await amountInput.fill(opts.amount);
  await descriptionInput.click();
  await descriptionInput.fill(opts.description);
  await expect(descriptionInput).toHaveValue(opts.description);
  await form.getByRole("button", { name: "Tambah Baris" }).click();
  // Assert baris di tabel — jangan andalkan toast yang bisa masih tampil.
  await expect(page.getByRole("cell", { name: opts.description })).toBeVisible({ timeout: 15_000 });
}

/** Tutup float. Jika ada sisa, isi rekening (butuh permission account.view — biasanya bendahara). */
export async function closePettyCashFloat(
  page: Page,
  opts?: { returnedAmount?: string; accountLabel?: string },
) {
  await page.getByRole("button", { name: "Tutup Float" }).click();
  const dialog = page.getByRole("alertdialog");
  await expect(dialog).toBeVisible();

  if (opts?.returnedAmount) {
    await dialog.locator('input[inputmode="numeric"]').fill(opts.returnedAmount);
    const accountSelect = dialog.getByRole("combobox").first();
    await expect(accountSelect.locator("option")).not.toHaveCount(1, { timeout: 20_000 });
    await accountSelect.selectOption({ label: opts.accountLabel ?? "BCA Operasional (Demo)" });
  }

  await dialog.getByRole("button", { name: "Tutup Float" }).click();
  await expect(page.getByText("closed", { exact: true }).first()).toBeVisible({ timeout: 15_000 });
  await expect(page.getByRole("alertdialog")).toHaveCount(0);
}

export async function uploadHandoverPhoto(page: Page) {
  await page.getByRole("tab", { name: "Lampiran" }).click();
  await page.getByPlaceholder("Judul (opsional)").fill("handover");
  await page.locator('input[type="file"]').setInputFiles({
    name: "serah-terima.png",
    mimeType: "image/png",
    buffer: HANDOVER_PNG,
  });
  await expect(page.getByText("Lampiran berhasil diunggah.")).toBeVisible();
  await page.getByRole("tab", { name: "Detail" }).click();
}
