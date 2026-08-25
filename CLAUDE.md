# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress site ("La Via delle Terme") for a thermal spa booking platform. The codebase consists of one active custom plugin and a child theme, integrating with WooCommerce and an external booking system called TermeGest.

## Build Commands

### plugin-custom-skianet (main plugin)

```bash
cd wp-content/plugins/plugin-custom-skianet

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Build JS/CSS assets (CSS -> prefixed/minified CSS, JS -> bundled/minified IIFE)
npm run build

# Watch for changes during development
npm run watch

# After composer update, runs pint (code style) and rector (refactoring) automatically
composer update
```

### Code Quality (plugin-custom-skianet only)

```bash
cd wp-content/plugins/plugin-custom-skianet

# PHP code style (Laravel Pint)
./vendor/bin/pint

# PHP refactoring (Rector)
./vendor/bin/rector process --clear-cache --no-diffs
```

No test framework is configured in this repo.

## Architecture

### Main Plugin: plugin-custom-skianet

Handles the full booking lifecycle:
- SOAP API communication with TermeGest (external booking management system)
- Availability checking via WP cron (daily) — results stored as JSON files in `assets/data/`
- Cart/checkout integration with WooCommerce, using PHP sessions to carry booking metadata
- License code assignment after payment (via WooCommerce License Delivery plugin)
- Email notifications for booked and non-booked products
- Custom WooCommerce order statuses

All classes follow the singleton pattern with `get_instance()` and are initialized in `plugins_loaded` hook:

| Class | File | Responsibility |
|---|---|---|
| `TermeGest_API` | `includes/class-termegest-api.php` | SOAP client wrapper for TermeGest GetReserv + SetInfo APIs |
| `Booking_Handler` | `includes/class-booking-handler.php` | Core booking flow orchestration |
| `Booking_Cart_Handler` | `includes/class-booking-cart-handler.php` | Stores booking metadata in WooCommerce cart/order items |
| `Booking_Code_Assignment` | `includes/class-booking-code-assignment.php` | Assigns license codes on `woocommerce_payment_complete` |
| `Booking_Termegest_Sync` | `includes/class-booking-termegest-sync.php` | Syncs orders to TermeGest via `setVenduto` + `setPrenotazione` |
| `Booking_Email_Notification` | `includes/class-booking-email-notification.php` | Sends booking confirmation emails on `woocommerce_payment_complete` |
| `Booking_Nonbooking_Email` | `includes/class-booking-nonbooking-email.php` | Sends coupon emails for non-booking products |
| `Booking_Order_Status` | `includes/class-booking-order-status.php` | Custom WooCommerce order status management |
| `Booking_Redirect` | `includes/class-booking-redirect.php` | Post-purchase redirect logic |
| `Booking_Checkout_Fields` | `includes/class-booking-checkout-fields.php` | Custom fields at checkout |
| `Booking_Only_Handler` | `includes/class-booking-only-handler.php` | Handler for booking-only products |
| `Availability_Checker` | `includes/class-availability-checker.php` | Daily cron to check availability via TermeGest |
| `Termegest_Encryption` | `includes/class-termegest-encryption.php` | AES-256-CBC encryption for TermeGest location params |

### SOAP API Integration (TermeGest)

Requires PHP `ext-soap`. The plugin communicates with two SOAP endpoints: 
- `https://www.termegest.it/getReserv.asmx` — get availability and reservations
- `https://www.termegest.it/setinfo.asmx` — set sold items and bookings

PHP classes generated for the SOAP clients live in `src/TermeGestGetReserv/` and `src/TermeGestSetInfo/`. Config types are in `config/`. Autoloaded via Composer PSR-4.

SOAP responses wrap XML inside a schema envelope — the `AnyXML` utility class extracts and converts them to `stdClass` objects. All SOAP calls are wrapped in try-catch.

Helper functions wrapping the API class are in `includes/termegest-api-functions.php`.

**Location slug → TermeGest name mapping** (encrypted before SOAP calls):
- `terme-genova` → `Genova`
- `monterosa-spa` → `Monterosa`
- `terme-saint-vincent` → `Saint Vincent`

### Booking Session & Cart Flow

Booking metadata is stored in `$_SESSION['termegest_booking']` (standard PHP session) during the booking form interaction. On cart add, the session data is attached to the cart item via `woocommerce_add_cart_item_data` and restored via `woocommerce_get_cart_item_from_session`. At checkout, booking fields are written to WooCommerce order item meta (`_booking_id`, `_booking_location`, `_booking_date`, `_booking_fascia_id`, `_booking_ticket_type`, `_booking_num_male`, `_booking_num_female`, `_health_certificate`).

### Payment Flow

On `woocommerce_payment_complete` (priority order):
1. `Booking_Code_Assignment` (priority 10) — assigns license codes
2. `Booking_Termegest_Sync` — sends `setVenduto` (all items) and `setPrenotazione` (booking items) to TermeGest
3. `Booking_Email_Notification` (priority 20) — sends confirmation email (booking details + coupon codes)
4. `Booking_Nonbooking_Email` — sends coupon email for non-booking products in mixed orders

### License Code Assignment & Per-Item Scoping

WooCommerce License Delivery's `wc_ld_license_codes` DB table associates codes with `order_id` + `product_id` only — it has **no `order_item_id` column**. This matters because an order can contain two separate line items for the *same product* with different booking details (e.g. same ticket type booked for two different dates/times/locations). A naive `WHERE order_id = %d AND product_id = %d` query returns **all** codes for that product in the order to every matching item, not just the ones belonging to that specific item.

`Booking_Cart_Handler::get_item_license_codes( $item )` is the single shared helper that gets this right: it fetches the full code list for order+product, then slices it using the **cumulative quantity of preceding sibling items with the same product** (in `$order->get_items()` iteration order — the same order WC License Delivery assigns codes in) as the offset, and takes the current item's own quantity from there. Always call this shared method (passing the `WC_Order_Item`, not raw ids) rather than re-querying `wc_ld_license_codes` directly — `Booking_Termegest_Sync::get_license_codes_for_item()` used to have its own duplicate (and buggy, non-item-scoped) copy of this query; it now delegates to the shared method instead.

**Known not-yet-fixed instances of the same non-item-scoped query pattern** (found but out of scope when the shared method was fixed): `Booking_Only_Handler::get_codes_from_order()` (`class-booking-only-handler.php`) and the two `controllo-codici/controllo_codici_DB*.php` shortcode queries. These would show the same duplication symptom if a customer's account/order ever contains two line items of the same product with different booking data.

### Availability Cron

The `termegest_check_availability` daily cron populates JSON files at `assets/data/availability-{location}.json` with structure:
```json
{ "availability": { "YYYY-MM-DD": true } }
```
The booking form JS fetches these local JSON files to disable unavailable dates before making any AJAX calls.

**Month calculation**: Always use `new DateTime('first day of next month')` — never `new DateTime('+1 month')`. On months with 31 days (e.g. May 31), `+1 month` overflows to day 31 of June → rolls to July 1, skipping June entirely.

**Timezone**: Always pass `wp_timezone()` when constructing `DateTime` objects in the cron. PHP's `date()` uses the server timezone (likely UTC); the site is Europe/Rome (UTC+2). At end-of-month the two can disagree and produce the wrong month. Pattern:
```php
$wp_timezone = wp_timezone();
$current_date = new DateTime('first day of this month', $wp_timezone);
$next_date    = new DateTime('first day of next month', $wp_timezone);
```

**Category mapping** in `check_location_availability()`:
- December / January → `pm`
- All other months → `p2`

**Triggering manually** (for debug/test):
```bash
wp cron event run termegest_check_availability --path=/home/customer/www/laviadelleterme.it/public_html
```

**After month turn**: re-run the cron manually so the JSON covers the correct current+next months. A stale JSON from the previous month leaves the new "next month" missing from the file.

### Booking Form JavaScript

`assets/js/src/booking-form.js` is bundled as an IIFE with `globalName: 'BookingForm'`. It implements progressive field enablement: Location → Date → Ticket Type → Time Slot → Quantity. Availability is checked in two stages: (1) local JSON files for disabled dates, (2) AJAX to `wp_ajax_check_availability_api` for slot-level data. All CSS is scoped to `.skianet-booking-wrapper` via PostCSS prefix-selector at build time.

**Calendar range requirement**: The calendar must always show the full current month + full next month (first day of current month → last day of next month). Do not use a fixed-days window (e.g. "today + 60 days") — it would cut off the end of next month or expose a partial third month. This range is computed as:
```javascript
const firstDayCurrentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const lastDayNextMonth     = new Date(today.getFullYear(), today.getMonth() + 2, 0);
```
The PHP cron mirrors this: it generates availability data for exactly the same two months.

**Disabled dates — whitelist logic**: `buildDisabledDatesArray` iterates the full calendar range (dateMin→dateMax) and disables any date where `availability[dateStr]` is falsy (missing OR false). Do not use blacklist logic (filtering only explicit `false` entries) — dates absent from the JSON would appear enabled even if never checked by the cron.

**Local date formatting**: Never use `new Date(...).toISOString().split('T')[0]` to produce `dateMin`/`dateMax` strings. `toISOString()` converts to UTC, shifting the date by one day for Europe/Rome (UTC+2) at midnight. Use `formatLocalDate(d)` which reads `getFullYear/getMonth/getDate` directly.

### Theme

`wp-content/themes/hello-theme-child-master/` — Child theme of Hello Elementor:
- `style.css` — Custom WooCommerce and site-wide CSS overrides (main stylesheet; must stay at theme root, WP requirement)
- `assets/css/`, `assets/js/` — global, non-feature-specific assets: `mobile-menu-style.css`, `script.js`, plus two
  **cross-feature** stylesheets that deliberately do *not* live in a feature folder because more than one page prints
  the markup they style — `wc-notices.css` (all WooCommerce notices, enqueued site-wide) and `booking-status.css`
  (the per-item booking/payment badge, enqueued on both order-received and checkout). Put a stylesheet here, not in a
  feature folder, whenever the markup it targets is emitted by a hook that fires on several pages.
- `checkout/`, `order-pay/`, `thankyou/`, `satispay/`, `my-account/`, `controllo-codici/` — one folder per feature module, each bundling its own PHP + CSS/JS, required individually from `functions.php`
- `woocommerce/pdf/Templates/` — PDF templates for invoices, packing slips, credit notes
- `woocommerce/emails/` — Custom WooCommerce email templates (prefixed with `__` = disabled/legacy)
- `ga4-elementor-compat.php` — GA4 event fallbacks for Elementor pages (see below)
- `performance-optimization.php` — disables Gutenberg, emoji scripts, oEmbed, XML-RPC, comments (all post types, including products — no WC product reviews in use), throttles Heartbeat, limits post revisions to 3, strips self-pings. Required from `functions.php`; do **not** re-add a `?ver` query-string-stripping filter here — it was removed because it silently defeated the theme's asset cache-busting (see below).

**Why this structure**: the theme has no single template-heavy surface to override, just a handful of independent WooCommerce page customizations — grouping each by feature (rather than the classic WP `inc/`-by-type convention) makes it obvious what touches what, and mirrors `plugin-custom-skianet`'s one-class-per-responsibility pattern.

**`woocommerce/` is reserved — do not add feature folders there.** WooCommerce resolves theme template overrides by exact path (e.g. `woocommerce/checkout/thankyou.php` is the real order-received template, `woocommerce/checkout/form-pay.php` the real order-pay template, `woocommerce/myaccount/*.php` the real My Account templates — note: no hyphen). This theme's own `checkout/`, `order-pay/`, `my-account/` folders live at theme root specifically to avoid colliding with those reserved paths; a file placed at, say, `woocommerce/checkout/thankyou.php` would silently replace WooCommerce's real template with whatever this repo put there (these modules contain only hooked PHP, not full markup), breaking the page. Only actual template overrides belong under `woocommerce/`.

**Global function naming**: loose (non-class) functions in the theme are prefixed `laviadelleterme_` to avoid collisions in the global namespace (WordPress/plugins share one global function table).

**Asset versioning**: `functions.php` enqueues every theme asset with a single version — `wp_get_theme()->get( 'Version' )`, i.e. the `Version:` header in `style.css`. Bump that header whenever a theme CSS/JS file changes, otherwise browsers keep serving the cached copy after a deploy (relevant since deploys here are sometimes manual FTP, not just `git push` — see "Manual uploads" below).

**`controllo-codici/`**: two public shortcodes (`controllo_codici_prezzo_pieno`, `controllo_codici_promo`) that query `{$wpdb->prefix}wc_ld_license_codes` and print remaining unassigned license-code counts per product. No capability check — anyone viewing a page containing the shortcode sees these counts; access control depends entirely on the page(s) hosting them being otherwise restricted.

**`my-account/my-account.php`**: custom login/registration URLs and post-login/registration redirect logic. Redirect targets (explicit `redirect`/`redirect_to` params and the `Referer` fallback) are validated via `laviadelleterme_is_local_url()`, which compares hosts with `wp_parse_url(...,  PHP_URL_HOST)` — do not go back to a plain `strpos($url, home_url())` substring check, it's bypassable (`https://evil.tld/?x=` + `home_url()` passes it) and was an open-redirect vector here until fixed.

### GA4 / Elementor Compatibility

`wp-content/themes/hello-theme-child-master/ga4-elementor-compat.php` (included from `functions.php`) provides fallbacks for GA4 events that rely on WooCommerce template hooks. Elementor Pro replaces the WC PHP template via `template_include`, so those hooks never fire on Elementor-built pages.

**Pattern**: hook `wp_footer` at priority 5 (before `print_tracking_calls` at priority 10), guard with `did_action('<wc_hook>') > 0` to avoid double-tracking on standard WC pages, then call `$event->track()` manually.

**Events with fallbacks:**

| Event | WC hook that breaks on Elementor | Guard |
|---|---|---|
| `view_item` | `woocommerce_after_single_product_summary` | `did_action('woocommerce_after_single_product_summary') > 0` |
| `view_item_list` | `woocommerce_product_loop_end` | `did_action('woocommerce_product_loop_end') > 0` |

`view_item_list` fallback reads products from `$wp_query->posts` — WordPress populates this regardless of Elementor.

**`select_item` cannot be fixed** — it injects click listeners on `.products .post-{id} a` (WC loop DOM); Elementor product widgets use a different DOM structure.

**Safe events (no fallback needed):** `view_cart`, `begin_checkout`, `add_*_info`, `view_account`, `view_order`, `view_sign_up` fire inside WC shortcodes Elementor embeds unchanged. All data/action events (`login`, `add_to_cart`, `purchase`, `refund`, etc.) have no template dependency.

**Note on `View_Item_Event.php`**: the hook was changed from `woocommerce_before_single_product` to `woocommerce_after_single_product_summary`. Both are inside the standard WC template and both are equally bypassed by Elementor; the compat guard uses the same hook the event now listens on.

### Thank You Page & Order-Pay Payment Status

`wp-content/themes/hello-theme-child-master/thankyou/thankyou.php` and `order-pay/order-pay.php` (both required from `functions.php`) make the order-received and order-pay pages reflect the order's **actual** payment status instead of always looking successful.

**Why this matters**: `_booking_id` order item meta (see `Booking_Cart_Handler::add_booking_data_to_order_items`) is written at checkout time — before any payment happens — so its mere presence can't be used to mean "booking confirmed". Likewise the Elementor heading widget on the thank-you page has hardcoded text ("Pagamento") that used to be replaced with "Ordine ricevuto!" unconditionally.

**`laviadelleterme_thankyou_order_is_confirmed( $order )`** (defined in `thankyou.php`) is the single source of truth: `true` only for order status `processing`, `completed`, `booked`, `not-booked`. `booked`/`not-booked` are reached only by passing through `completed` first (see `Booking_Order_Status`), so plain `$order->is_paid()` is not enough — those two statuses aren't in WC's default paid-statuses list.

Driven by that helper, `thankyou.php` conditionally:
- Rewrites the Elementor H1 to "Ordine ricevuto!" (confirmed) vs "Ordine in attesa di pagamento" (not confirmed).
- Overrides `woocommerce_thankyou_order_received_text` (the generic WC notice) when not confirmed.
- Shows a per-item badge: green "Prenotazione confermata", amber "Usa i codici per completare la prenotazione" (non-booking item, confirmed order), or red "In attesa di pagamento" (order not confirmed, checked first — takes priority over the `_booking_id` check). It hooks `woocommerce_order_item_meta_end`, which **also fires in the order-pay summary table**, so the badge appears on two pages and its CSS lives in the shared `assets/css/booking-status.css`, enqueued for both — not in `thankyou.css`.
- Outputs a "Completa il pagamento" CTA box (`woocommerce_thankyou` hook) linking to `$order->get_checkout_payment_url()`, gated on `$order->needs_payment()` so it doesn't show for e.g. on-hold/BACS orders that don't need customer action.

**Mollie return-redirect vs. webhook race (client-side polling fix)**: even for a successful card payment, the customer's browser can land on the thank-you page *before* the order is actually marked paid. Mollie's plugin (`MolliePaymentGatewayHandler::getReturnRedirectUrlForOrder()`) queries Mollie's API on the browser's return trip only to decide *where* to redirect (order-received vs. back to order-pay on failure) — it does **not** update the WooCommerce order status there. The order status is only ever flipped by the separate, asynchronous Mollie webhook (`MollieOrderService::onWebhookAction()`, a different HTTP request sent by Mollie's servers, not the browser). If that webhook lands a moment after the browser's redirect, `thankyou.php` renders "in attesa di pagamento" for an order that is, in reality, already paid — confirmed via a real order's debug.log where `payment_complete`/status-transition logging all happened after the point the customer would have already been redirected. Fix: `thankyou.php` prints a small inline-JS polling snippet (`woocommerce_thankyou`, priority 20) **only** inside the same `! laviadelleterme_thankyou_order_is_confirmed( $order ) && $order->needs_payment()` gate used by the CTA box above — so confirmed orders and orders that genuinely don't need action (e.g. BACS) get zero extra script/requests. It calls a new `wp_ajax(_nopriv)_laviadelleterme_check_order_confirmed` endpoint (validates `$order->key_is_valid()`, same check the Mollie webhook itself uses, to stop order-status enumeration) every 2.5s for up to ~30s (12 attempts), and does a single `location.reload()` the moment the order becomes confirmed. After ~30s with no change (e.g. a real, slow bank-transfer order) it stops polling and leaves the real "awaiting payment" state on screen — it does not loop forever.

`order-pay/order-pay.php` handles the retry-payment page itself. It detects the one validation failure that WooCommerce reports without redirecting — the terms-and-conditions checkbox left unticked (`woocommerce_pay` POSTed with an empty `terms`, so `$_POST` is still readable during the re-render) — and on that condition adds a `order-pay-terms-error` body class (styled red in `checkout/checkout.css`) plus a small inline `wp_footer` script that scrolls the row into view and focuses the checkbox (`focus({preventScroll:true})`, otherwise the browser's own jump cancels the smooth scroll); the class is removed on `change` once the box is ticked.

**Removed (do not re-add):** this file used to also hook `template_redirect` at priority 20 to print a generic "payment may still be pending, wait 10 minutes" notice on any `woocommerce_pay` POST that reached that priority. It existed for Mollie, which blocked creating a new payment while a previous one was still "active and non-cancellable" for the order and did so **silently** (no WC error, no JS error, plain 200). Neither environment runs Mollie any more, and the notice also fired on ordinary terms-validation failures, showing a second misleading message next to the real error.

**Checkout styling**: `checkout/checkout.css` (enqueued from `functions.php` on `is_checkout()`) styles generic WooCommerce checkout markup — notice **positioning**, the `#payment` payment-methods list (Stripe card/UPE and Satispay), the terms checkbox, the place-order button, and the whole order-pay page layout. `is_checkout()` covers both the normal `/checkout/` page and the `/checkout/order-pay/…` retry page (same WC template). Note: `is_checkout_pay_page()` was tried first for a narrower, order-pay-only enqueue but the `<link>` tag never appeared in the rendered page on this Elementor setup — root cause not fully diagnosed (suspect the enqueue callback ran against a build of `functions.php` on the server that predated the change, since deploys here can be manual FTP uploads — see "Manual uploads" below); switching to the broader, well-established `is_checkout()` conditional resolved it.

**Scoping order-pay rules without a body class**: WordPress/WooCommerce give the order-pay page no distinguishing body class, so order-pay-only CSS is scoped on `form#order_review`. On the normal checkout page `#order_review` is a `div` and its table carries `.woocommerce-checkout-review-order-table`; on order-pay it is a `form` with a plain `table.shop_table`. `body.woocommerce-checkout form#order_review` therefore matches order-pay only — and `body.woocommerce-checkout:has(form#order_review)` extends that scoping to ancestors (used to widen the notices to match the two-column form).

**order-pay two-column desktop layout (≥1024px)**: the form's direct children are flat siblings with no wrapper — `table.shop_table`, `#wc-stripe-express-checkout-element`, the "— OPPURE —" separator, `#payment` — so the two columns are built by assigning grid columns to the children directly: summary table left, everything payment-related right. The non-obvious part is `grid-template-rows: auto auto 1fr` plus `grid-row: 1 / -1` on the table. Placing the table in row 1 only would make row 1 as tall as the table and open a gap between the wallet buttons and the separator in the other column; spanning it across rows without an elastic last row would distribute the excess height *between* those rows instead. With the last row at `1fr` all slack lands there. Rows stay at zero height when the Stripe wallet buttons are absent (Satispay-only order, or Apple Pay unavailable on the device), so the layout survives missing children. `#wc-stripe-express-checkout-element` also carries an inline `clear: both` that must be overridden with `clear: none !important` inside the grid.

**Terms checkbox label markup**: inside `label.checkbox` the `<input>`, the `<span>` with the text, and `<abbr class="required">*</abbr>` are **siblings**. Do not use `display: flex` on that label — it turns the three into separate flex items and strands the asterisk at the end of the row. Keep inline flow and align the enlarged checkbox with `vertical-align`.

**Stripe UPE card field — what is and isn't stylable from this repo**: the card field is an iframe, so its *contents* are cross-origin. The visible border/padding around the inputs is drawn by Stripe from an `appearance` object, and the gateway derives that object in the browser from the computed style of a hidden sampler input — `upeThemeInputSelector: "#wc-stripe-hidden-style-input"` in `build/upe-classic.js`, printed by `class-wc-stripe-upe-payment-gateway.php`. **To restyle the fields inside the iframe, style `#wc-stripe-hidden-style-input`** (it carries class `.input-text`, so today it inherits the theme's generic input look). Related selectors the same JS reads: `upeThemeLabelSelector`, `upeThemeTextSelectors`, and `backgroundSelectors` (`.woocommerce-PaymentBox`, `.payment_box`, `#payment`, `body`). On plugin **10.8.5** the old appearance transients (`wc_stripe_appearance`, `wc_stripe_blocks_appearance`) are **deprecated and unused** — `save_appearance_ajax()` / `clear_appearance_transients()` are `wc_deprecated_function` stubs, appearance is computed client-side on every load — so there is no cache to flush after changing that CSS.

**`.wc-stripe-upe-element` keeps `padding: 4px` on purpose.** Stripe wraps the iframe in `div.__PrivateStripeElement` with `margin: -4px 0 !important` and gives the iframe `margin: -4px; width: calc(100% + 8px)` — deliberate bleed so the focus ring isn't clipped. Without the 4px compensation on the container the card field visibly overhangs the rest of the column by 4px per side. Do not "fix" it by zeroing the iframe's margin/width instead: those two inline values lack `!important` so an `!important` rule *would* win, but the iframe also has `overflow: hidden !important`, so the focus outline gets clipped. The wrapper div itself cannot be overridden at all — its inline declarations are `!important`, and an important declaration in a `style` attribute beats an important rule from any stylesheet — but it draws nothing (`border-style: none`, `background: transparent`, `padding: 0`), so there is never a reason to try.

**WooCommerce notices are styled site-wide in `assets/css/wc-notices.css`** — appearance only (colors, borders, radius, spacing); per-page width/centering stays in the page's own stylesheet. The reason it is one global file keyed on the *type* class rather than on a wrapper: WooCommerce prints the same three notice types under at least three different wrappers — `.woocommerce-notices-wrapper > div.woocommerce-info` (cart, order-pay, my-account), `.woocommerce-NoticeGroup > div[role=alert] > ul.woocommerce-error > li` (checkout validation, incl. AJAX), and bare `ul.woocommerce-message`. Styling a wrapper leaves the other forms unstyled, which is exactly what happened when the rules lived under `.woocommerce-notices-wrapper` in `checkout.css` and checkout validation errors came out raw. Always target `.woocommerce-error` / `.woocommerce-info` / `.woocommerce-message` directly. Two mechanics: `padding`/`margin`/`list-style` need `!important` because `woocommerce.css` declares them `!important` too, and the `::before` icon must be hidden since WooCommerce positions it with a large asymmetric `padding-left`. Palette: red `#8e1414`, amber `#7a4100`, green `#14663d` on a 0.12 tint of the same hue, plus a 4px left accent bar in the full-strength colour. Those text values were **darkened for contrast** — the earlier `#a05800` / `#1a7a4a` sat around 4.3:1 on their own tinted background, under the 4.5:1 WCAG AA floor for normal text, and read as washed out. `assets/css/booking-status.css` still uses the original lighter trio at 0.78rem; if it is ever restyled, apply the same darkening. Do not add per-page notice color or font-size overrides — a `body.woocommerce-lost-password .woocommerce-message { font-size: 1.2rem !important }` rule in `style.css` was removed for this reason.

**Payment method list markup gotcha**: each `<li class="wc_payment_method">` (rendered via WC's standard `woocommerce_payment_methods_list` markup — gateway-agnostic) contains `<input type="radio">`, `<label>`, and — for gateways with inline fields like a credit card element — a `.payment_box` div, all as **siblings**, not nested inside the label. Styling the `<li>` as `display: flex; align-items: center; flex-wrap: wrap` with the radio `flex-shrink: 0`, the label `flex: 1 1 auto`, and `.payment_box { flex: 1 0 100%; }` (forcing it onto its own full-width row) is required — without `flex-wrap: wrap` + the `.payment_box` flex-basis, it renders inline after the label instead of below it. Content **inside** hosted payment-field iframes (Mollie Components, Stripe Elements/UPE) is cross-origin and not stylable via this repo's CSS at all — that requires changing the `styles`/appearance params the gateway's own JS passes when creating those components.

**Both environments now run Stripe + `woo-satispay`**: staging2 (`staging2.laviadelleterme.it`) used to be configured with the **Mollie** WooCommerce plugin while production ran Stripe, but staging2 has since been switched over, so both now use **Stripe** (UPE, `payment_method_stripe`, `.wc-stripe-upe-element`, plus the express-checkout wallet buttons `#wc-stripe-express-checkout-element*`) for cards and the separate **`woo-satispay`** plugin (`payment_method_satispay`, plain `<img>` with no class) for Satispay. All Mollie-specific code has been removed from the theme — don't re-add `.mollie-*` selectors or Mollie-conditional logic. `checkout/checkout.css` selectors are still deliberately kept as generic as possible (core WC classes over gateway-specific ones): the icon-sizing rule, for instance, is a plain `label img` match rather than a gateway class, so it covers `woo-satispay`'s unclassed logo. Older Mollie debug findings recorded above (e.g. the silently blocked payment retry) are kept as history only — they describe a plugin neither environment runs any more.

### Satispay: recovery when the customer cancels

`wp-content/themes/hello-theme-child-master/satispay/satispay.php` (required from `functions.php`) patches the third-party `woo-satispay` plugin **from the outside** — it hooks `woocommerce_api_wc_gateway_satispay` at **priority 5**, i.e. before the plugin's own `WC_Satispay::gateway_api()` handler (priority 10, registered in the gateway constructor), and `exit`s on the branches it handles while `return`ing on the branches the plugin should still handle. Never edit `wp-content/plugins/woo-satispay/` directly — it is a third-party plugin and any change there is lost on update; use this same hook-earlier-and-exit pattern for further Satispay patches.

**What was broken in the plugin** (`wc-satispay.php`):
- `process_payment()` sets the order to `wc-on-hold` the moment a payment is *initiated*, before any confirmation (this is also what makes WooCommerce send the admin "New order" email before anything is paid — pre-existing behavior, not introduced by this module).
- On return (`?wc-api=wc_gateway_satispay&action=redirect`), `Payment::get()` is called with **no try/catch**. `Request::request()` (`satispay-sdk/lib/Request.php`) throws a plain `\Exception` on any non-2xx response — including the very common `Unauthorized` — so an uncaught throw here is a PHP fatal: the blank page the customer saw on returning from the app.
- When the payment is not `ACCEPTED` the plugin cancels it Satispay-side and redirects to `WC()->cart->get_checkout_url()`, leaving the order in `on-hold`; the async `action=callback` with `CANCELED` (and the `finalize_orders()` cron) then sets `wc-cancelled`. **`cancelled` is not a payable status** — `needs_payment()` only accepts `pending`/`failed` — so the order became permanently unrecoverable and the customer could not switch to card.

**What the module does instead**: wraps every SDK call in try/catch; on any non-`ACCEPTED` return it cancels the payment Satispay-side (as the plugin did), restores the order to **`pending`** with an order note, clears `satispay_payment_id` from the WC session, adds a `wc_add_notice`, and redirects to `$order->get_checkout_payment_url()` (the order-pay page) rather than the checkout — the order already carries the booking item meta, so re-doing checkout is both unnecessary and lossy. The `callback` branch intercepts `CANCELED` for the same reason (otherwise the async callback would cancel the order while the customer is retrying) and replicates `payment_complete()` for `ACCEPTED` so behavior does not depend on hook ordering. `restore_payable_status()` never touches an order already in a paid status. `pending` was chosen over `failed` because it sends no email to customer or admin and matches the "in attesa di pagamento" wording used in `thankyou.php`.

**Debugging**: every branch logs to `wc_get_logger()` with source **`satispay-lvdt`** — WooCommerce → Status → Logs.

**Activation code is per-environment**: staging2 needs its **own** Satispay activation code. Credentials cloned into the staging DB from production fail with `Unauthorized, request id: …` on `Payment::create()`, surfaced as a checkout notice. Gotcha in the plugin's `process_admin_options()`: keys are regenerated **only when the activation code string changes** — re-saving the same code, or toggling only the Sandbox checkbox, does not re-authenticate. To force it: clear the Activation Code field → Save (this wipes `keyId`/`privateKey`/`publicKey`) → paste the new code → Save. Sandbox mode needs a sandbox account's code; production mode needs the production code with Sandbox off — the two cannot be crossed.

### Assets Pipeline (plugin-custom-skianet)

- Source CSS: `assets/css/booking-form.css`, `assets/css/booking-only-form.css`, `assets/css/pdp.css`
- Source JS: `assets/js/src/booking-form.js`, `assets/js/src/booking-only-form.js`
- Build output: `assets/js/dist/booking-form.min.js` + `booking-form.min.css` with sourcemaps
- Build tool: esbuild (`build.js`) + PostCSS with prefix-selector; `vanilla-calendar-pro` is bundled at build time
- `booking-only-form.js` is a hand-written IIFE served directly — it is **not** processed by esbuild

### Vendor in Production

The `vendor/` directory must be generated with `--no-dev` for production to exclude dev-only tools:

```bash
composer install --no-dev --optimize-autoloader
```

Dev-only packages (must NOT be deployed): `rector/`, `driftingly/`, `laravel/` (pint), `phpstan/`, `veewee/`.

### Deployment

GitHub Actions (`.github/workflows/deploy.yml`) deploys automatically to `staging2.laviadelleterme.it` on every push to `main`, via rsync over SSH (port 18765). Requires `SSH_KEY` secret in GitHub Actions.

**Manual uploads**: the user also sometimes uploads changed files directly to staging **or production** via FTP/SFTP (e.g. Cyberduck) instead of going through git push — production (`laviadelleterme.it`) has no CI deploy at all, so it is *only* ever updated this way. This means both environments' actual state can diverge from — or be ahead of — what's committed in this repo (e.g. `performance-optimization.php` existed on production before it was committed here). Don't assume "not committed" means "not live"; when debugging a live behavior discrepancy, ask whether the relevant files were deployed (git push or manual upload, and to which environment) rather than assuming git history reflects either environment.

### Disabled/Legacy Components

- Files prefixed with `__` are disabled legacy code.
- `plugin-custom-termeshop` — legacy plugin referenced in older docs; not present in this repo.
