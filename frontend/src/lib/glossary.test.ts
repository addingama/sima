import { describe, expect, it } from "vitest";

import { filterGlossary, glossaryGroups } from "./glossary";

function names(query: string): string[] {
  return filterGlossary(glossaryGroups, query).flatMap((group) => group.terms.map((term) => term.name));
}

describe("glossaryGroups", () => {
  it("mengurutkan istilah di dalam kelompok menurut abjad", () => {
    for (const group of glossaryGroups) {
      const ordered = group.terms.map((term) => term.name);
      const sorted = [...ordered].sort((left, right) => left.localeCompare(right, "id"));

      expect(ordered).toEqual(sorted);
    }
  });
});

describe("filterGlossary", () => {
  it("mencocokkan nama tanpa memedulikan huruf besar", () => {
    expect(names("dana amanah")).toContain("Dana Amanah");
    expect(names("RESTRICTED")).toContain("Restricted");
  });

  it("mencocokkan alias", () => {
    expect(names("terikat")).toEqual(expect.arrayContaining(["Restricted"]));
    expect(names("buku besar")).toContain("Amanah Ledger");
  });

  it("mencocokkan penjelasan", () => {
    expect(names("jurnal kebalikan")).toEqual(expect.arrayContaining(["Reversal"]));
  });

  it("mengembalikan daftar kosong bila tidak ada yang cocok", () => {
    expect(filterGlossary(glossaryGroups, "xyzxyz")).toEqual([]);
  });

  it("mengabaikan kueri yang hanya berisi spasi", () => {
    expect(filterGlossary(glossaryGroups, "   ")).toEqual(glossaryGroups);
  });

  it("menyembunyikan kelompok yang tidak punya istilah cocok", () => {
    const groups = filterGlossary(glossaryGroups, "audit trail");

    expect(groups.map((group) => group.id)).toEqual(["jejak"]);
    expect(groups[0].terms.map((term) => term.name)).toEqual(["Audit Trail"]);
  });
});
