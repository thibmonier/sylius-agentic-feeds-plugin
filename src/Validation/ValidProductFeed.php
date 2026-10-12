<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Validation;

use Symfony\Component\Validator\Constraint;

/** The locale and currency belong to the channel; the format is registered. */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ValidProductFeed extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return 'tmonier_agentic_feeds_valid_product_feed';
    }
}
