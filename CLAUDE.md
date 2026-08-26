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

**Every call site now goes through that helper.** `Booking_Only_Handler::get_codes_from_order()` was the last
place with its own non-item-scoped copy of the query; on 2026-08-26 it was changed to iterate the order items and
delegate. The `controllo-codici` shortcodes never had the bug — they only count unassigned codes (`WHERE order_id = 0`)
and never touch order items.

`get_item_license_codes()` keeps a **per-request cache** keyed on `order_id:product_id` of the full (pre-slice) code
list, because inside `woocommerce_payment_complete` the same pair is queried by the confirmation email, the TermeGest
sync and the coupon email. It is always populated *after* `Booking_Code_Assignment` (priority 10), so it can never
freeze a list from before the codes were assigned — a future caller that read codes earlier in the same request would
break that assumption. The slicing itself lives in `slice_codes_for_item()`.

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
- `style.css` — **only genuinely global rules** (header/scroll classes, Elementor mini-cart, the site-wide
  `button.woocommerce-Button`, the Stripe `payment_box` reset for the account's add-payment-method page). Must stay at
  theme root (WP requirement) but is *not* the theme's catch-all: it used to be, and grew to 517 lines mixing five
  unrelated pages, with the `.woocommerce-lost-password` rules split across two blocks 280 lines apart. If a rule
  belongs to a page, it goes in that page's file. It still holds one block flagged as debt — the `#mupwp-*` form rules,
  whose originating plugin isn't in this repo and whose page is unidentified.
- `assets/css/`, `assets/js/` — global, non-feature-specific assets: `mobile-menu-style.css` (mobile + off-canvas menu),
  `script.js`, plus the stylesheets that deliberately do *not* live in a feature folder because the markup they style is
  printed on many pages, or by Elementor content / third-party plugins, so there is no reliable conditional tag to
  narrow the enqueue: `wc-notices.css` (all WooCommerce notices), `stripe-upe-appearance.css` (the hidden sampler input
  Stripe reads), `shop.css` (catalog, product page, cart) and `promo-pages.css` (PPWP password forms). A feature folder
  is the right home instead whenever the module owns both its PHP and its CSS — that is why `booking-status.css` moved
  out of here into `booking-status/`.
  `script.js` (password-protected promo pages, hamburger icon state, second header on scroll) is **vanilla JS with no
  jQuery dependency** — don't reintroduce one, it was removed so the enqueue stops pulling jQuery in for four lines
  used on a single page. Its scroll listener is registered `{ passive: true }` and writes classes only when the state
  actually changes; keep both properties if you touch it.
- `checkout/`, `order-pay/`, `thankyou/`, `booking-status/`, `satispay/`, `my-account/`, `controllo-codici/`, `promozioni-speciali/` — one folder per feature module, each bundling its own PHP + CSS/JS, required individually from `functions.php`. `checkout/checkout.css` holds only what the normal checkout and order-pay share (notices, payment methods, Stripe wallet + UPE card fields, terms, place-order button); everything scoped to `form#order_review` lives in `order-pay/order-pay.css`, enqueued right after it inside the same `is_checkout()` branch. `my-account/my-account.css` is enqueued on `is_account_page() || is_page('login-e-registrazione')` — registration is a standalone page here, not a WC endpoint (see the `login_url`/`register_url` filters), so `is_account_page()` alone would miss it. The lost-password page has its own `my-account/lost-password.css`, enqueued nested inside that branch on `is_wc_endpoint_url('lost-password')`; its selectors keep the `body.woocommerce-lost-password` prefix even though the sheet is already page-scoped, because dropping it would lower their specificity against Elementor's and WooCommerce's own rules. Its H1 ("Il mio account", hardcoded in the account area's shared Elementor template) is rewritten to "Password dimenticata" server-side by an `elementor/widget/render_content` filter in `my-account.php` — the same pattern as `thankyou.php`. It used to be faked in CSS by hiding the real H1 and printing the string in an `::after` at a fixed `5rem !important`: that skipped the widget's responsive typography and, with no width of its own, wrapped below the form.
- `woocommerce/pdf/Templates/` — PDF templates for invoices, packing slips, credit notes. These are forks of the plugin's stock templates and the plugin itself is not in this repo, so drift can only be checked against the copy installed on the server. Values that land in an attribute (`class`) go through `esc_attr`, and sku/weight/weight-unit/quantity through `esc_html`; item **name**, **meta**, prices and total labels are deliberately left raw — the plugin hands those over as ready-made HTML and escaping them breaks the documents.
- `woocommerce/emails/` — Custom WooCommerce email templates. Only `customer-completed-order.php` is live; four `__`-prefixed disabled copies were deleted in the 2026-08-25 cleanup (WooCommerce resolves overrides by exact filename, so they were never loaded).
- `promozioni-speciali/promozioni-speciali.css` — the PPWP unlock form on `/promozioni-speciali/`, enqueued on
  `is_page('promozioni-speciali')` with `promo-pages-style` as its dependency. `assets/css/promo-pages.css` stays the
  shared PPWP base loaded site-wide (PPWP can protect any page, so there is no conditional tag for it) and is also what
  styles the ADAVA page's identical `.ppw-pcp-*` markup — do **not** move those shared rules into this module, only
  keep this page's deviations here. The submit button reuses the `--lvdt-button-*` variables from `style.css`. The
  password input is deliberately shown in clear text and lowercased by `assets/js/script.js` (single shared password,
  not personal data), so don't style it as a `type=password` field.
- `ga4-elementor-compat.php` — GA4 event fallbacks for Elementor pages (see below)
- `elementor-element-cache.php` — opts three widgets out of Elementor's **Element Caching**. With that feature on, Elementor stores each widget's rendered HTML in the document's post meta and reprints it without calling `Widget_Base::render_content()`, so every `elementor/widget/render_content` filter stops running. The theme has two such filters (`my-account/my-account.php`, `thankyou/thankyou.php`) and in both the text depends on the request — the WooCommerce endpoint, the order status — while the cache is per element and covers the whole document: the first render wins and freezes. Confirmed on staging2 — after opening `/my-account/lost-password/` the account dashboard showed "Password dimenticata" too, and on the thank-you page the same mechanism can show "Ordine ricevuto!" on an unpaid order. The fix uses Elementor's own escape hatch (`Element_Base::should_render_shortcode()`): the `elementor/element/is_dynamic_content` filter marks an element as dynamic, so instead of being baked into the cached HTML it is printed as an `[elementor-element data="…"]` placeholder and rendered on every request. **The match must depend only on the widget's own data, never on request context** — the filter runs when the cache is built, not when it is served; it keys on the `title-my-account` CSS class, the heading texts "Il mio account"/"Pagamento" and the text-editor containing "Completa il pagamento". With Element Caching off the file is a no-op (`should_render_shortcode()` returns early on its own default-false filter). After changing it, clear the existing cache (Elementor → Tools → Regenerate files & data) or the frozen HTML stays on screen.
- `performance-optimization.php` — disables Gutenberg, emoji scripts, oEmbed, XML-RPC, comments (all post types, including products — no WC product reviews in use), throttles Heartbeat, limits post revisions to 3, strips self-pings. Required from `functions.php`; do **not** re-add a `?ver` query-string-stripping filter here — it was removed because it silently defeated the theme's asset cache-busting (see below).

**Why this structure**: the theme has no single template-heavy surface to override, just a handful of independent WooCommerce page customizations — grouping each by feature (rather than the classic WP `inc/`-by-type convention) makes it obvious what touches what, and mirrors `plugin-custom-skianet`'s one-class-per-responsibility pattern.

**`woocommerce/` is reserved — do not add feature folders there.** WooCommerce resolves theme template overrides by exact path (e.g. `woocommerce/checkout/thankyou.php` is the real order-received template, `woocommerce/checkout/form-pay.php` the real order-pay template, `woocommerce/myaccount/*.php` the real My Account templates — note: no hyphen). This theme's own `checkout/`, `order-pay/`, `my-account/` folders live at theme root specifically to avoid colliding with those reserved paths; a file placed at, say, `woocommerce/checkout/thankyou.php` would silently replace WooCommerce's real template with whatever this repo put there (these modules contain only hooked PHP, not full markup), breaking the page. Only actual template overrides belong under `woocommerce/`.

**Global function naming**: loose (non-class) functions in the theme are prefixed `laviadelleterme_` to avoid collisions in the global namespace (WordPress/plugins share one global function table).

**WooCommerce guard in `functions.php`**: everything after the site-wide assets (`style.css`, the mobile menu, `script.js`, the registered `controllo-codici.css`) is behind a single `if ( ! class_exists( 'WooCommerce' ) ) return;` — `is_wc_endpoint_url()` and `is_checkout()` are WooCommerce functions and calling them with the plugin off is a fatal error, which matters here because plugins can be briefly inactive during manual FTP deploys. For the same reason the `hello-elementor-theme-style` dependency is only declared when that handle is actually registered: turning off "Theme Style" in Hello Elementor's settings would otherwise make WordPress skip the child stylesheet entirely, in silence.

**Asset versioning**: `functions.php` enqueues every theme asset with a single version — `wp_get_theme()->get( 'Version' )`, i.e. the `Version:` header in `style.css`. Bump that header whenever a theme CSS/JS file changes, otherwise browsers keep serving the cached copy after a deploy (relevant since deploys here are sometimes manual FTP, not just `git push` — see "Manual uploads" below).

**`controllo-codici/`**: two shortcodes (`controllo_codici_prezzo_pieno`, `controllo_codici_promo`) that print remaining unassigned license-code counts per product. Both are thin wrappers around `laviadelleterme_render_controllo_codici( $prodotti )` in `controllo-codici.php` — only the product map differs, so keep the shared renderer as the single place that touches the DB or the markup. It is gated by `laviadelleterme_puo_vedere_controllo_codici()` (`manage_woocommerce`); everyone else gets a "Contenuto riservato allo staff." line rather than silence, so a wrong capability is visible instead of looking like an empty page. One query (`WHERE order_id = 0 AND product_id IN (…) GROUP BY product_id`) covers the whole list — it used to be one query per product, 33 in total. Products with no rows are simply absent from the result and render as 0. `controllo-codici.css` is *registered* in `functions.php` and enqueued by the renderer itself, so it costs nothing on the rest of the site; it therefore prints in the footer, which is fine for an internal page.

**`my-account/my-account.php`**: custom login/registration URLs and post-login/registration redirect logic. Redirect targets (explicit `redirect`/`redirect_to` params and the `Referer` fallback) are validated via `laviadelleterme_is_local_url()`, which compares hosts with `wp_parse_url(...,  PHP_URL_HOST)` — do not go back to a plain `strpos($url, home_url())` substring check, it's bypassable (`https://evil.tld/?x=` + `home_url()` passes it) and was an open-redirect vector here until fixed.

The destination logic is shared: `laviadelleterme_destinazione_dopo_accesso()` is the single implementation, and the `woocommerce_login_redirect` / `woocommerce_registration_redirect` callbacks are thin wrappers around it (they used to be two line-for-line identical copies). The logged-out gate on the account pages uses **`is_account_page()`**, not a `strpos` on `REQUEST_URI` — the string check fired on any URL that merely contained `/my-account/` and ignored whichever page WooCommerce is actually configured with. It exits early on `lost-password`, guards against a redirect loop if the account page were ever set to the login page itself, uses `wp_safe_redirect()`, and carries the requested URL over in `redirect_to` (rebuilt on `home_url()` so a manipulated `REQUEST_URI` cannot move the destination off-host) so that opening e.g. `/my-account/view-order/123` while logged out lands back there after login instead of on the dashboard.

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

**`laviadelleterme_order_is_confirmed( $order )`** (defined in `booking-status/booking-status.php`, shared with `thankyou.php` and used on order-pay too — it was called `laviadelleterme_thankyou_order_is_confirmed` while it lived in `thankyou.php`) is the single source of truth: `true` only for order status `processing`, `completed`, `booked`, `not-booked`. `booked`/`not-booked` are reached only by passing through `completed` first (see `Booking_Order_Status`), so plain `$order->is_paid()` is not enough — those two statuses aren't in WC's default paid-statuses list.

Driven by that helper, `thankyou.php` conditionally:
- Rewrites the Elementor H1 to "Ordine ricevuto!" (confirmed) vs "Ordine in attesa di pagamento" (not confirmed). The `elementor/widget/render_content` filter that does this replaces only the text node that is *exactly* "Pagamento" (a plain `str_replace` also hit headings like "Metodo di Pagamento"), and it runs for guest orders too — it used to be gated on `is_user_logged_in()`, which left guest orders showing the raw hardcoded heading. The same filter also strips the Elementor text-editor subtitle containing "Completa il pagamento": doing it server-side replaced a `display:none` CSS rule plus a JS text match that made it flash on screen before disappearing.
- Overrides `woocommerce_thankyou_order_received_text` (the generic WC notice) when not confirmed.
- Shows a per-item badge: green "Prenotazione confermata", amber "Usa i codici per completare la prenotazione" (non-booking item, confirmed order), or red "In attesa di pagamento" (order not confirmed, checked first — takes priority over the `_booking_id` check). It hooks `woocommerce_order_item_meta_end`, which **also fires in the order-pay summary table**, so the badge appears on two pages and neither `thankyou/` nor `order-pay/` owns it: the hook, the gate and the CSS all live in **`booking-status/`** (`booking-status.php` + `booking-status.css`, enqueued for both pages). The badge markup uses the `.booking-status*` classes — they were prefixed `.thankyou-booking-status*` until the module was extracted. That hook is **not** a page hook, though: WooCommerce also fires it in `templates/emails/email-order-items.php` (so inside customer emails *and* the admin "New order" email, which goes out while the order is still unpaid — every line would read "In attesa di pagamento") and in `templates/order/order-details-item.php` (My Account → View order, where the badge CSS is not even loaded). `laviadelleterme_show_booking_status_badge()` is the gate: it requires `is_wc_endpoint_url('order-received') || is_checkout()` — the same two conditions that enqueue the CSS — and excludes `is_admin()`, `wp_doing_ajax()` (the `?wc-ajax=checkout` POST, where `is_checkout()` is true but the only output is the transactional emails) and the email item table, tracked by a flag set on `woocommerce_email_before/after_order_table`. That last flag is needed because with Stripe the `payment_complete` — and therefore the emails — can happen *during* the order-received request itself.
- Outputs a "Completa il pagamento" CTA box (`woocommerce_thankyou` hook) linking to `$order->get_checkout_payment_url()`, gated on `$order->needs_payment()` so it doesn't show for e.g. on-hold/BACS orders that don't need customer action.

**Mollie return-redirect vs. webhook race (client-side polling fix)**: even for a successful card payment, the customer's browser can land on the thank-you page *before* the order is actually marked paid. Mollie's plugin (`MolliePaymentGatewayHandler::getReturnRedirectUrlForOrder()`) queries Mollie's API on the browser's return trip only to decide *where* to redirect (order-received vs. back to order-pay on failure) — it does **not** update the WooCommerce order status there. The order status is only ever flipped by the separate, asynchronous Mollie webhook (`MollieOrderService::onWebhookAction()`, a different HTTP request sent by Mollie's servers, not the browser). If that webhook lands a moment after the browser's redirect, `thankyou.php` renders "in attesa di pagamento" for an order that is, in reality, already paid — confirmed via a real order's debug.log where `payment_complete`/status-transition logging all happened after the point the customer would have already been redirected. Fix: `thankyou.php` prints a small inline-JS polling snippet (`woocommerce_thankyou`, priority 20) **only** inside the same `! laviadelleterme_order_is_confirmed( $order ) && $order->needs_payment()` gate used by the CTA box above — so confirmed orders and orders that genuinely don't need action (e.g. BACS) get zero extra script/requests. It calls a new `wp_ajax(_nopriv)_laviadelleterme_check_order_confirmed` endpoint (validates `$order->key_is_valid()`, same check the Mollie webhook itself uses, to stop order-status enumeration) every 2.5s for up to ~30s (12 attempts), and does a single `location.reload()` the moment the order becomes confirmed. After ~30s with no change (e.g. a real, slow bank-transfer order) it stops polling and leaves the real "awaiting payment" state on screen — it does not loop forever.

`order-pay/order-pay.php` handles the retry-payment page itself. It detects the one validation failure that WooCommerce reports without redirecting — the terms-and-conditions checkbox left unticked (`woocommerce_pay` POSTed with an empty `terms`, so `$_POST` is still readable during the re-render) — and on that condition adds a `order-pay-terms-error` body class (styled red in `checkout/checkout.css`) plus a small inline `wp_footer` script that scrolls the row into view and focuses the checkbox (`focus({preventScroll:true})`, otherwise the browser's own jump cancels the smooth scroll); the class is removed on `change` once the box is ticked.

**Removed (do not re-add):** this file used to also hook `template_redirect` at priority 20 to print a generic "payment may still be pending, wait 10 minutes" notice on any `woocommerce_pay` POST that reached that priority. It existed for Mollie, which blocked creating a new payment while a previous one was still "active and non-cancellable" for the order and did so **silently** (no WC error, no JS error, plain 200). Neither environment runs Mollie any more, and the notice also fired on ordinary terms-validation failures, showing a second misleading message next to the real error.

**Checkout styling**: `checkout/checkout.css` (enqueued from `functions.php` on `is_checkout()`) styles generic WooCommerce checkout markup — notice **positioning**, the `#payment` payment-methods list (Stripe card/UPE and Satispay), the terms checkbox, the place-order button, and the whole order-pay page layout. `is_checkout()` covers both the normal `/checkout/` page and the `/checkout/order-pay/…` retry page (same WC template). Note: `is_checkout_pay_page()` was tried first for a narrower, order-pay-only enqueue but the `<link>` tag never appeared in the rendered page on this Elementor setup — root cause not fully diagnosed (suspect the enqueue callback ran against a build of `functions.php` on the server that predated the change, since deploys here can be manual FTP uploads — see "Manual uploads" below); switching to the broader, well-established `is_checkout()` conditional resolved it.

**Scoping order-pay rules without a body class**: WordPress/WooCommerce give the order-pay page no distinguishing body class, so order-pay-only CSS is scoped on `form#order_review`. On the normal checkout page `#order_review` is a `div` and its table carries `.woocommerce-checkout-review-order-table`; on order-pay it is a `form` with a plain `table.shop_table`. `body.woocommerce-checkout form#order_review` therefore matches order-pay only — and `body.woocommerce-checkout:has(form#order_review)` extends that scoping to ancestors (used to widen the notices to match the two-column form).

**order-pay two-column desktop layout (≥1024px)**: the form's direct children are flat siblings with no wrapper — `table.shop_table`, `#wc-stripe-express-checkout-element`, the "— OPPURE —" separator, `#payment` — so the two columns are built by assigning grid columns to the children directly: summary table left, everything payment-related right. The non-obvious part is `grid-template-rows: auto auto 1fr` plus `grid-row: 1 / -1` on the table. Placing the table in row 1 only would make row 1 as tall as the table and open a gap between the wallet buttons and the separator in the other column; spanning it across rows without an elastic last row would distribute the excess height *between* those rows instead. With the last row at `1fr` all slack lands there. Rows stay at zero height when the Stripe wallet buttons are absent (Satispay-only order, or Apple Pay unavailable on the device), so the layout survives missing children. `#wc-stripe-express-checkout-element` also carries an inline `clear: both` that must be overridden with `clear: none !important` inside the grid.

**Terms checkbox label markup**: inside `label.checkbox` the `<input>`, the `<span>` with the text, and `<abbr class="required">*</abbr>` are **siblings**. Do not use `display: flex` on that label — it turns the three into separate flex items and strands the asterisk at the end of the row. Keep inline flow and align the enlarged checkbox with `vertical-align`.

**Stripe UPE card field — what is and isn't stylable from this repo**: the card field is an iframe, so its *contents* are cross-origin. The visible border/padding around the inputs is drawn by Stripe from an `appearance` object, and the gateway derives that object in the browser from the computed style of a hidden sampler input — `upeThemeInputSelector: "#wc-stripe-hidden-style-input"` in `build/upe-classic.js`, printed by `class-wc-stripe-upe-payment-gateway.php`. **To restyle the fields inside the iframe, style `#wc-stripe-hidden-style-input`** — done in `assets/css/stripe-upe-appearance.css` (enqueued site-wide alongside `wc-notices.css`, because the sampler input is printed on checkout, order-pay *and* the account's add-payment-method page). The properties the JS copies into `.Input` are `color`, every `padding*`, the font set, the full border set (width/color/style + per-corner radius), `outline`, `backgroundColor` and `boxShadow` — anything else set on that input is ignored. Use hex, not `rgba()`: the value is replayed inside the iframe where the backdrop is Stripe's, not the `.payment_box` tint, so a translucent colour lands differently than it looks here. Custom properties on that input are fine — the gateway reads `getComputedStyle`, which resolves them, so `border-radius: var(--lvdt-radius-sm)` reaches the iframe as `3px`. Related selectors the same JS reads: `upeThemeLabelSelector`, `upeThemeTextSelectors`, and `backgroundSelectors` (`.woocommerce-PaymentBox`, `.payment_box`, `#payment`, `body`). On plugin **10.8.5** the old appearance transients (`wc_stripe_appearance`, `wc_stripe_blocks_appearance`) are **deprecated and unused** — `save_appearance_ajax()` / `clear_appearance_transients()` are `wc_deprecated_function` stubs, appearance is computed client-side on every load — so there is no cache to flush after changing that CSS.

**`.wc-stripe-upe-element` keeps `padding: 4px` on purpose.** Stripe wraps the iframe in `div.__PrivateStripeElement` with `margin: -4px 0 !important` and gives the iframe `margin: -4px; width: calc(100% + 8px)` — deliberate bleed so the focus ring isn't clipped. Without the 4px compensation on the container the card field visibly overhangs the rest of the column by 4px per side. Do not "fix" it by zeroing the iframe's margin/width instead: those two inline values lack `!important` so an `!important` rule *would* win, but the iframe also has `overflow: hidden !important`, so the focus outline gets clipped. The wrapper div itself cannot be overridden at all — its inline declarations are `!important`, and an important declaration in a `style` attribute beats an important rule from any stylesheet — but it draws nothing (`border-style: none`, `background: transparent`, `padding: 0`), so there is never a reason to try.

**`--lvdt-button-*` must be declared on `body`, not only `:root`.** They hold the shared button look and resolve
Elementor's kit globals (`--e-global-color-*`), which Elementor prints in the `.elementor-kit-NNN` rule — and that class
sits on `<body>`, not on `html`. A `var()` inside a custom property is resolved in the scope where the custom property
is *declared*, so on `:root` the kit global is undefined and `--lvdt-button-bg` becomes guaranteed-invalid: `background`
falls back to transparent and `border-color` to `currentColor`, while `--lvdt-button-color` survives via its own `#fff`
fallback — a white button on nothing. That was the live symptom on the "added to cart" notice. The block is therefore
declared on `:root, body` and both kit colors carry an explicit hex fallback (kit global IDs are not stable across
installs). The notice button takes only *form* from these variables (family, weight, radius, uppercase), not colour, and at a
reduced scale — `0.9rem` / `0.55rem 1.25rem 0.65rem` instead of the full-page `1.3rem` / `0.9rem 2rem 1.1rem` — because
a notice is a compact strip whose own text sits at `0.95rem`. Its colours come from the notice's own type instead: see
the `--lvdt-notice-color` note under WooCommerce notices below.

**Corner radii come from the `--lvdt-radius-*` scale in `style.css` — never write a literal.** Three steps, one per
visual weight: `--lvdt-radius-sm` (3px) for controls — buttons, text inputs, the nav promo button, and the Stripe
sampler input; `--lvdt-radius-md` (10px) for messages and rows — WooCommerce notices, table rows, list items, the terms
error box; `--lvdt-radius-lg` (12px) for containers — the order-pay `shop_table`, the order/customer detail cards, the
payment-method items, the thank-you CTA box. Before this there were seven different literals (3, 5, 6, 8, 10, 12, 14px)
scattered across nine files with no meaning attached to the differences. `--lvdt-button-radius` is gone: the button is
simply the `sm` step. Deliberately **outside** the scale are shapes rather than sizes, left as literals and commented as
such: the `20px` pills (`.booking-status`, `.product-quantity`, `.wc-item-meta li`, `.codici-count`) and the `50%`
circle of `.booking-status__dot`.

**The shared button rules are a default, not the last word — never `!important` them.** Everything the site's own
Elementor widgets are styled with must be able to win, because the user styles the checkout button from the Elementor
editor and that emits per-element rules like
`.elementor-960 .elementor-element.elementor-element-58efafa #payment #place_order`. So the rules that apply
`--lvdt-button-*` carry no `!important` and only as much specificity as it takes to beat `woocommerce.css`:
`body.woocommerce-checkout #place_order` (1,1,1) — one ID outranks the three classes of
`.woocommerce button.button.alt` — and `body .woocommerce-message a.button` (0,2,2), one step above
`.woocommerce a.button`. Two `color: inherit !important` normalizations that used to force the buttons to answer with
`!important` of their own now exclude them by selector instead: `.woocommerce-order *:not(.booking-status)` also
excludes `:not(.thankyou-complete-payment__button)`, and the notice link rule is `a:not(.button)`. The one deliberate
exception is the global `button.woocommerce-Button` block in `style.css`, which keeps `!important` because the rule it
must beat has three classes against its one, and buying that back with `.button` + a `.woocommerce` ancestor would make
it fail silently if a WooCommerce template ever changes those classes; no Elementor rule competes there today. Note
that Elementor overriding only `background-color` leaves our `border-color` on the old value — set the border in the
editor too.

**WooCommerce notices are styled site-wide in `assets/css/wc-notices.css`** — appearance only (colors, borders, radius, spacing); per-page width/centering stays in the page's own stylesheet. The reason it is one global file keyed on the *type* class rather than on a wrapper: WooCommerce prints the same three notice types under at least three different wrappers — `.woocommerce-notices-wrapper > div.woocommerce-info` (cart, order-pay, my-account), `.woocommerce-NoticeGroup > div[role=alert] > ul.woocommerce-error > li` (checkout validation, incl. AJAX), and bare `ul.woocommerce-message`. Styling a wrapper leaves the other forms unstyled, which is exactly what happened when the rules lived under `.woocommerce-notices-wrapper` in `checkout.css` and checkout validation errors came out raw. Always target `.woocommerce-error` / `.woocommerce-info` / `.woocommerce-message` directly — but with the type class **written twice and prefixed with `body`** (`body .woocommerce-info.woocommerce-info`). Elementor Pro's Checkout widget ships `.elementor-widget-woocommerce-checkout-page .woocommerce-info { background-color: transparent; border-top-color: transparent; … }`, two classes (0,2,0) against the one class (0,1,0) of a plain type selector, so on the checkout page the notices lost their tint and top border. The repeated class buys (0,2,0) and `body` the deciding element, (0,2,1), with no dependency on a wrapper or a body class — the notices appear in too many contexts for either to be reliable. That level still sits below Elementor's per-element rules (at least (0,4,0)), so anything set by hand in the editor keeps winning, which is why this is done with specificity and not `!important`. Two mechanics: `padding`/`margin`/`list-style` need `!important` because `woocommerce.css` declares them `!important` too, and the `::before` icon must be hidden since WooCommerce positions it with a large asymmetric `padding-left`. Palette: text red `#8e1414`, amber `#7a4100`, green `#14663d` on a **0.18** tint of the same hue, with the border in that hue at **full strength** and a 4px left accent bar (width only — there is no separate `border-left-color` any more). The tint was 0.10-0.12 and the border a 0.35 alpha of the hue: that border measured ~1.4:1 against white, i.e. invisible, which is what made the notices look like they dissolved into the page. Full strength puts the three borders at 3.1:1-5.7:1, over the 3:1 WCAG floor for non-text elements, and 0.18 is the most tint the text can take before contrast suffers — it still leaves the three between 5.7:1 and 6.9:1. Those text values were **darkened for contrast** — the earlier `#a05800` / `#1a7a4a` sat around 4.3:1 on their own tinted background, under the 4.5:1 WCAG AA floor for normal text, and read as washed out. `booking-status/booking-status.css` now uses the same darkened trio (it kept the lighter one until the 2026-08-25 audit); its dot colours stay at full strength since they are not text. Its **borders do not** — they are still a 0.25-0.3 alpha of the hue over a 0.08-0.1 tint, i.e. the same invisible-border problem the notices had before 2026-08-26, left alone on purpose: the badges are small pills where a full-strength perimeter would read as much heavier than it does on a full-width notice, so they need values tuned to their own size rather than a copy of the notice palette. **The button inside a notice is outlined in the notice's own colour**, not the site blue: transparent background (the
notice tint shows through), border and text in that type's dark text colour, and on hover the colour fills the
background with white text. The blue took over the strip — a colour foreign to the message, on a tint of another hue,
outweighing the text that should be read first. Each type block publishes its colour as `--lvdt-notice-color`, a custom
property that inherits down to the button, so there is one button rule instead of three; it falls back to
`--lvdt-button-bg` for a notice with no recognised type class. Contrast: at rest the text is the notice's own text
colour (already ≥4.5:1 on its tint) and the border is full-strength (≥3:1); on hover white sits between 6.9:1 and 8.7:1
on the three colours. Do not add per-page notice color or font-size overrides — a `body.woocommerce-lost-password .woocommerce-message { font-size: 1.2rem !important }` rule in `style.css` was removed for this reason.

**Payment method list markup gotcha**: each `<li class="wc_payment_method">` (rendered via WC's standard `woocommerce_payment_methods_list` markup — gateway-agnostic) contains `<input type="radio">`, `<label>`, and — for gateways with inline fields like a credit card element — a `.payment_box` div, all as **siblings**, not nested inside the label. Styling the `<li>` as `display: flex; align-items: center; flex-wrap: wrap` with the radio `flex-shrink: 0`, the label `flex: 1 1 auto`, and `.payment_box { flex: 1 0 100%; }` (forcing it onto its own full-width row) is required — without `flex-wrap: wrap` + the `.payment_box` flex-basis, it renders inline after the label instead of below it. Content **inside** hosted payment-field iframes (Mollie Components, Stripe Elements/UPE) is cross-origin and not stylable via this repo's CSS at all — that requires changing the `styles`/appearance params the gateway's own JS passes when creating those components.

**Both environments now run Stripe + `woo-satispay`**: staging2 (`staging2.laviadelleterme.it`) used to be configured with the **Mollie** WooCommerce plugin while production ran Stripe, but staging2 has since been switched over, so both now use **Stripe** (UPE, `payment_method_stripe`, `.wc-stripe-upe-element`, plus the express-checkout wallet buttons `#wc-stripe-express-checkout-element*`) for cards and the separate **`woo-satispay`** plugin (`payment_method_satispay`, plain `<img>` with no class) for Satispay. All Mollie-specific code has been removed from the theme — don't re-add `.mollie-*` selectors or Mollie-conditional logic. `checkout/checkout.css` selectors are still deliberately kept as generic as possible (core WC classes over gateway-specific ones): the icon-sizing rule, for instance, is a plain `label img` match rather than a gateway class, so it covers `woo-satispay`'s unclassed logo. Older Mollie debug findings recorded above (e.g. the silently blocked payment retry) are kept as history only — they describe a plugin neither environment runs any more.

### Satispay: recovery when the customer cancels

`wp-content/themes/hello-theme-child-master/satispay/satispay.php` (required from `functions.php`) patches the third-party `woo-satispay` plugin **from the outside** — it hooks `woocommerce_api_wc_gateway_satispay` at **priority 5**, i.e. before the plugin's own `WC_Satispay::gateway_api()` handler (priority 10, registered in the gateway constructor), and `exit`s on the branches it handles while `return`ing on the branches the plugin should still handle. Never edit `wp-content/plugins/woo-satispay/` directly — it is a third-party plugin and any change there is lost on update; use this same hook-earlier-and-exit pattern for further Satispay patches.

**What was broken in the plugin** (`wc-satispay.php`):
- `process_payment()` sets the order to `wc-on-hold` the moment a payment is *initiated*, before any confirmation (this is also what makes WooCommerce send the admin "New order" email before anything is paid — pre-existing behavior, not introduced by this module).
- On return (`?wc-api=wc_gateway_satispay&action=redirect`), `Payment::get()` is called with **no try/catch**. `Request::request()` (`satispay-sdk/lib/Request.php`) throws a plain `\Exception` on any non-2xx response — including the very common `Unauthorized` — so an uncaught throw here is a PHP fatal: the blank page the customer saw on returning from the app.
- When the payment is not `ACCEPTED` the plugin cancels it Satispay-side and redirects to `WC()->cart->get_checkout_url()`, leaving the order in `on-hold`; the async `action=callback` with `CANCELED` (and the `finalize_orders()` cron) then sets `wc-cancelled`. **`cancelled` is not a payable status** — `needs_payment()` only accepts `pending`/`failed` — so the order became permanently unrecoverable and the customer could not switch to card.

**What the module does instead**: wraps every SDK call in try/catch; on any non-`ACCEPTED` return it cancels the payment Satispay-side (as the plugin did), restores the order to **`pending`** with an order note, clears `satispay_payment_id` from the WC session, adds a `wc_add_notice`, and redirects to `$order->get_checkout_payment_url()` (the order-pay page) rather than the checkout — the order already carries the booking item meta, so re-doing checkout is both unnecessary and lossy. The `callback` branch intercepts `CANCELED` for the same reason (otherwise the async callback would cancel the order while the customer is retrying) and replicates `payment_complete()` for `ACCEPTED` so behavior does not depend on hook ordering. `restore_payable_status()` never touches an order already paid — and "paid" here means **`$order->get_date_paid()`**, not `wc_get_is_paid_statuses()`: that list is only `('processing','completed')` and does not include the custom `booked` / `not-booked`, so with the status check alone a late `CANCELED` callback from the abandoned attempt would push an order back to `pending` after it had been paid by card, synced to TermeGest and given its license codes. It also clears the order's `transaction_id`, otherwise the plugin's own `wc_satispay_finalize_orders_event` cron (every 4h, gated by the "Finalize unhandled payments" setting) picks up exactly the `wc-pending` orders created 1–4h ago whose Satispay payment is `CANCELED` and sets them back to `wc-cancelled` — re-creating the unrecoverable state this module exists to prevent. `pending` was chosen over `failed` because it sends no email to customer or admin and matches the "in attesa di pagamento" wording used in `thankyou.php`.

**Debugging**: every branch logs to `wc_get_logger()` with source **`satispay-lvdt`** — WooCommerce → Status → Logs.

**Activation code is per-environment**: staging2 needs its **own** Satispay activation code. Credentials cloned into the staging DB from production fail with `Unauthorized, request id: …` on `Payment::create()`, surfaced as a checkout notice. Gotcha in the plugin's `process_admin_options()`: keys are regenerated **only when the activation code string changes** — re-saving the same code, or toggling only the Sandbox checkbox, does not re-authenticate. To force it: clear the Activation Code field → Save (this wipes `keyId`/`privateKey`/`publicKey`) → paste the new code → Save. Sandbox mode needs a sandbox account's code; production mode needs the production code with Sandbox off — the two cannot be crossed.

### Assets Pipeline (plugin-custom-skianet)

- Source CSS: `assets/css/booking-form.css`, `assets/css/booking-only-form.css`, `assets/css/pdp.css`
- Source JS: `assets/js/src/booking-form.js`, `assets/js/src/booking-only-form.js`
- Build output: `assets/js/dist/booking-form.min.js` + `booking-form.min.css` with sourcemaps
- Build tool: esbuild (`build.js`) + PostCSS with prefix-selector; `vanilla-calendar-pro` is bundled at build time
- `booking-only-form.js` is a hand-written IIFE served directly — it is **not** processed by esbuild

**Enqueue gates**: `Booking_Handler::enqueue_assets()` loads `pdp.css` only on `is_product()` (it styles
`.product.customDataLoaded`, a class set by `woocommerce-variation-preselect.js`) and the form's CSS/JS only where
`page_has_booking_form()` says the shortcode is present. That check reads `post_content` **and** the `_elementor_data`
meta — pages here are built with Elementor, which keeps the content in a JSON meta, so `has_shortcode($post->post_content, …)`
alone (what `Booking_Only_Handler` does) would miss it. It would also miss a form placed in an Elementor *template*
rather than in page content. All enqueues use `PLUGIN_SKIANET_VERSION`, never a hardcoded version string.

**No global `session_start()`**: it used to run on `init` for every request, which put a `Set-Cookie: PHPSESSID` on
every response — nothing cacheable, concurrent requests serialized on the session file. Each of the five places that
touch `$_SESSION` starts the session itself, and all of them run before any output.

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

- Files prefixed with `__` are disabled legacy code. None are left in the theme (the last four, in `woocommerce/emails/`, were deleted on 2026-08-25); the convention still applies elsewhere in the repo.
- `plugin-custom-termeshop` — legacy plugin referenced in older docs; not present in this repo.
