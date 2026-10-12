# Changelog

All notable changes to `tmonier/sylius-agentic-feeds-plugin`.

## 1.0.0

First release:
- feed prices are the displayed prices (US/CA without tax), no feed without exchange rate,
  per-variant GTIN/MPN (variant form, import command), Stripe shipping entries per feed;
- product feeds per channel, locale, currency and target country: Google Merchant Center (XML,
  TSV), OpenAI product feed (JSON Lines, gzip), Stripe Agentic Commerce catalog (product,
  inventory and pricing CSV);
- Omnibus-safe sale prices, VAT included, currency conversion, stock, variants grouped,
  GTIN/MPN/brand from attributes;
- per-item validation report (admin, console, CI exit code);
- incremental regeneration of stale feeds (cron or Symfony Scheduler), Messenger messages;
- `product_variant.price_changed` events with tmonier/sylius-webhook-outbox-plugin;
- stable feed URLs with optional access token, local or Flysystem storage;
- schema.org Product/Offer JSON-LD on product pages;
- admin pages, demo fixtures, 7 languages.
