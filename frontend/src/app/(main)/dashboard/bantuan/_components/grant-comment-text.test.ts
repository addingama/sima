import { describe, expect, it } from "vitest";

import {
  commentCountLabel,
  commentSegments,
  insertMention,
  mentionQueryAtCursor,
  toEditorText,
  toStoredBody,
} from "./grant-comment-text";

describe("mention komentar", () => {
  it("mengenali @ di kursor dan menyisipkan nama", () => {
    expect(mentionQueryAtCursor("Tolong @si", 10)).toEqual({ start: 7, query: "si" });
    expect(mentionQueryAtCursor("Sudah selesai", 13)).toBeNull();

    const inserted = insertMention("Tolong @si dicek", 7, 10, { id: 4, name: "Siti" });

    expect(inserted).toEqual({ value: "Tolong @Siti dicek", cursor: 12 });
    expect(toStoredBody(inserted.value, [{ id: 4, name: "Siti" }])).toBe("Tolong @[user:4] dicek");
  });

  it("menampilkan nama dari token dan menghitung komentar", () => {
    expect(toEditorText("Halo @[user:4]", [{ id: 4, name: "Siti" }])).toBe("Halo @Siti");
    expect(commentSegments("Halo @[user:4] ya", [{ id: 4, name: "Siti" }])).toEqual([
      { type: "text", text: "Halo ", offset: 0 },
      { type: "mention", id: 4, name: "Siti", offset: 5 },
      { type: "text", text: " ya", offset: 14 },
    ]);
    expect(commentCountLabel(0)).toBeNull();
    expect(commentCountLabel(1)).toBe("1 komentar");
    expect(commentCountLabel(3)).toBe("3 komentar");
  });
});
