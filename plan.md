# Overview

A custom WooCommerce plugin to sell **A→B courier services** with real‑time, distance‑based pricing at checkout. V1 focuses on the **Courier Delivery** service only (Standard / Express / Premium tiers) and includes Google Address Autocomplete on the product page, server‑side price calculation, and automatic line‑item pricing in the cart/checkout.

> **Out of scope for v1:** Onfleet dispatch, Mobile Notary module, admin UI for settings (values are configured in code/filters), storefront/landing pages, and post‑fulfillment fees.

---

## 1) Goals & Scope

- Collect **Pickup** and **Drop‑off** on the product page and validate the service area (≤100 miles from Atlanta).
- Let the customer choose **Delivery Type** (Standard, Express, Premium) and **Add‑ons** (Signature \$5, Expedite +25%, Photo share \$3).
- Calculate price with **Google Distance Matrix (driving)** and push the **final price** to cart/checkout.
- Store a full **price breakdown** on the order for transparency.
- Be resilient: server‑side calculation only (no client‑trusted numbers), caching, and graceful fallbacks.

---

## 2) User Flows

### Customer

1. Open the **Courier Delivery** product.
2. Enter **Pickup** and **Drop‑off** (autocomplete), select **Tier** and **Add‑ons**.
3. Click **Calculate & Add to Cart** → price appears with breakdown → item added.
4. Checkout (standard Woo).

### Admin

- Install plugin, set Google API key, and (optionally) tweak rates via code filters.
- View orders with embedded **breakdown** and raw route metadata.

---

## 3) Architecture

**Front‑end (theme‑agnostic, minimal JS):**

- Autocomplete inputs (Google Places Autocomplete) → we capture **`place_id`** for pickup and drop‑off.
- Button **Calculate & Add to Cart** triggers a call to plugin REST endpoint.
- Shows price + breakdown; disables Add‑to‑Cart until a valid quote returns.

**Server (WP plugin, PHP):**

- **REST route** `/wright/v1/quote` performs geocoding/Distance Matrix and computes price.
- **Cart hooks** set the line price and persist meta.
- **Transient cache** for (pickup_place_id, dropoff_place_id) → distance miles (to reduce API cost).

**External services:** Google Places + Distance Matrix APIs (driving mode).

**Data flow:**
Product page → (pickup/dropoff place_id + selections) → **REST** → price & token → cart item data → **re‑calculate server‑side** on add‑to‑cart → set price → checkout → order with breakdown.

---

## 4) Plugin Structure (files)

```
wright-courier-calculator/
  wright-courier-calculator.php           # Bootstrap, hooks
  /includes/
    class-wwc-plugin.php                  # singleton loader
    class-wwc-frontend.php                # enqueue, form renderer, UX guards
    class-wwc-rest.php                    # /wright/v1/quote endpoint
    class-wwc-calculator.php              # core pricing logic
    class-wwc-google.php                  # Places/Distance Matrix client + caching
    class-wwc-cart.php                    # add-to-cart, set prices, meta persistence
    class-wwc-order.php                   # admin order meta display
    helpers.php                           # sanitization, money utils, rounding
  /assets/js/
    product.js                            # Autocomplete + quote request + UI
  /assets/css/
    product.css
  /templates/
    product-fields.php                    # Field markup (loaded via hook)
    breakdown.php                         # Optional summary component
  /config/
    rates.php                             # v1 constants (can be filtered)
```

---

## 5) Configuration (v1 constants, filterable)

```php
const WWC_SERVICE_CENTER = [ 'lat' => 33.7490, 'lng' => -84.3880 ]; // Atlanta
const WWC_SERVICE_RADIUS_MILES = 100;

const WWC_TIERS = [
  'standard' => [ 'label' => 'Standard', 'base' => 15.00, 'per_mile' => 1.50, 'first_miles_free' => 5 ],
  'express'  => [ 'label' => 'Express',  'base' => 25.00, 'per_mile' => 2.00, 'first_miles_free' => 5 ],
  'premium'  => [ 'label' => 'Premium',  'base' => 40.00, 'per_mile' => 4.00, 'first_miles_free' => 5 ],
];

const WWC_ADDONS = [
  'signature'   => [ 'label' => 'Signature',   'type' => 'flat', 'value' => 5.00 ],
  'photo_share' => [ 'label' => 'Photo share', 'type' => 'flat', 'value' => 3.00 ],
  'expedite'    => [ 'label' => 'Expedite',    'type' => 'mult', 'value' => 1.25 ],
];

const WWC_FUEL_SURCHARGE = 0.05; // 5%
```

All values are filterable, e.g., `apply_filters('wwc_rates_tiers', WWC_TIERS)` to allow adjustments without an admin page.

---

## 6) Product Targeting

Apply logic **only** to the designated product(s):

- By **product ID - 177. Price is set to by default and should be updated according to the calculation** list in code, or ()
- By **product tag** (e.g., `courier-service`).

---

## 7) Front‑end UX Details

- Two inputs with Google Autocomplete: **Pickup**, **Drop‑off** (store both text and `place_id`).
- Select **Delivery Type** (radio or variant sync) and **Add‑ons** (checkboxes).
- CTA: **Calculate & Add to Cart** (disabled until both places are chosen).
- After quote: show **Price breakdown** and an info note:

  - _After‑hours/weekend surcharge may be billed post‑fulfillment per policy._

- Prevent add‑to‑cart if:

  - Distance > 100 miles, addresses invalid, or API fails (show clear error).

---

## 8) REST Contract: `/wright/v1/quote`

**Request (JSON):**

```json
{
  "pickup": { "place_id": "...", "label": "123 Main St, ..." },
  "dropoff": { "place_id": "...", "label": "456 Pine Ave, ..." },
  "tier": "standard|express|premium",
  "addons": ["signature", "photo_share", "expedite"]
}
```

**Response (JSON):**

```json
{
  "ok": true,
  "miles": 12.4,
  "pricing": {
    "base": 15.0,
    "extra_miles": 7.4,
    "per_mile": 1.5,
    "distance_subtotal": 26.1,
    "flat_addons": 8.0,
    "mult_addons": 1.25,
    "fuel": 1.7,
    "total": 36.8
  },
  "breakdown_html": "<ul>…</ul>"
}
```

**Errors:** `{ ok:false, code:"OUT_OF_RADIUS" | "API_FAIL" | "INVALID_INPUT", message:"…" }`.

---

## 9) Core Pricing Algorithm (server)

**Inputs:** miles (driving), tier, addons.

```
free = tier.first_miles_free
paid_miles = max(0, miles - free)
subtotal = tier.base + paid_miles * tier.per_mile
subtotal *= (addons includes 'expedite') ? 1.25 : 1
subtotal += (addons includes 'signature') ? 5 : 0
subtotal += (addons includes 'photo_share') ? 3 : 0
fuel = round2(subtotal * 0.05)
TOTAL = round2(subtotal + fuel)
```

**Rounding:** 2 decimals; currency = store currency.

---

## 10) Distance Computation

- Use the **Distance Matrix API** with `origins=pickup.place_id`, `destinations=dropoff.place_id`, `mode=driving`.
- Convert meters → miles (`/1609.344`), round to 2 decimals.
- **Cache** in `wp_transients` with key `wwc_dm_{A}_{B}` for 12–24 hours.
- Fallback: if API fails, try a second request; otherwise return an error (do **not** guess distance for billing).

---

## 11) Cart/Checkout Integration

- Add hidden fields to the product form for `pickup_place_id`, `dropoff_place_id`, `tier`, `addons[]` and **DO NOT** trust client totals.
- On **add to cart** (`woocommerce_add_cart_item_data`): persist **raw inputs** (place_ids, labels, tier, addons).
- On **price set** (`woocommerce_before_calculate_totals`):

  - Re‑compute server‑side using the saved inputs.
  - Set item price via `$cart_item['data']->set_price( $total )`.

- On **order creation** (`woocommerce_checkout_create_order_line_item`): add breakdown fields (miles, base/per‑mile, surcharges) to line item meta.
- Enforce **single booking per order** (v1) via `woocommerce_add_to_cart_validation`.

---

## 12) Admin Order View

- Render a compact **Price Breakdown** panel on the line item:

  - Miles; Tier; Base; Paid miles × rate; Add‑ons; Fuel; **Total**.
  - Raw addresses and Google map link for auditing.

---

## 13) Security & Data Integrity

- REST requests require a **nonce**; sanitize all inputs.
- Never use client‑supplied totals; always re‑calculate on server.
- Rate‑limit the REST route to prevent abuse (simple token bucket per IP/session).
- Hide Google key in backend; only expose Places JS key with proper **HTTP referrer restrictions**.

---

## 14) Edge Cases

- Zero‑distance (same building): charge **base** of the selected tier.
- Extremely short routes (< free miles): still charge **base** only.
- Distances slightly exceeding 100 miles → block with clear message.
- Quantity > 1 → either block or multiply price (v1: **block** to avoid ambiguity).
- Multiple courier items in cart → block; instruct one booking per order.

---

## 15) Test Matrix (QA)

1. **Standard, 10 miles, no add‑ons** → Base + 5 paid miles + fuel.
2. **Express, 42 miles, Signature + Photo** → Check flat add‑ons + fuel.
3. **Premium + Expedite, 2 miles** → Only base × 1.25 + fuel.
4. **Out of radius (120 miles)** → Block with message.
5. **API fail** (simulate) → Friendly error; cannot add to cart.
6. **Checkout edit address** → Price re‑computes correctly.
7. **Currency** not USD → correct formatting/decimals.

---

## 16) Deployment Steps

1. Get Google API key (enable Places + Distance Matrix); set referrer/IP restrictions.
2. Install plugin; set constants in `config/rates.php` and add product tag/ID mapping.
3. Add the field template hook on the **Courier Delivery** product page.
4. Smoke test the REST endpoint with two ATL addresses.
5. Full QA with the test matrix.

---

## 17) Future Enhancements (post‑v1)

- **Admin Settings UI** (rates, surcharges, hours, radius, messages).
- **Onfleet integration:** create linked pickup→drop‑off tasks, Photo POD always, Signature when purchased; auto‑assign rules.
- **Mobile Notary module:** Jonesboro origin, \$35 up to 15 mi, then \$1.25/mi round‑trip, \$2/signature, After‑hours \$25 (post‑fulfillment), printing/additional signer/extra stop logic.
- **Multi‑job per order** with shipment grouping.
- **Post‑fulfillment invoices** (waiting time/after‑hours) from order admin.
- **Rate confirmation token** to tie the quote to a cart for X minutes (anti‑stale).
- **Maps provider switch** (Mapbox) via interface.

---

## 18) Acceptance Criteria (v1)

- Customer cannot add the product without valid **pickup & drop‑off** addresses and a **computed quote**.
- Cart/checkout show the **correct, server‑calculated price** for the selected tier and add‑ons.
- Line item meta contains the **full breakdown** and raw route data.
- Orders placed inside the **100‑mile radius** only.
- All calculations succeed without trusting client totals; API errors are handled gracefully.

---

### Notes

- We’ll place a small notice on the product page: _“After‑hours/weekend surcharge may be billed post‑fulfillment per policy.”_
- For performance/cost, cache distances by place‑id pair; clear cache daily.
