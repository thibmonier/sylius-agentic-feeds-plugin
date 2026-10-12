<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Entity;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Model\ResourceInterface;

/** GTIN and MPN of one variant (Sylius variants have no attributes). */
interface VariantIdentifiersInterface extends ResourceInterface
{
    public function getVariant(): ?ProductVariantInterface;

    public function setVariant(?ProductVariantInterface $variant): void;

    public function getGtin(): ?string;

    public function setGtin(?string $gtin): void;

    public function getMpn(): ?string;

    public function setMpn(?string $mpn): void;
}
