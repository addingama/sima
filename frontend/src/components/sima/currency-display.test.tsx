import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { CurrencyDisplay } from "./currency-display";

describe("CurrencyDisplay", () => {
  it("menampilkan nilai IDR dan mempertahankan class tambahan", () => {
    render(<CurrencyDisplay className="text-success" value="250000.00" />);

    const value = screen.getByText(/^Rp\s?250\.000$/);

    expect(value).toHaveClass("tabular-nums", "text-success");
  });
});
