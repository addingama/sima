import { describe, expect, it } from "vitest";

import { formatIdr } from "./currency";

describe("formatIdr", () => {
  it("memformat nominal dengan locale Indonesia", () => {
    expect(formatIdr("1250000.00")).toMatch(/^Rp\s?1\.250\.000$/);
  });

  it.each([null, undefined, "", "bukan nominal"])("menampilkan Rp 0 untuk %s", (value) => {
    expect(formatIdr(value)).toMatch(/^Rp\s?0$/);
  });
});
