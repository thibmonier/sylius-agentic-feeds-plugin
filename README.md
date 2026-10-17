# Agentic Feeds for Sylius

> Français : [README.fr.md](README.fr.md) <!-- vitrine:lang -->

[![Build](https://github.com/thibmonier/sylius-agentic-feeds-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-agentic-feeds-plugin/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Product feeds for shopping engines and AI assistants in Sylius 2: Google Merchant Center
(XML or TSV), the OpenAI product feed (JSON Lines, gzip) and the Stripe Agentic Commerce
catalog (CSV), plus schema.org Product JSON-LD on product pages.**

Make your catalog readable by Google Shopping, ChatGPT shopping and agents connected to
Stripe, from the data you already have in Sylius: one feed per channel, locale, currency and
format, validated item by item, regenerated when the catalog changes, served at a stable URL.

What this plugin is **not**: it does not put a checkout in ChatGPT, Gemini or Copilot, and it
does not guarantee that your products appear anywhere. See [What to expect](#what-to-expect).

## Features

| Need | What the plugin does |
|---|---|
| Google Merchant Center | RSS 2.0 XML with the `g:` namespace, or a tab-separated file: ids, titles, plain-text descriptions, links, images, availability, price, sale price and dates, brand, GTIN, MPN, `identifier_exists`, condition, `item_group_id`, `product_type`, colour, size, shipping weight. |
| OpenAI product feed | JSON Lines compressed with gzip, OpenAI field names (`item_id`, `url`, `image_url`, `group_id`, `variant_dict`, `seller_name`...), `is_eligible_search: true`, `is_eligible_checkout: false` (buyers check out on your shop), optional return policy and seller URLs per channel. |
| Stripe Agentic Commerce | Product catalog CSV with the shipping entries of the feed, plus the inventory and pricing CSVs Stripe accepts every 15 minutes. |
| One feed per market | Channel, locale, currency (converted with the Sylius exchange rates) and target country per feed; as many feeds as you need. |
| Correct prices | The prices your shop displays, in the feed currency (a feed fails rather than publish unconverted amounts without exchange rate); without tax in US and Canada feeds. A sale price only when a catalog promotion applies **and** Sylius knows the lowest price of the 30 days before it (EU Omnibus): never a struck-through "original price" set by hand. |
| Variants | One item per enabled variant, grouped by product (`item_group_id` / `group_id`), options as colour, size or variant attributes. |
| Identifiers | GTIN and MPN per variant (variant form, `tmonier:feeds:import-identifiers`), product attributes for products without variants, brand attribute or default brand; check digit and reserved prefixes verified, a GTIN never repeated on two items. |
| Stock | `in_stock` / `out_of_stock` from on hand minus on hold (tracked variants), quantities in the Stripe files. |
| Validation | Per item: missing required fields, too long titles and descriptions, invalid GTINs, missing identifiers. Items with errors are left out and listed; admin report per feed; exit code for CI. |
| Freshness | A catalog change (product, variant, stock, price, translation, image, attribute) marks the feeds stale: `tmonier:feeds:generate --stale` (cron) or Symfony Scheduler regenerates only those. |
| Deltas | With [tmonier/sylius-webhook-outbox-plugin](https://github.com/thibmonier/sylius-webhook-outbox-plugin): `product_variant.price_changed` events (stock changes are its `product_variant.stock_changed`). |
| Serving | Stable URLs `/feeds/{code}` (and `/feeds/{code}/inventory`, `/feeds/{code}/pricing`), optional access token (`X-Feed-Token` header), `Last-Modified` / 304, files in a local directory or any Flysystem storage; feeds of disabled channels are neither generated nor served. |
| Product pages | schema.org `Product` with one `Offer` per variant (JSON-LD), left out when the page already has a Product JSON-LD. |
| Admin | *Marketing > Product feeds*: configurations, last generation status and counts, download links, "Generate now", issues per feed. |

Also included: translations in English, French, German, Spanish, Italian, Dutch and Polish;
Messenger messages; demo fixtures; MySQL, MariaDB and PostgreSQL support.

## What to expect

- **Discovery, not checkout.** Feeds make your catalog readable by shopping engines and AI
  assistants. Buyers are sent to your product pages and check out on your shop, where your
  legal information, consent and payment flows apply.
- **No guarantee of visibility.** Google, OpenAI and Stripe decide what they show. Google
  reviews products against its policies; OpenAI says that a feed does not guarantee placement.
- **OpenAI is gated.** Onboarding product feeds to ChatGPT is limited to partners approved by
  OpenAI (apply on chatgpt.com/merchants), and OpenAI expects files pushed by SFTP: this plugin
  generates the file, it does not upload it.
- **Stripe** Agentic Commerce requires Stripe to process your payments and your approval of
  each agent; you upload the files (Dashboard or API). This plugin does not call Stripe.
- **Google Merchant Center** fetches the feed URL on a schedule you set in Merchant Center.
- Not "selling in ChatGPT / Gemini": that is not available to European merchants today.

## Screenshots

![Product feeds in Marketing: Google, OpenAI and Stripe feeds with their last generation and files](docs/images/admin-product-feeds.jpg)

![Stripe feed form with its shipping rules](docs/images/admin-product-feed-stripe.jpg)

![Issues found while generating the Stripe feed](docs/images/admin-product-feed-issues.jpg)

## Requirements

| | Version |
|---|---|
| PHP | 8.2+ (Sylius 2.2), 8.3+ (Sylius 2.3) |
| Sylius | 2.2, 2.3 |
| Symfony | 6.4, 7.4 (Sylius 2.2); 6.4, 7.4, 8.0 (Sylius 2.3, Symfony 8 needs PHP 8.4) |

## Installation

1. Require the package:

    ```bash
    composer require tmonier/sylius-agentic-feeds-plugin
    ```

2. Register the bundle in `config/bundles.php` (Symfony Flex does it automatically once a recipe exists):

    ```php
    Tmonier\SyliusAgenticFeedsPlugin\TmonierSyliusAgenticFeedsPlugin::class => ['all' => true],
    ```

3. Import the configuration in `config/packages/tmonier_sylius_agentic_feeds.yaml`:

    ```yaml
    imports:
        - { resource: "@TmonierSyliusAgenticFeedsPlugin/config/config.yaml" }
    ```

4. Import the routes (admin pages and the public `/feeds/...` URLs) in
   `config/routes/tmonier_sylius_agentic_feeds.yaml`:

    ```yaml
    tmonier_sylius_agentic_feeds:
        resource: "@TmonierSyliusAgenticFeedsPlugin/config/routes.yaml"
    ```

5. Run the migrations:

    ```bash
    bin/console doctrine:migrations:migrate
    ```

6. Create feeds in *Marketing > Product feeds*, then generate them from cron:

    ```cron
    */15 * * * * bin/console tmonier:feeds:generate --stale
    0 3 * * *    bin/console tmonier:feeds:generate
    ```

## Configuration

```yaml
# config/packages/tmonier_sylius_agentic_feeds.yaml
tmonier_sylius_agentic_feeds:
    storage:
        adapter: local                      # local | flysystem
        directory: '%kernel.project_dir%/var/agentic_feeds'
        flysystem_service: null             # e.g. a league/flysystem-bundle storage service id
    attributes:                             # product attribute codes, null to disable
        gtin: gtin
        mpn: mpn
        brand: brand
    default_brand: null                     # brand of products without brand attribute
    condition: new                          # new | refurbished | used
    link_parameters: 'utm_medium=feed&utm_source={format}'
    batch_size: 100
    json_ld: true
    schedule:
        enabled: false                      # with symfony/scheduler: regenerate stale feeds
        frequency: '1 hour'
    webhook_events: true                    # with tmonier/sylius-webhook-outbox-plugin
    seller_policies:                        # OpenAI fields, per channel code
        FASHION_WEB:
            return_policy: 'https://shop.example.com/returns'
            return_days: 30
            privacy_policy: 'https://shop.example.com/privacy'
            terms: 'https://shop.example.com/terms'
```

**Brand and identifiers.** Google, OpenAI and Stripe require a brand for most products. Use a
text product attribute `brand` (or set `default_brand`, e.g. for your own label); without a
brand, items are left out and reported. Each variant has its own GTIN (Google: "each variant has
its own GTIN"): enter GTIN and MPN on the variant form (the product form of simple products), or
import them:

```bash
bin/console tmonier:feeds:import-identifiers identifiers.csv --dry-run   # variant_code;gtin;mpn
```

Every line is checked first (known variant, listed once, at most 3 values, valid GTIN not
repeated in the file, MPN of 70 characters at most); with one rejected line, the errors are
listed with their line numbers and nothing is written.

The `gtin` / `mpn` product attributes are used for products without variants only; on products
with variants they are not repeated (warning). Invalid GTINs (length, check digit, reserved
prefixes 02, 04, 2, 05, 98, 99) and a GTIN already used by another item are left out and
reported; Google items get `identifier_exists: no` only when the variant has no identifier at
all (the attribute is omitted when a GTIN was left out or not repeated on variants).

**Prices and taxes.** Feed prices are the prices your shop displays (the channel prices, which
include the tax when the rate of the channel default tax zone is "included in price"),
converted to the feed currency with the Sylius exchange rates. A feed in a currency without
exchange rate is refused in the form and fails (previous files kept) rather than publish
unconverted amounts. Feeds targeting the US or Canada get prices without tax (an included tax is
removed), as Google requires there. Elsewhere Google requires prices with VAT: a shop whose
rates are not included in prices gets a `price_excludes_tax` warning per item. A channel without
default tax zone gets a `tax_unknown` warning and its prices are sent as excluding tax. The Stripe
`tax_behavior` column says which (`inclusive` / `exclusive`).

**Shipping.** Google: set shipping in the Merchant Center account (recommended by Google).
Stripe requires a `shipping` value for physical goods: enter the shipping entries of the feed
(one per line, `country:delivery_area:service:speed_range:price`, e.g.
`FR:ALL:Standard:2-4:9.90 EUR`, or `US:ALL:Standard::5.00 USD` without speed range); they are required for Stripe feeds and sent for variants
that require shipping.

**Sales (Omnibus).** A product shows a reduced price only when a catalog promotion applies to
it and the channel shows the lowest price before discounts (Sylius price history): the feed
price is that 30-day lowest price, the sale price the current price, with the dates of the
promotion. Otherwise the current price is the only price. The Stripe pricing format needs an
end date: promotions without end date are sent as the price, without sale price.

**Storage.** The local directory must be shared by your web and worker nodes; otherwise use
Flysystem (S3, SFTP...):

```yaml
flysystem:
    storages:
        feeds.storage:
            adapter: 'aws'
            options: { client: 'aws_client', bucket: 'my-feeds' }

tmonier_sylius_agentic_feeds:
    storage:
        adapter: flysystem
        flysystem_service: feeds.storage
```

### Feeds

| Field | Notes |
|---|---|
| Code | letters, digits, `-`, `_`; the URL is `/feeds/{code}` |
| Channel, locale, currency | the locale and currency must be enabled in the channel |
| Target country | optional (OpenAI `target_countries`) |
| Format | `google_xml`, `google_tsv`, `openai_jsonl`, `stripe_csv` |
| Access token | optional, 16+ characters: then the `X-Feed-Token` header (recommended) or `?token=...` is required (404 otherwise). Query strings end up in web server, proxy and CDN logs: prefer the header when the consumer supports it, and rotate the token if it leaked |
| Shipping entries | Stripe feeds: one `country:delivery_area:service:speed_range:price` per line |

Files: `{code}.xml`, `{code}.tsv`, `{code}.jsonl.gz`, `{code}.csv`, `{code}-inventory.csv`,
`{code}-pricing.csv`. A failed generation keeps the previous files.

### Command

```bash
bin/console tmonier:feeds:generate                   # every enabled feed
bin/console tmonier:feeds:generate google_web -v     # one feed, list the issues
bin/console tmonier:feeds:generate --stale           # only feeds whose catalog changed
bin/console tmonier:feeds:generate --dry-run --fail-on-errors   # CI: exit 1 if items are left out
```

### Messenger and scheduling

`Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateFeed` (one feed) and `GenerateStaleFeeds` are
dispatched on `sylius.command_bus` ("Generate now" in the admin). They run synchronously
unless you route them to a transport:

```yaml
framework:
    messenger:
        routing:
            'Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateFeed': async
```

With `symfony/scheduler` installed and `schedule.enabled: true`, the schedule
`tmonier_agentic_feeds` dispatches `GenerateStaleFeeds` every `frequency`
(`bin/console messenger:consume scheduler_tmonier_agentic_feeds`).

### Webhook events

With [tmonier/sylius-webhook-outbox-plugin](https://github.com/thibmonier/sylius-webhook-outbox-plugin),
the event type `product_variant.price_changed` (version 1) can be subscribed to:

```json
{"channel": "FASHION_WEB", "variants": [{"code": "TSHIRT-M", "product_code": "TSHIRT", "price": 1250, "original_price": null, "previous_price": 1000, "previous_original_price": null}]}
```

Amounts in minor units of the channel base currency, one event per channel and flush.

### JSON-LD

The product page gets a `<script type="application/ld+json">` with a schema.org `Product`
(name, description, images, brand, sku) and one `Offer` per enabled variant (price, currency,
availability, condition, GTIN, MPN, `priceValidUntil` during a promotion), in the visitor's
locale and currency. When the page already contains a Product JSON-LD (theme, another plugin),
this one is removed. Set `json_ld: false` to turn it off.

## Screenshots

`docs/images/` is reserved for screenshots (not included yet): feed list with statuses and
download links, feed form, issues report, generated XML.

## Limitations

- Files are generated, not pushed: no SFTP upload to OpenAI, no Stripe import API call.
- No parquet (OpenAI's preferred format), no OpenAI Feed API push, no promotion feed.
- Google shipping and tax attributes are not sent (Merchant Center account settings).
- OpenAI: `target_countries` only for the US, CA and MX (the markets of the format).
- One item per variant; variant images are listed first, then the product images.
- Image URLs point to the original files (`/media/image/...`); Google requires 500 x 500
  pixels at least from 2027-01-31.

## Development

See `CLAUDE.md` for the local environment and commands, `SPEC.md` for the specifications read
and the design decisions.

<!-- vitrine:demo-b2b:start -->
## B2B demo

This plugin is used in the [Sylius B2B demo](https://github.com/thibmonier/sylius-b2b-demo): a Sylius 2.3 shop with fictitious sample data where the B2B plugins work together. The source code, setup and fixtures of the demo are in that repository.

The demo is an example, not a production setup.
<!-- vitrine:demo-b2b:end -->

## License

MIT, see [LICENSE](LICENSE).
