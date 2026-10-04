import { describe, expect, it } from "vitest";

import { grantApplicationResource } from "./grant-application";

function field(name: string) {
  const result = grantApplicationResource.formFields.find((item) => item.name === name);
  if (!result) {
    throw new Error(`Field ${name} tidak ditemukan.`);
  }

  return result;
}

describe("grantApplicationResource", () => {
  it("menggunakan default penerima perorangan dan pembayaran tunai", () => {
    expect(grantApplicationResource.getCreateDefaults?.()).toEqual({
      beneficiary_type: "individual",
      beneficiary_scope: "individual",
      payment_method: "cash",
      bank_account_owner_type: "beneficiary",
    });
  });

  it("menampilkan field dampak hanya untuk bantuan kolektif", () => {
    expect(field("target_beneficiary_count").visibleWhen?.({ beneficiary_scope: "collective" })).toBe(true);
    expect(field("target_beneficiary_count").visibleWhen?.({ beneficiary_scope: "individual" })).toBe(false);
    expect(field("beneficiary_count_method").visibleWhen?.({ beneficiary_scope: "collective" })).toBe(true);
  });

  it("menampilkan field PIC hanya untuk penerima organisasi", () => {
    expect(field("organization_pic_name").visibleWhen?.({ beneficiary_type: "organization" })).toBe(true);
    expect(field("organization_pic_name").visibleWhen?.({ beneficiary_type: "individual" })).toBe(false);
    expect(field("recipient_identity_number").visibleWhen?.({ beneficiary_type: "organization" })).toBe(false);
  });

  it("meminta penjelasan rekening pribadi hanya untuk PIC organisasi", () => {
    const values = {
      beneficiary_type: "organization",
      payment_method: "transfer",
      bank_account_owner_type: "pic",
    };

    expect(field("bank_account_holder_relationship").visibleWhen?.(values)).toBe(true);
    expect(field("bank_account_use_reason").visibleWhen?.(values)).toBe(true);
    expect(field("bank_account_use_reason").visibleWhen?.({ ...values, bank_account_owner_type: "beneficiary" })).toBe(
      false,
    );
  });

  it("tidak mengirim assignment dari form umum", () => {
    const payload = grantApplicationResource.mapToPayload?.({
      beneficiary_type: "individual",
      assigned_verifier_id: 10,
      assigned_handover_id: 20,
      assigned_verifier: { id: 10 },
      assigned_handover: { id: 20 },
    });

    expect(payload).not.toHaveProperty("assigned_verifier_id");
    expect(payload).not.toHaveProperty("assigned_handover_id");
  });
});
