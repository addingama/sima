import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";

import { GlossaryPage } from "./glossary-page";

describe("GlossaryPage", () => {
  it("menampilkan istilah inti dan tautan kelompok", () => {
    render(<GlossaryPage />);

    expect(screen.getByRole("heading", { name: "Dana Amanah" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Kas/Bank" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Restricted" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Konsep inti" })).toHaveAttribute("href", "#konsep-inti");
  });

  it("menyembunyikan istilah yang tidak cocok dengan pencarian", async () => {
    const user = userEvent.setup();
    render(<GlossaryPage />);

    await user.type(screen.getByLabelText("Cari istilah"), "amanah ledger");

    expect(screen.getByRole("heading", { name: "Amanah Ledger" })).toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Kas/Bank" })).not.toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Master data" })).not.toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Konsep inti" })).toHaveAttribute("href", "#konsep-inti");
  });

  it("menampilkan keadaan kosong bila pencarian tidak ketemu", async () => {
    const user = userEvent.setup();
    render(<GlossaryPage />);

    await user.type(screen.getByLabelText("Cari istilah"), "xyzxyz");

    expect(screen.getByText("Tidak ada istilah yang cocok.")).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Konsep inti" })).not.toBeInTheDocument();
  });
});
