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
- `style.css` — Custom WooCommerce and site-wide CSS overrides
- `woocommerce/pdf/Templates/` — PDF templates for invoices, packing slips, credit notes
- `woocommerce/emails/` — Custom WooCommerce email templates (prefixed with `__` = disabled/legacy)

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

### Disabled/Legacy Components

- Files prefixed with `__` are disabled legacy code.
- `plugin-custom-termeshop` — legacy plugin referenced in older docs; not present in this repo.
