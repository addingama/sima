import { describe, expect, it } from "vitest";

import { buildGrantCompletionPayload } from "./grant-case-panels";

describe("buildGrantCompletionPayload", () => {
  it("mengirim realisasi orang terbantu untuk bantuan kolektif", () => {
    expect(buildGrantCompletionPayload({ beneficiary_scope: "collective" }, "2026-10-04", "Ahmad", "", "2320")).toEqual(
      {
        handed_over_on: "2026-10-04",
        handover_recipient_name: "Ahmad",
        handover_recipient_notes: null,
        actual_beneficiary_count: 2320,
      },
    );
  });

  it("tidak mengirim hitungan realisasi untuk bantuan individual", () => {
    expect(buildGrantCompletionPayload({ beneficiary_scope: "individual" }, "2026-10-04", "", "", "")).toEqual({
      handed_over_on: "2026-10-04",
      handover_recipient_name: null,
      handover_recipient_notes: null,
      actual_beneficiary_count: null,
    });
  });
});
