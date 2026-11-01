<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\VariantIdentifiers;

final class VariantIdentifiersTest extends TestCase
{
    #[Test]
    public function defaultState(): void
    {
        $vi = new VariantIdentifiers();

        self::assertNull($vi->getId());
        self::assertNull($vi->getVariant());
        self::assertNull($vi->getGtin());
        self::assertNull($vi->getMpn());
    }

    #[Test]
    public function setGtinTrims(): void
    {
        $vi = new VariantIdentifiers();

        $vi->setGtin('  4006381333931  ');

        self::assertSame('4006381333931', $vi->getGtin());
    }

    #[Test]
    public function setGtinEmptyBecomesNull(): void
    {
        $vi = new VariantIdentifiers();

        $vi->setGtin('   ');

        self::assertNull($vi->getGtin());
    }

    #[Test]
    public function setGtinNullStaysNull(): void
    {
        $vi = new VariantIdentifiers();
        $vi->setGtin('123');

        $vi->setGtin(null);

        self::assertNull($vi->getGtin());
    }

    #[Test]
    public function setMpnTrims(): void
    {
        $vi = new VariantIdentifiers();

        $vi->setMpn('  ABC-123  ');

        self::assertSame('ABC-123', $vi->getMpn());
    }

    #[Test]
    public function setMpnEmptyBecomesNull(): void
    {
        $vi = new VariantIdentifiers();

        $vi->setMpn('');

        self::assertNull($vi->getMpn());
    }

    #[Test]
    public function setMpnNullStaysNull(): void
    {
        $vi = new VariantIdentifiers();

        $vi->setMpn(null);

        self::assertNull($vi->getMpn());
    }

    #[Test]
    public function mpnMaxLengthConstant(): void
    {
        self::assertSame(70, VariantIdentifiers::MPN_MAX_LENGTH);
    }
}
