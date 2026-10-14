<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Validation;

use Sylius\Component\Currency\Model\ExchangeRateInterface;
use Sylius\Component\Currency\Repository\ExchangeRateRepositoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;
use Tmonier\SyliusAgenticFeedsPlugin\Format\StripeCsvFormat;

final class ValidProductFeedValidator extends ConstraintValidator
{
    /** @param ExchangeRateRepositoryInterface<ExchangeRateInterface> $exchangeRateRepository */
    public function __construct(
        private readonly FormatRegistry $formats,
        private readonly ExchangeRateRepositoryInterface $exchangeRateRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidProductFeed) {
            throw new UnexpectedTypeException($constraint, ValidProductFeed::class);
        }
        if (!$value instanceof ProductFeedInterface) {
            return;
        }
        if (null === $value->getFormat() || !$this->formats->has($value->getFormat())) {
            $this->context->buildViolation('tmonier_agentic_feeds.product_feed.format.invalid')->atPath('format')->addViolation();
        }
        foreach ($value->getShippingRules() as $rule) {
            if (1 !== preg_match(StripeCsvFormat::SHIPPING_ENTRY, $rule)) {
                $this->context->buildViolation('tmonier_agentic_feeds.product_feed.shipping_rules.invalid')->setParameter('%rule%', $rule)->atPath('shippingRulesText')->addViolation();
            }
        }
        if (StripeCsvFormat::CODE === $value->getFormat() && [] === $value->getShippingRules()) {
            $this->context->buildViolation('tmonier_agentic_feeds.product_feed.shipping_rules.required')->atPath('shippingRulesText')->addViolation();
        }
        $channel = $value->getChannel();
        if (!$channel instanceof \Sylius\Component\Core\Model\ChannelInterface) {
            return;
        }
        $locales = [];
        foreach ($channel->getLocales() as $locale) {
            $locales[] = $locale->getCode();
        }
        if (!\in_array($value->getLocaleCode(), $locales, true)) {
            $this->context->buildViolation('tmonier_agentic_feeds.product_feed.locale.not_in_channel')->atPath('localeCode')->addViolation();
        }
        $currencies = [];
        foreach ($channel->getCurrencies() as $currency) {
            $currencies[] = $currency->getCode();
        }
        $base = $channel->getBaseCurrency()?->getCode();
        $currencies[] = $base;
        if (!\in_array($value->getCurrencyCode(), $currencies, true)) {
            $this->context->buildViolation('tmonier_agentic_feeds.product_feed.currency.not_in_channel')->atPath('currencyCode')->addViolation();
        } elseif (null !== $base && $base !== $value->getCurrencyCode() && !$this->exchangeRateRepository->findOneWithCurrencyPair($base, (string) $value->getCurrencyCode()) instanceof \Sylius\Component\Currency\Model\ExchangeRateInterface) {
            // Without a rate Sylius would publish the amounts unconverted.
            $this->context->buildViolation('tmonier_agentic_feeds.product_feed.currency.no_exchange_rate')->atPath('currencyCode')->addViolation();
        }
    }
}
