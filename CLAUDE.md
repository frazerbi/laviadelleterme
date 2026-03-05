# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress site ("La Via delle Terme") for a thermal spa booking platform. The codebase consists of two custom WordPress plugins and a child theme, all integrating with WooCommerce and an external booking system called TermeGest.

## Build Commands

### plugin-custom-skianet (main plugin)

```bash
cd wp-content/plugins/plugin-custom-skianet

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Build JS/CSS assets (LESS -> minified CSS, JS -> minified JS)
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

## Architecture

### Two Custom Plugins

**`plugin-custom-skianet`** — The main booking plugin. Handles the full booking lifecycle:
- SOAP API communication with TermeGest (external booking management system)
- Availability checking via WP cron (daily)
- Cart/checkout integration with WooCommerce
- License code assignment after payment (via WooCommerce License Delivery plugin)
- Email notifications for booked and non-booked products
- Custom WooCommerce order statuses

**`plugin-custom-termeshop`** — Legacy plugin (mostly commented out). Currently only active components are:
- `Order Management/add_status.php` — custom order statuses
- `registration-form-extension/registration-form-ext.php` — registration form extension

### plugin-custom-skianet Class Map

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
| `Termegest_Encryption` | `includes/class-termegest-encryption.php` | Encryption utilities for TermeGest data |

### SOAP API Integration (TermeGest)

The plugin communicates with two SOAP endpoints:
- `https://www.termegest.it/getReserv.asmx` — get availability and reservations
- `https://www.termegest.it/setinfo.asmx` — set sold items and bookings

PHP classes generated for the SOAP clients live in `src/TermeGestGetReserv/` and `src/TermeGestSetInfo/`. Config types are in `config/`. Autoloaded via Composer PSR-4.

Helper functions wrapping the API class are in `includes/termegest-api-functions.php`.

### Payment Flow

On `woocommerce_payment_complete`:
1. `Booking_Code_Assignment` assigns license codes (WooCommerce License Delivery plugin)
2. `Booking_Termegest_Sync` sends `setVenduto` (for all items) and `setPrenotazione` (for booking items) to TermeGest
3. `Booking_Email_Notification` sends confirmation email to customer (booking details + coupon codes)
4. `Booking_Nonbooking_Email` sends coupon email for non-booking products in mixed orders

### Theme

`wp-content/themes/hello-theme-child-master/` — Child theme of Hello Elementor:
- `style.css` — Custom WooCommerce and site-wide CSS overrides
- `mobile-menu-style.css` — Mobile menu styles
- `js/script.js` — Custom JS
- `woocommerce/pdf/Templates/` — PDF templates for invoices, packing slips, credit notes (using WooCommerce PDF plugin)
- `woocommerce/emails/` — Custom WooCommerce email templates (prefixed with `__` = disabled/legacy)

### Assets Pipeline (plugin-custom-skianet)

- Source CSS: `assets/css/skianet-style.less` (LESS)
- Source JS: `assets/js/skianet-script.js`
- Build output: minified `.min.css` and `.min.js` with sourcemaps
- Build tool: esbuild (`build.js`) + PostCSS with prefix-selector
- JS dependencies: `vanilla-calendar-pro` (bundled), Select2 and FullCalendar loaded from CDN

### Disabled/Legacy Components

Files prefixed with `__` or commented out in plugin entry points are disabled legacy code. The `__components/` directory contains old shortcode-based components replaced by the current architecture.
