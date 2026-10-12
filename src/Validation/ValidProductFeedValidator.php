<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;

final class ValidProductFeedValidator extends ConstraintValidator
{
    public function __construct(private readonly FormatRegistry $formats)
    {
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
        $channel = $value->getChannel();
        if (null === $channel) {
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
        $currencies[] = $channel->getBaseCurrency()?->getCode();
        if (!\in_array($value->getCurrencyCode(), $currencies, true)) {
            $this->context->buildViolation('tmonier_agentic_feeds.product_feed.currency.not_in_channel')->atPath('currencyCode')->addViolation();
        }
    }
}
