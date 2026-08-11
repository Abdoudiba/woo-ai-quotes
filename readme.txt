=== AI Quotes for WooCommerce ===
Contributors: (tbd)
Tags: woocommerce, quote, request a quote, ai, b2b
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 9.0
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Draft a branded, calculated quote from a plain-language request — checked against your real catalog, with every number computed by the plugin, never by the AI.

== Description ==

Every existing "Request a Quote" plugin is a manual form: the customer fills
fields, a rep drafts the quote back by hand. AI Quotes turns a plain-language
request into a structured line-item list automatically — then hands it to
you to review before anything is final.

The AI only ever drafts loosely worded line items (what, and how many). This
plugin resolves each one against your real product catalog and computes
every subtotal, tax amount, and total in PHP. The model never sees or sets a
price.

= Features =

* Plain-language request -> AI-drafted line items, matched against your real catalog.
* Fully editable line-item table before anything is finalized — add, remove, or search for a different product.
* Calculation done in PHP from real WooCommerce tax data, never by the AI.
* Branded PDF output — your company name, address, logo, and payment details, configured once in settings.
* Finalized quotes lock in their numbers, so they don't change if a product's price changes later.
* Bring your own OpenAI or Anthropic API key — billed to your own account.

== Installation ==

1. Requires WooCommerce active.
2. If installing from a release zip, the PDF library is already bundled. If running from source, run `composer install --no-dev` in the plugin directory first.
3. Upload to `/wp-content/plugins/` or install via Plugins → Add New → Upload. Activate.
4. Set your AI provider and company details under WooCommerce → Settings → AI Quotes.
5. Use the AI Quotes menu to draft your first quote.

== Frequently Asked Questions ==

= Does this replace my existing "Request a Quote" plugin? =

No — it's a different piece of the same workflow. This plugin drafts and
calculates a quote document; it doesn't (yet) handle the customer-facing
request form or checkout integration some B2B plugins provide.

= Does this send the quote to the customer automatically? =

Not in this version — it generates a PDF for you to download and send
yourself. Automatic emailing is on the roadmap.

= What happens if the AI matches the wrong product? =

Every drafted line is fully editable before you finalize — fix the
description, price, or swap in the correct product from the search dropdown.

== Changelog ==

= 0.1.2 =
* Fix: the Finalize button's redirect to the PDF download used an
  HTML-escaped nonce URL (from wp_nonce_url()) as a raw redirect target,
  corrupting quote_id/_wpnonce and showing "The link you followed has
  expired." Finalizing now redirects correctly.
* Fix: a free-text line item's tax used the settings fallback rate even on
  a store with WooCommerce taxes disabled entirely, while product-linked
  lines correctly stayed untaxed — an inconsistency between the two. The
  fallback rate now only applies when the store has tax enabled at all.

= 0.1.1 =
* Fix: every form submission (New Quote, Save Draft, Finalize, Download,
  Delete) failed with a blank HTTP 400 — handlers were registered on the
  wrong WordPress hook (`admin_action_*` instead of `admin_post_*`, the
  hook admin-post.php actually dispatches on), so nothing ever ran.

= 0.1.0 =
* Initial release (Phase 1): AI-drafted line items, catalog matching,
  editable quote editor, PHP-computed totals, branded PDF export.
