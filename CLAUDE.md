# CLAUDE.md

Sylius 2 plugin "Agentic Feeds": product feeds for Google Merchant Center (XML/TSV), the
OpenAI product feed (JSONL gzip) and the Stripe Agentic Commerce catalog (CSV), per channel,
locale and currency; validation report; stale-feed regeneration; price-change webhook events
(with tmonier/sylius-webhook-outbox-plugin); schema.org Product JSON-LD.
Functional spec, specs read and decisions: `SPEC.md`. Public docs: `README.md`.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console, PHPStan
and functional PHPUnit calls with `php -d memory_limit=2G` when running locally.

```bash
docker compose up -d mysql mailhog        # ports come from compose.override.yml (gitignored)
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # functional + integration, test DB + fixtures
vendor/bin/phpstan analyse                # level max, src + tests
vendor/bin/ecs check
vendor/bin/console lint:container
vendor/bin/console tmonier:feeds:generate -v
```

Local env overrides (gitignored): `compose.override.yml` (MySQL published on 3333, MailHog on
8052/1052), `tests/TestApplication/.env.local` (DATABASE_URL + MAILER_DSN) and
`.env.test.local`. Databases: `tmonier_sylius_agentic_feeds_dev` / `_test`, loaded with the
default fixture suite, which includes `tmonier_agentic_feeds` (Google XML, OpenAI JSONL and
Stripe CSV feeds per channel; the Stripe one has an access token). The test application sets
`default_brand: 'Sylius Demo'` (the demo catalog has no brand attribute) and writes files to
`var/agentic_feeds/<env>`. Demo shop user: verify it (`UPDATE sylius_shop_user SET verified_at
= NOW() WHERE verified_at IS NULL;`). Dev server: `symfony server:start -d --port=8100 --no-tls`
(admin `sylius` / `sylius`), then `/admin/product-feeds/`, `/feeds/google_fashion_web`.

## Architecture

- Entities `src/Entity` (`ProductFeed`, `FeedIssue`), XML mapping `config/doctrine`, migration
  `src/Migrations` (Schema API).
- `Feed/ItemBuilder` (variant -> neutral `FeedItem`: prices Omnibus-safe with
  `calculateLowestPriceBeforeDiscount`, currency conversion, tax of the channel default tax
  zone, stock, attributes, links on the channel hostname), `Feed/FeedGenerator` (batches,
  entity manager cleared between batches, temp streams -> storage, status and issues),
  `Feed/GenerationResult`.
- `Format/*`: `FeedFormatInterface` services tagged `tmonier_agentic_feeds.format`
  (`FormatRegistry`); `GoogleFields` shared by XML/TSV; OpenAI writes through a zlib gzip
  stream filter; Stripe writes product, inventory and pricing files.
- `Validation/`: `Issue`, `ItemValidator` (shared checks, `cut()`), `Gtin`, `ValidProductFeed`.
- `Storage/`: `LocalFeedStorage` (default, atomic rename), `FlysystemFeedStorage` (config).
- `Controller/FeedDownloadController` (`/feeds/{code}/{role}`), `Controller/Admin/GenerateFeedController`.
- `Command/GenerateFeedsCommand`, `Message/*` + `MessageHandler/FeedMessageHandler`,
  `Message/FeedScheduleProvider` (registered in the extension when symfony/scheduler exists).
- `EventListener/CatalogChangeListener` (stale feeds), `EventListener/JsonLdDeduplicationListener`.
- `Outbox/*` registered in the extension only when the outbox plugin interfaces exist.
- `Twig/JsonLdBuilder` + `Twig/AgenticFeedsExtension` (`tmonier_agentic_feeds_json_ld`,
  `tmonier_agentic_feeds_file_urls`), hooks in `config/twig_hooks`.

## Rules

- No core entity change: plugin tables only. Never claim checkout in assistants or guaranteed
  visibility (README "What to expect", SPEC §1).
- Field names follow the specs read on 2026-10-12 (SPEC §2): re-check them before changing.
- Tests never depend on random fixtures: `tests/TestCatalog.php` creates a channel of its own,
  products, attributes, images, promotions, taxes and feeds inside the rolled-back transaction
  (`AgenticFeedsTestTrait`, which also removes the files of the test feeds). The generator
  clears the entity manager: reload entities after a generation. No `markTestSkipped`.
- `tmonier/sylius-webhook-outbox-plugin` and `symfony/scheduler` are dev dependencies (bridge
  tests); keep them out of `require`.
- Labels in `messages`, validation messages in `validators`, flashes in `flashes`; every key in
  en, fr, de, es, it, nl, pl (`TranslationsTest`). Clear the cache after adding a translation file.
