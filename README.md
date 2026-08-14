# Yuupee Smart Quote Drafting for WooCommerce

Draft a branded, calculated quote from a plain-language request — checked
against your real product catalog, with every number computed in PHP, never
by the AI.

## Why this exists

Every existing WooCommerce "Request a Quote" plugin (B2BKing, Wholesale
Suite, ELEX, WebToffee) is a manual form: the customer fills fields, a rep
drafts the quote back by hand. None of them turn a plain-language request
("client wants 5 HP laptops and a UPS, budget-conscious") into a structured,
calculated quote automatically.

This plugin does that — but keeps the same discipline the Yuupee document
generator this was built from is based on: **the AI only ever drafts loosely
worded line items; PHP resolves them against real catalog data and computes
every number.** The model is never trusted with a price, a tax rate, or a
total.

## What it does (Phase 1 — no WhatsApp yet)

- **Plain-language intake**: a rep types or pastes a customer's request. AI
  extracts a `{description, qty}` list only — no prices, no product IDs, so
  it can't invent either.
- **Catalog matching**: each drafted description is searched against real
  WooCommerce products. A match snapshots that product's real id, name, and
  price; anything unmatched stays a free-text line with no price, for the
  rep to fill in or price manually.
- **Editable line-item table**: every drafted row can be edited, removed, or
  added to (including a real product-search dropdown) before anything is
  finalized — the AI draft is a starting point, never the final document.
- **Calculation engine**: subtotal, tax, and total are computed in PHP from
  the line items — a linked product's real WooCommerce tax class/rate, or a
  configurable fallback rate for free-text items. Never AI arithmetic.
- **Branded PDF** via bundled `dompdf` — company name, address, logo, and
  payment details all come from plugin settings, not hardcoded, since every
  install belongs to a different business.
- **Numbers lock in at finalization**: a finalized quote snapshots its line
  totals, so it doesn't silently change if a product's price changes later —
  same principle as a real invoice.
- **Bring your own AI key** (OpenAI or Anthropic) — usage is billed to the
  store owner's own account, not marked up by this plugin.

## Not in Phase 1 (by design — see roadmap)

- **WhatsApp intake.** Phase 1 deliberately ships the AI-drafting core with
  zero external API setup beyond the AI provider key, so it's usable the
  moment it's installed. This plugin now exposes the REST hook any channel
  integration needs (see "REST API" below) — the actual WhatsApp wiring is
  external, not part of this plugin.
- **Emailing the PDF to the customer.** v1 downloads a PDF for the rep to
  send manually.
- **Multi-turn AI refinement.** Drafting is single-shot; corrections happen
  by editing the table directly, not by re-prompting the AI.
- **Customer-facing quote acceptance / e-signature.**

## Installation

1. Requires WooCommerce active (admin notice, not a fatal error, if missing —
   same pattern as woo-geo-catalog).
2. Requires the bundled `dompdf` library. From a **release zip**, it's
   already included. From a **raw git checkout**, run `composer install
   --no-dev` in the plugin directory first — the plugin shows an admin
   notice and no-ops if `vendor/` is missing, rather than fatal-erroring.
3. Upload to `wp-content/plugins/`, or zip and use Plugins → Add New →
   Upload Plugin. Activate.
4. Configure an AI provider and company details under **WooCommerce →
   Settings → AI Quotes**.
5. Use the **AI Quotes** admin menu to draft, edit, and finalize quotes.

## Architecture

```
yuupee-smart-quote-drafting-for-woocommerce.php                    Plugin bootstrap, WooCommerce + vendor/ dependency checks, HPOS compat
composer.json                        Single dependency: dompdf/dompdf (LGPL, bundled)
includes/
  class-ysqd-settings.php             WooCommerce → Settings → AI Quotes: provider/key, company branding
  class-ysqd-quote-post-type.php      Storage: hidden CPT + post meta, quote-number assignment
  class-ysqd-ai-drafter.php           Request text -> {description, qty} via AI, then matched against the real catalog
  class-ysqd-calculator.php           Subtotal/tax/total — the one thing the AI never touches
  class-ysqd-pdf.php                  dompdf render + streamed download
  class-ysqd-admin.php                All admin screens: list, new-quote intake, line-item editor, form/AJAX handlers
templates/
  quote.php                          Plain PHP/HTML quote template rendered to PDF
assets/
  quote-editor.js                    Editable line-item table: add/remove rows, product search, live preview totals
  admin.css                          Minor admin screen styling
```

Data storage: a hidden custom post type (`ysqd_quote`, no default WP edit
screen — the admin UI is fully custom) plus post meta for line items and
totals. No custom database tables.

## Known limitations / v1 scope boundaries

- **Product matching is single-best-match, not interactive.** Each AI-drafted
  description is matched via one WooCommerce search, taking the top result.
  If that's wrong, the rep corrects it directly in the table — there's no
  "pick from 3 candidates" UI yet.
- **Tax uses the store's base-location rate**, not a customer-specific
  checkout address — the standard simplification for a seller-drafted quote
  (not a live cart), but worth knowing if the store has many tax zones.
- **API keys stored as WordPress options**, not a secrets manager — standard
  practice for WP plugins, but worth knowing if the site's threat model
  calls for more.
- **Single-site tested only** so far — built without a live WooCommerce
  install available; needs the same real-install test pass woo-geo-catalog
  went through before this should be trusted for anything customer-facing.

## REST API

`POST /wp-json/ysqd/v1/quotes` runs the same draft pipeline as the admin
"New Quote" screen — AI drafts line items from `request_text`, they're
matched against the real catalog, totals are computed — and always creates
a **draft**, never a finalized quote. The route itself is generic (works
for any external automation — a CRM, a script, anything), not tied to any
one integration.

- **Auth**: WordPress core [Application Passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)
  (native since WP 5.6, no extra plugin) via HTTP Basic Auth, for a user
  with the `manage_woocommerce` capability. Not WooCommerce's consumer
  key/secret scheme — that only covers the `wc/*` namespace, not this
  plugin's custom route.
- **Request body**: `{ "customer_name"?, "customer_email"?, "request_text" }`
- **Response**: `{ quote_id, status: "draft", edit_url, line_items, totals }`
- On an AI-drafting failure, the quote row is still created (empty) rather
  than silently discarded, so the caller always has a `quote_id`/`edit_url`
  to hand a rep even if the request has to be built manually instead.

Used by Yuupee's own `create_ai_quote` OpenClaw tool for WhatsApp intake —
see that project's own docs for the integration-specific side (env vars,
access control by sender). **This is Yuupee-internal, not a packaged
feature of this plugin.** The tool only runs inside an OpenClaw instance,
which is a separate orchestrator a buyer would have to install and run
themselves — a much bigger ask than installing a WordPress plugin. Until
there's a self-contained integration (the plugin itself talking to a
WhatsApp Business API webhook, no external orchestrator required), don't
market or sell WhatsApp intake as a capability of this plugin — see
"Roadmap" below.

## Roadmap

- **WhatsApp intake, without requiring OpenClaw.** The current
  implementation only works because Yuupee already runs OpenClaw — a real
  buyer wouldn't. Would need the plugin to handle WhatsApp Business Cloud
  API webhooks directly and call the AI provider itself (already BYOK),
  cutting out the orchestrator dependency. Real, non-trivial work — not
  planned until there's a concrete reason to prioritize it over other
  things.
- Email-the-PDF-to-customer action.
- Interactive product-match picker instead of best-single-match.
- Customer-facing quote view + acceptance.

## License

GPL v2 or later, matching the plugin header. Bundles `dompdf` (LGPL-2.1,
GPL-compatible for redistribution) — see LICENSE.
