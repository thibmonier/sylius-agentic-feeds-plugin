<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Entity;

use Sylius\Component\Core\Model\ProductVariantInterface;

class VariantIdentifiers implements VariantIdentifiersInterface
{
    protected ?int $id = null;

    protected ?ProductVariantInterface $variant = null;

    protected ?string $gtin = null;

    protected ?string $mpn = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVariant(): ?ProductVariantInterface
    {
        return $this->variant;
    }

    public function setVariant(?ProductVariantInterface $variant): void
    {
        $this->variant = $variant;
    }

    public function getGtin(): ?string
    {
        return $this->gtin;
    }

    public function setGtin(?string $gtin): void
    {
        $this->gtin = null === $gtin || '' === trim($gtin) ? null : trim($gtin);
    }

    public function getMpn(): ?string
    {
        return $this->mpn;
    }

    public function setMpn(?string $mpn): void
    {
        $this->mpn = null === $mpn || '' === trim($mpn) ? null : trim($mpn);
    }
}
