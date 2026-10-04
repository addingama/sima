import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { useResourceQuery } from "@/hooks/use-resource-query";
import { useAuth } from "@/providers/auth-provider";

import { type GrantDashboardRow, GrantOfficerDashboard, summarizeGrantOfficerWork } from "./grant-officer-dashboard";

vi.mock("@/hooks/use-resource-query", () => ({ useResourceQuery: vi.fn() }));
vi.mock("@/providers/auth-provider", () => ({ useAuth: vi.fn() }));

describe("summarizeGrantOfficerWork", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("menghitung hanya tugas verifikasi dan serah terima milik petugas", () => {
    const rows: GrantDashboardRow[] = [
      { id: 1, application_number: "BNT/1", recipient_name: "A", status: "draft" },
      { id: 2, application_number: "BNT/2", recipient_name: "B", status: "verification", assigned_verifier_id: 10 },
      { id: 3, application_number: "BNT/3", recipient_name: "C", status: "verification", assigned_verifier_id: 99 },
      { id: 4, application_number: "BNT/4", recipient_name: "D", status: "approved", assigned_handover_id: 10 },
      { id: 5, application_number: "BNT/5", recipient_name: "E", status: "approved", assigned_handover_id: 99 },
      { id: 6, application_number: "BNT/6", recipient_name: "F", status: "completed" },
    ];

    expect(summarizeGrantOfficerWork(rows, 10)).toEqual({
      recommendation: 1,
      verification: 1,
      handover: 1,
      completed: 1,
    });
  });

  it("hanya meminta data pengajuan bantuan milik petugas", () => {
    vi.mocked(useAuth).mockReturnValue({
      user: { id: 10, name: "Petugas Lapangan" },
    } as ReturnType<typeof useAuth>);
    vi.mocked(useResourceQuery).mockReturnValue({
      data: { rows: [], pagination: undefined, message: null },
      isLoading: false,
      isError: false,
      refetch: vi.fn(),
    } as unknown as ReturnType<typeof useResourceQuery<GrantDashboardRow>>);

    render(<GrantOfficerDashboard />);

    expect(screen.getByText("Selamat datang, Petugas Lapangan")).toBeInTheDocument();
    expect(useResourceQuery).toHaveBeenCalledWith("/grant-applications", {
      mine: true,
      per_page: 100,
      sort: "created_at",
      direction: "desc",
    });
    expect(useResourceQuery).not.toHaveBeenCalledWith("/dashboard", expect.anything());
  });
});
