import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { RelationSelect } from "@/components/sima/crud/relation-select";

vi.mock("@/hooks/use-resource-query", () => ({
  useResourceQuery: () => ({
    data: {
      rows: [
        { id: 1, name: "Budi Santoso", code: "U1" },
        { id: 2, name: "Siti Aminah", code: "U2" },
        { id: 3, name: "Bakti Sosial Ramadan", code: "DEMO-PRG-001" },
      ],
    },
    isLoading: false,
  }),
}));

describe("RelationSelect searchable", () => {
  it("renders combobox input when searchable", () => {
    render(
      <RelationSelect
        resource="/programs"
        labelKey="name"
        value=""
        onChange={() => undefined}
        placeholder="Cari event..."
        searchable
      />,
    );

    expect(screen.getByPlaceholderText("Cari event...")).toBeInTheDocument();
  });

  it("falls back to native select when not searchable", () => {
    const { container } = render(
      <RelationSelect
        resource="/programs"
        labelKey="name"
        value=""
        onChange={() => undefined}
        placeholder="Pilih event..."
      />,
    );

    expect(container.querySelector("select")).toBeTruthy();
  });
});
