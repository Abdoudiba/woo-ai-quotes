=== Yuupee Smart Quote Drafting for WooCommerce ===
Contributors: abdoudiba
Tags: woocommerce, quote, request a quote, ai, b2b
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 8.0
WC tested up to: 9.0
Stable tag: 0.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Draft a branded, calculated quote from a plain-language request — checked against your real catalog, computed by the plugin, never by the AI.

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
* Drafts using whichever AI provider you've already connected under Settings → Connectors (WordPress's built-in AI Client) — no separate API key to manage in this plugin.

== Installation ==

1. Requires WooCommerce active, and WordPress 7.0+ with an AI provider connected under Settings → Connectors (Anthropic, Google, or OpenAI ship built into WordPress 7.0).
2. If installing from a release zip, the PDF library is already bundled. If running from source, run `composer install --no-dev` in the plugin directory first.
3. Upload to `/wp-content/plugins/` or install via Plugins → Add New → Upload. Activate.
4. Set your company details under WooCommerce → Settings → AI Quotes.
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

= Which AI provider does this use? =

Whichever one you've connected under Settings → Connectors — this plugin
has no AI integration of its own. It calls WordPress's built-in AI Client
(`wp_ai_client_prompt()`, core since WordPress 7.0), which routes the
request to your connected provider. If no provider is connected, AI
drafting is disabled and you can still build a quote manually.

== External services ==

This plugin turns a rep's plain-language request into a draft list of line
items (description + quantity only — no prices, no product IDs, no customer
data) by calling WordPress's built-in AI Client, which forwards the request
text to whichever AI provider the site owner has connected under Settings →
Connectors. Nothing is sent unless a store rep actively triggers a draft.

This plugin does not choose or hardcode a provider, and ships no API keys —
credentials are entered and managed by WordPress core, not this plugin. The
built-in connectors available in WordPress 7.0 are:

* **OpenAI** —
  [Terms of use](https://openai.com/policies/row-terms-of-use/) —
  [Privacy policy](https://openai.com/policies/privacy-policy/)
* **Anthropic** —
  [Commercial Terms of Service](https://www.anthropic.com/legal/commercial-terms) —
  [Privacy policy](https://www.anthropic.com/legal/privacy)
* **Google** —
  [Terms of service](https://policies.google.com/terms) —
  [Privacy policy](https://policies.google.com/privacy)

Only whichever provider the site owner has connected is ever called.

== Changelog ==

= 0.4.2 =
* Fix: "Tested up to" bumped to 7.1 (was stale at 7.0, flagged by Plugin Check).
* No functional changes — re-confirmed via a fresh Plugin Check run that the
  0.4.1 PDF-template stylesheet fix (see below) still triggers zero
  enqueue-related findings.

= 0.4.1 =
* Fix: the PDF template's stylesheet is no longer written as literal
  template HTML — it's read from its own file (`assets/css/quote-pdf.css`)
  and echoed in PHP, which WordPress.org's Plugin Check tool now confirms
  triggers zero enqueue-related findings (the plain `<style>` block, and a
  `<link rel="stylesheet">` alternative, both did). Behavior and rendered
  output are unchanged — verified against a real generated PDF.

= 0.4.0 =
* Change: AI drafting now goes through WordPress's built-in AI Client
  (`wp_ai_client_prompt()`) instead of calling OpenAI/Anthropic directly.
  The site owner connects a provider under Settings → Connectors; this
  plugin no longer stores or asks for an API key. `Requires at least`
  raised to WordPress 7.0 accordingly.
* Fix: quote.php's PDF template markup is a standalone HTML string handed
  to Dompdf, never a WordPress-served page — clarified in code comments
  after a review flagged its inline `<style>` block as a missing
  wp_enqueue_style() call.

= 0.3.0 =
* Rename: plugin renamed to "Yuupee Smart Quote Drafting for WooCommerce" (from
  "AI Quotes for WooCommerce") and internal prefix changed `waq_` -> `ysqd_`
  for distinctiveness and a 4+ character unique prefix.
* Add: documented third-party AI services (OpenAI, Anthropic) in this readme.
* Add: `Requires Plugins: woocommerce` header.

= 0.2.0 =
* Add: REST endpoint (`POST /wp-json/ysqd/v1/quotes`) exposing the AI-draft
  pipeline to external automation — always creates a draft, never a
  finalized quote. Authenticated via WordPress core Application Passwords.

= 0.1.3 =
* Improve: catalog matching for AI-drafted line items now retries with
  progressively fewer trailing words when the full description doesn't
  match. WordPress's default search requires every word to match somewhere
  in the post, so a single trailing word in a different language than the
  catalog (e.g. "Keyboard" against a French "Clavier" listing) could fail
  an otherwise-correct match on brand + model.

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
