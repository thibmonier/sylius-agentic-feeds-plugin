# SPEC: Agentic Feeds (tmonier/sylius-agentic-feeds-plugin) v1

Functional specification and design decisions. Public documentation: `README.md`.

## 1. Context and positioning

Decision memo of 2026-10-12 ("vente par IA / agentic commerce", workspace `docs/`, not
shipped): checkout inside AI assistants is not open to EU merchants (OpenAI recentred ChatGPT
shopping on discovery on 2026-03-24; Google UCP checkout limited to the US, Canada and
Australia; the French competition authority, opinion 26-A-05 of 2026-07-17, notes that
end-to-end agent purchases do not exist yet in France). What is useful now is the
**discovery layer**: product feeds that follow the OpenAI, Google Merchant Center and Stripe
Agentic Commerce Suite specifications, kept fresh. No feed plugin is tagged for Sylius 2
(`setono/sylius-feed-plugin` only has `0.7.x-dev`).

What the plugin must **not** claim: "sell in ChatGPT / Gemini / Copilot"; "Instant Checkout
compatible"; "official" or "certified"; that a feed guarantees visibility; CRD/PSD2
compliance of a third-party checkout. Buyers check out on the Sylius shop, where the
pre-contractual information, the order button and payment security are under the merchant's
control.

## 2. Specifications read (2026-10-12)

| Target | Pages read on 2026-10-12 | Notes |
|---|---|---|
| Google Merchant Center | https://support.google.com/merchants/answer/7052112 (product data specification, no version shown), https://support.google.com/merchants/answer/160589 (XML), https://support.google.com/merchants/answer/160567 (TSV), https://support.google.com/merchants/answer/6324484 (shipping), https://support.google.com/merchants/answer/6324461 (GTIN) | `https://support.google.com/merchants/answer/6324454` (tax) returned 404: tax syntax UNVERIFIED (not used) |
| OpenAI product feed | https://developers.openai.com/commerce/specs/file-upload/products (file upload format, no version shown), https://developers.openai.com/commerce/llms-full.txt | Onboarding limited to approved partners (chatgpt.com/merchants); SFTP delivery; parquet preferred, `jsonl.gz` accepted; US by default (`target_countries` needs OpenAI confirmation) |
| Stripe Agentic Commerce Suite | https://docs.stripe.com/agentic-commerce, https://docs.stripe.com/agentic-commerce/sellers/use-cases/retail.md, https://docs.stripe.com/agentic-commerce/concepts/catalog-feed?locale=en-US (API `2026-09-30.preview`) | Seller ACS in the US, CA, CH, NO, GB and most EU countries; Stripe must process payments; agents approve connections; gzip not mentioned (plain CSV generated) |
| schema.org | https://schema.org/Product, https://schema.org/Offer | JSON-LD on product pages |

### Fields generated

- **Google** (XML and TSV): `id` (max 50), `title` (max 150, cut with a warning),
  `description` (plain text, max 5000), `link`, `image_link`, `additional_image_link` (max 10),
  `availability` (`in_stock` / `out_of_stock`), `price` (`12.00 EUR`), `sale_price`,
  `sale_price_effective_date` (ISO 8601 `start/end`, only with an end date), `brand` (required),
  `gtin` (valid only), `mpn`, `identifier_exists: no` (without GTIN and MPN), `condition`,
  `item_group_id` (products with variants), `product_type`, `color`, `size`, `shipping_weight`.
  XML: RSS 2.0, plain `title`/`link`/`description`, other attributes `g:`-prefixed, repeated
  elements for repeated attributes, empty attributes left out. TSV: header row, tabs and line
  breaks in values replaced by spaces, `additional_image_link` repeated in 10 columns.
- **OpenAI** (`jsonl.gz`, one object per variant, gzip, UTF-8, absent values left out, never
  placeholders): `item_id`, `title`, `description`, `url`, `brand`, `seller_name` (channel name),
  `seller_url`, `image_url`, `additional_image_urls` (array), `availability`, `price`,
  `sale_price` (only below `price`; the format has no sale dates, so the file says the truth
  while the promotion runs), `condition`, `product_category`, `gtin`, `mpn`, `color`, `size`,
  `weight` + `item_weight_unit: kg`, `is_eligible_search: true`, `is_eligible_checkout: false`,
  `return_policy`, `return_deadline_in_days`, `seller_privacy_policy`, `seller_tos` (from
  `seller_policies`), `target_countries` (feed country), and for variants `group_id`
  (must differ from `item_id`), `listing_has_variations: true`, `variant_dict`.
- **Stripe** (CSV with header, comma, RFC 4180 quoting): product file `id` (max 100), `title`,
  `description`, `link`, `image_link`, `additional_image_link` (comma-separated, commas in URLs
  encoded `%2C`), `availability`, `inventory_quantity`, `inventory_not_tracked`, `price`,
  `sale_price`, `sale_price_effective_date` (`YYYY-MM-DD/YYYY-MM-DD`), `tax_behavior`
  (`inclusive`: prices include VAT), `brand`, `gtin`, `mpn`, `condition`, `product_category`,
  `item_group_id` (max 70), `color`, `size` (max 20), `weight` (`0.25 kg`); inventory file
  `id, availability, inventory_quantity` (tracked variants only: the quantity is required
  there); pricing file `id, price, sale_price, sale_price_effective_date`.

## 3. Model

- `ProductFeed` (resource, table `tmonier_agentic_feed`): code (URL), name, channel, locale,
  currency, optional target country, format, optional access token, enabled; last generation
  date, status (`never`, `ok`, `warnings`, `errors`, `failed`), item / error / warning counts,
  duration, failure message; `staleSince`.
- `FeedIssue` (table `tmonier_agentic_feed_issue`): severity, code, field, item id, product code,
  parameters; replaced at each successful generation (5000 stored at most; counts are complete).

## 4. Generation

- `Feed/ItemBuilder` builds a neutral `FeedItem` per enabled variant of the enabled products of
  the channel (no channel price: error `missing_price`). `Format/*` map it to the target
  fields; `Validation/ItemValidator` + the format checks give `Issue`s: errors leave the item
  out, warnings do not.
- **Prices (decision, Omnibus)**: current channel price converted to the feed currency
  (`CurrencyConverter`), plus the tax of the channel default tax zone when the rate is not
  included. A sale only when a catalog promotion is applied to the channel price and
  `ProductVariantPricesCalculator::calculateLowestPriceBeforeDiscount()` returns a higher price
  (the channel shows lowest prices and Sylius knows the 30-day lowest): price = that lowest
  price, sale price = current price, dates from the promotions (latest start, earliest end).
  The manual "original price" alone never produces a struck-through price.
- **Availability**: tracked variants `on hand - on hold`, untracked always in stock.
- **Texts**: HTML stripped (block ends become spaces), entities decoded, whitespace collapsed;
  titles `product name - options` for products with several enabled variants.
- **Links**: `https://<channel hostname>/<locale>/products/<slug>` + `link_parameters`
  (`{format}` placeholder); images `https://<hostname>/media/image/<path>`, variant images first.
- **Identifiers**: product attributes (configurable codes), default brand; `Validation/Gtin`
  (8/12/13/14 digits, GS1 check digit, reserved prefixes).
- `Feed/FeedGenerator`: variant ids queried once, variants loaded in batches, entity manager
  cleared between batches, files written to temporary streams then to the storage (atomic
  rename for the local storage); failures keep the previous files and record the message.
- Formats are services tagged `tmonier_agentic_feeds.format` (`FeedFormatInterface`): one main
  file and optional companion files (Stripe inventory and pricing).

## 5. Freshness and deltas

- `EventListener/CatalogChangeListener` (onFlush/postFlush): products, variants (stock),
  channel prices, translations, images, attribute values changed → enabled feeds marked stale
  (first date kept). `tmonier:feeds:generate --stale` and `GenerateStaleFeeds` regenerate only
  those (decision: full regeneration of stale feeds rather than partial files, except the
  lightweight Stripe inventory/pricing files written with every Stripe generation).
- Scheduling: cron, or Symfony Scheduler when installed (`schedule.enabled`).
- `Outbox/PriceChangedDetector` (registered only with tmonier/sylius-webhook-outbox-plugin and
  `webhook_events`): `product_variant.price_changed` v1, coalesced per channel and flush.

## 6. Serving

`GET /feeds/{code}/{role}` (`role` = `main`, `inventory`, `pricing`): disabled, unknown, not
generated feeds and wrong tokens answer 404 (decision: no hint that a protected feed exists);
`Last-Modified` and 304; `Cache-Control: public` (private with a token); `X-Robots-Tag:
noindex`. Storage: local directory (default `var/agentic_feeds`) or a Flysystem operator.

## 7. JSON-LD

Twig hook `sylius_shop.product.show` (priority -100): schema.org `Product` with one `Offer` per
enabled variant, from the same `ItemBuilder` (shop channel, locale and currency).
`EventListener/JsonLdDeduplicationListener` removes it from the response when another
`application/ld+json` script declares `"@type": "Product"` (decision: "only if not already
present" checked on the rendered page, which covers themes and other plugins). Sylius 2.3 shop
templates emit no JSON-LD (checked in the 2.3.0 vendor).

## 8. Admin

CRUD of feeds (grid with target, status, counts, stale date, download links; "Generate now"
POST with CSRF token, dispatched on `sylius.command_bus`), issues grid filtered by feed and
severity, breadcrumb with the feed name, menu *Marketing > Product feeds*.

## 9. Decisions taken without explicit specification (conservative)

1. Items without brand are errors (required by the three targets), with `default_brand` as the
   escape hatch.
2. Invalid GTINs are dropped from the files (warning) rather than leaving the item out.
3. `is_eligible_checkout: false` always: checkout stays on the shop.
4. No sale price without the Sylius 30-day reference price; Stripe needs an end date.
5. Prices with the tax of the channel default tax zone (EU display); no shipping or tax
   attributes (account settings).
6. Protected feeds answer 404, like missing ones.
7. Stale feeds are regenerated in full.
8. OpenAI and Stripe files are not uploaded (approval or account needed to test, memo §5.1).

## 10. Out of scope (v1)

SFTP upload to OpenAI and Stripe import API (v1.1), parquet, OpenAI Feed API push, Stripe
promotion feed, ChatGPT Ads, Meta and Microsoft catalogs, llms.txt (covered by
acseo/sylius-geo-plugin), UCP / ACP checkout.
