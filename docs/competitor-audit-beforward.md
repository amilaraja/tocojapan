# Competitor audit — BE FORWARD (beforward.jp)

What the largest Japanese-used-car exporter does on their site and what Toco can borrow. Ordered roughly by impact-per-effort.

---

## Quick wins (existing surfaces, small additions)

- **Live result count on search forms.** Beforward shows `TOYOTA (86,620)` inline in the make dropdown and `N items match` next to the submit button — updates on every change. Add a live count next to each Make/Body option and a live total on the homepage search panel; kills "is this worth pressing Search" hesitation.
- **Two-tier price block: `Price $X` + `Total Price $Y CIF to {port}`.** We already have the data via the destination cookie + `@cif()`. Promote the CIF line so it reads at ~80% the weight of the FOB price — Beforward effectively treats them as equal-weight.
- **Price-tier tiles on the homepage** (`< $500`, `$500–$1k`, `$1k–$1.5k`, …) linking to filtered stock. We have popular chips; price ladders convert better because they map to a buyer's actual budget bracket.
- **`data-nosnippet` on prices in the detail page** — keeps stale Google SERP snippets from showing wrong amounts after we re-price.

## Trust + conversion

- **Per-destination quote breakdown table.** RoRo vs Container toggle, what each price includes (Marine Insurance, BF Warranty, ISF, customs entry, translations). Toco has the math; rebuild the bottom estimator as a proper table that names every line item.
- **Marine Insurance + Inspection + Warranty as toggled add-ons** at quote time, with tooltips. We currently roll `insurance_pct` silently into CIF — surfacing it as a checkbox with a tooltip ("covers total loss by shipwreck") gives buyers control and reads as more transparent.
- **Named sales reps with WhatsApp links** floating on the detail page (BF: "Yasu", `+81 80 9277 3296`). High-trust signal for cross-border buyers. We have a `whatsapp_number` setting; render it as a sticky button on the detail page.
- **"X people inquiring" live counter** next to the inquiry CTA. Social proof, cheap to fake, high-converting.
- **"3rd Party Seller" / temporary-chassis disclaimer.** We already redact chassis (`HA4-238****`); add expectation-setting tooltip ("chassis number provided by the third-party supplier, may be temporary").

## SEO + structured data

- **Schema.org `Product + Car` JSON-LD on every detail page** — `sku`, `brand`, `image[]`, `offers.priceCurrency`, `availability=InStock`, `itemCondition=UsedCondition`. Single biggest SEO gap right now: we render great meta tags but no structured data, so Google can't show price-rich snippets.
- **Schema.org `FAQPage` on the homepage.** BF gets visible accordion + JSON-LD double-duty out of the same content.
- **Per-language URL prefix** (`/sw/`, `/pt/`, `/ar/`). We use the Google Translate widget — client-side and invisible to Google. Real i18n URLs (with `hreflang`) are what ranks in non-English markets.
- **Per-country landing pages** (`/beforward_kenya`, etc.). High-intent: someone searching "Japanese cars Kenya" lands on a Kenya-specific page with their port pre-selected. Easy to implement on top of our Countries + destination cookie.
- **Detail URL pattern `/{make}/{model}/{stockcode}/id/{id}/`** — slug + ID belt-and-braces. Our slug-only URLs break if a vehicle title is edited; theirs survive renames.

## UI / patterns

- **Top-bar Account / Favorites / Cart triad** with **Favorites visible to guests too** (saves to localStorage, merges on login). Lower friction than gating behind login like we do.
- **Promo icon system** with filterable badges (clearance, campaign, Prime Seller). On listing cards as small icons; click to filter. Maps directly to our SOLD badge pattern — could add `featured`, `low_stock`, `new_arrival`.
- **Shipping schedule PDFs per region** — `shipping_schedule_europe.pdf`, etc. Low-tech, high-trust artefact buyers actually download and share.
- **Car Arrival Progress (CAP) page** — branded public shipment tracker. We already capture B/L + vessel + ETA + carrier URL on shipped orders; surfacing them on a public `/track/{order_no}` lookup (no login) would let buyers share with their customs broker.

## Not worth borrowing

- **Cart flow** — adds checkout complexity without solving anything our bank-transfer flow doesn't already cover.
- **Loyalty points** — premature for the current catalog size.
- **25 country sub-domains** — heavy ops burden; per-language URL prefix delivers 80% of the same lift.

---

## Suggested first sprint

Pick 3 from the top of the list:

1. **Schema.org structured data** on the detail page — biggest SEO uplift.
2. **Live result count + price-tier tiles** on the homepage — biggest conversion lift on existing traffic.
3. **Per-destination quote table + add-on toggles** on the detail page — biggest trust lift; pairs well with the CIF cookie we already have.
