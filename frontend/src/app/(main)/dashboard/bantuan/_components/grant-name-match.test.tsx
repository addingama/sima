import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { GrantCasePanels } from "./grant-case-panels";
import { GrantNameMatchBadge, GrantNameMatchBanner, nikRelationLabel } from "./grant-name-match";

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => ({ user: { id: 1, name: "Verifikator", permissions: [] } }),
}));
vi.mock("@/hooks/use-resource-mutation", () => ({
  useWorkflowAction: () => ({ mutate: vi.fn(), isPending: false }),
}));

describe("nikRelationLabel", () => {
  it("menerjemahkan hubungan NIK untuk verifikator", () => {
    expect(nikRelationLabel("same")).toBe("NIK sama");
    expect(nikRelationLabel("different")).toBe("NIK berbeda");
    expect(nikRelationLabel("unknown")).toBe("NIK belum lengkap");
  });
});

describe("GrantNameMatchBadge", () => {
  it("menandai nama yang sama dan jumlah bantuan selesai", () => {
    render(
      <GrantNameMatchBadge
        warning
        completedCount={2}
        matches={[{ id: 9, application_number: "BNT/2026/000009", reason: "Modal usaha" }]}
      />,
    );

    expect(screen.getByText("Nama sama")).toHaveAttribute("title", "BNT/2026/000009 · Modal usaha");
    expect(screen.getByText("Sudah dibantu 2 kali")).toBeInTheDocument();
  });

  it("menyembunyikan badge bila nama tidak sama", () => {
    const { container } = render(<GrantNameMatchBadge warning={false} completedCount={0} />);

    expect(container).toBeEmptyDOMElement();
  });
});

describe("GrantNameMatchBanner", () => {
  it("menjelaskan bahwa bantuan berulang boleh dicek verifikator", () => {
    render(
      <GrantNameMatchBanner
        row={{
          name_match_warning: true,
          completed_assistance_count: 1,
          name_matches: [
            {
              id: 4,
              application_number: "BNT/2026/000004",
              status_label: "Selesai",
              reason: "Sembako",
              nik_relation: "same",
              viewable: true,
            },
            {
              id: 5,
              application_number: "BNT/2026/000005",
              status_label: "Verifikasi",
              reason: "Biaya berobat",
              nik_relation: "different",
              viewable: false,
            },
          ],
        }}
      />,
    );

    expect(screen.getByText(/Bantuan berulang diperbolehkan/)).toBeInTheDocument();
    expect(screen.getByText("Sudah dibantu 1 kali.")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "BNT/2026/000004" })).toHaveAttribute("href", "/dashboard/bantuan/4");
    expect(screen.getByText("BNT/2026/000005").closest("a")).toBeNull();
    expect(screen.getByText(/NIK sama/)).toBeInTheDocument();
    expect(screen.getByText(/NIK berbeda/)).toBeInTheDocument();
  });

  it("tetap tampil di detail meski petugas tidak punya aksi pada kartu", () => {
    render(
      <GrantCasePanels
        row={{
          id: 3,
          status: "completed",
          name_match_warning: true,
          completed_assistance_count: 1,
          name_matches: [
            {
              id: 8,
              application_number: "BNT/2026/000008",
              status_label: "Selesai",
              reason: "Modal usaha",
              nik_relation: "unknown",
              viewable: false,
            },
          ],
        }}
        onRefresh={vi.fn()}
      />,
    );

    expect(screen.getByText("Nama penerima sama")).toBeInTheDocument();
    expect(screen.queryByText("Tugaskan verifikator")).not.toBeInTheDocument();
  });
});
