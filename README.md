# Agentic Feeds for Sylius

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
| Stripe Agentic Commerce | Product catalog CSV, plus the inventory and pricing CSVs Stripe accepts every 15 minutes. |
| One feed per market | Channel, locale, currency (converted with the Sylius exchange rates) and target country per feed; as many feeds as you need. |
| Correct prices | Prices include the VAT of the channel default tax zone. A sale price only when a catalog promotion applies **and** Sylius knows the lowest price of the 30 days before it (EU Omnibus): never a struck-through "original price" set by hand. |
| Variants | One item per enabled variant, grouped by product (`item_group_id` / `group_id`), options as colour, size or variant attributes. |
| Identifiers | GTIN, MPN and brand from configurable product attributes, with a default brand; GTIN check digit and reserved prefixes verified. |
| Stock | `in_stock` / `out_of_stock` from on hand minus on hold (tracked variants), quantities in the Stripe files. |
| Validation | Per item: missing required fields, too long titles and descriptions, invalid GTINs, missing identifiers. Items with errors are left out and listed; admin report per feed; exit code for CI. |
| Freshness | A catalog change (product, variant, stock, price, translation, image, attribute) marks the feeds stale: `tmonier:feeds:generate --stale` (cron) or Symfony Scheduler regenerates only those. |
| Deltas | With [tmonier/sylius-webhook-outbox-plugin](https://github.com/thibmonier/sylius-webhook-outbox-plugin): `product_variant.price_changed` events (stock changes are its `product_variant.stock_changed`). |
| Serving | Stable URLs `/feeds/{code}` (and `/feeds/{code}/inventory`, `/feeds/{code}/pricing`), optional access token, `Last-Modified` / 304, files in a local directory or any Flysystem storage. |
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
brand, items are left out and reported. GTINs come from a text attribute `gtin`: invalid ones
(length, check digit, reserved prefixes 02, 04, 2, 05, 98, 99) are left out of the files and
reported; without GTIN nor MPN, Google items get `identifier_exists: no`.

**Prices and taxes.** Feed prices are the channel prices converted to the feed currency, with
the tax of the channel default tax zone added when the rate is not included in the price (VAT
display in Europe; US merchants should keep prices without tax). Shipping is not sent: set it
in Merchant Center (recommended by Google) and in your Stripe profile.

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
| Access token | optional, 16+ characters: then `?token=...` or the `X-Feed-Token` header is required (404 otherwise) |

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
- Shipping and tax attributes are not sent (account settings of Merchant Center and Stripe).
- One item per variant; variant images are listed first, then the product images.
- Image URLs point to the original files (`/media/image/...`); Google requires 500 x 500
  pixels at least from 2027-01-31.

## Development

See `CLAUDE.md` for the local environment and commands, `SPEC.md` for the specifications read
and the design decisions.

## License

MIT, see [LICENSE](LICENSE).
