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
- `assets/css/`, `assets/js/` — global, non-feature-specific assets (currently `mobile-menu-style.css`, `script.js`)
- `checkout/`, `order-pay/`, `thankyou/`, `my-account/`, `controllo-codici/` — one folder per feature module, each bundling its own PHP + CSS/JS, required individually from `functions.php`
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
- Shows a per-item badge: green "Prenotazione confermata", amber "Usa i codici per completare la prenotazione" (non-booking item, confirmed order), or red "In attesa di pagamento" (order not confirmed, checked first — takes priority over the `_booking_id` check).
- Adds a body class `thankyou-awaiting-payment` (via `body_class` filter) when not confirmed — used in `thankyou.css` to hide the Mollie gateway's own (English, unstyled) "payment status" instructions box only in that case.
- Outputs a "Completa il pagamento" CTA box (`woocommerce_thankyou` hook) linking to `$order->get_checkout_payment_url()`, gated on `$order->needs_payment()` so it doesn't show for e.g. on-hold/BACS orders that don't need customer action.

`order-pay/order-pay.php` handles the retry-payment page itself: hooks `template_redirect` at **priority 20**, i.e. after `WC_Form_Handler::pay_action()` (priority 10) has run. If the payment attempt succeeds, `pay_action()` already redirected + `exit`ed before priority 20 runs. If code still reaches priority 20, the POST (`woocommerce_pay=1`) didn't lead anywhere, so a `wc_add_notice(..., 'notice')` explains that a previous payment attempt may still be pending. This was written after discovering (via the Mollie plugin's own debug log — enable it in WooCommerce → Settings → Payments → Mollie) that Mollie blocks creating a new payment for an order while a previous one is still "active and non-cancellable" for that order, and does so **silently** (no WC error notice, no JS error, plain 200 response) — the fix is intentionally gateway-agnostic rather than parsing Mollie's internals.

**Checkout styling**: `checkout/checkout.css` (required/enqueued from `functions.php` on `is_checkout()`) styles generic WooCommerce checkout markup — the notices wrapper, the `#payment` payment-methods list (Mollie gateways: card, Satispay, Bancomat Pay), and the terms/place-order area. It's a separate file from `order-pay.php`/`order-pay.css` on purpose: this markup and its styling apply to **both** the normal `/checkout/` page and the `/checkout/order-pay/…` retry page (same WC template), whereas `order-pay/` holds logic/styling specific to the retry-payment scenario only. Note: `is_checkout_pay_page()` was tried first for a narrower, order-pay-only enqueue but the `<link>` tag never appeared in the rendered page on this Elementor setup — root cause not fully diagnosed (suspect the enqueue callback ran against a build of `functions.php` on the server that predated the change, since deploys here can be manual FTP uploads — see "Manual uploads" below); switching to the broader, well-established `is_checkout()` conditional resolved it.

**Payment method list markup gotcha**: each `<li class="wc_payment_method">` (rendered via WC's standard `woocommerce_payment_methods_list` markup — gateway-agnostic) contains `<input type="radio">`, `<label>`, and — for gateways with inline fields like a credit card element — a `.payment_box` div, all as **siblings**, not nested inside the label. Styling the `<li>` as `display: flex; align-items: center; flex-wrap: wrap` with the radio `flex-shrink: 0`, the label `flex: 1 1 auto`, and `.payment_box { flex: 1 0 100%; }` (forcing it onto its own full-width row) is required — without `flex-wrap: wrap` + the `.payment_box` flex-basis, it renders inline after the label instead of below it. Content **inside** hosted payment-field iframes (Mollie Components, Stripe Elements/UPE) is cross-origin and not stylable via this repo's CSS at all — that requires changing the `styles`/appearance params the gateway's own JS passes when creating those components.

**Staging vs. production use different payment gateways**: staging2 (`staging2.laviadelleterme.it`) is configured with the **Mollie** WooCommerce plugin for card / Satispay / Bancomat Pay (`mollie_wc_gateway_*` classes, `.mollie-instructions`, `.mollie-component*` markup — see the Mollie-specific notes above). Production (`laviadelleterme.it`) instead uses **Stripe** (UPE, `payment_method_stripe`, `.wc-stripe-upe-element`) for cards and a separate **`woo-satispay`** plugin (`payment_method_satispay`, plain `<img>` with no class) for Satispay — entirely different plugins/markup, not just a config toggle within Mollie. `checkout/checkout.css` selectors were kept as generic as possible (targeting core WC classes, not gateway-specific ones) so the base layout works on both; the icon-sizing rule in particular was deliberately changed from a Mollie-only class match (`img.mollie-gateway-icon`) to a plain `label img` selector so it also covers `woo-satispay`'s unclassed logo. Gateway-specific styling (Mollie Components field labels, Stripe UPE fieldset/checkbox) still needs to be verified/built separately per environment — don't assume a Mollie-specific fix or debug finding (e.g. the order-pay "blocked retry" behavior) applies on production, which doesn't run Mollie at all.

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
