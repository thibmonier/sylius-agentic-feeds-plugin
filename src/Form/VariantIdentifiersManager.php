<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Form;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\VariantIdentifiersInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\VariantIdentifiersRepositoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Gtin;

/**
 * Loads and writes the GTIN / MPN of variants (plugin table). Form data is staged and written
 * on the resource controller events "sylius.product_variant.pre_create/pre_update" (and
 * "sylius.product.*" for simple products), then flushed with the variant; also used by the
 * import command.
 */
final class VariantIdentifiersManager
{
    /** @var \SplObjectStorage<ProductVariantInterface, array{?string, ?string}> */
    private \SplObjectStorage $staged;

    /** @param FactoryInterface<VariantIdentifiersInterface> $factory */
    public function __construct(
        private readonly VariantIdentifiersRepositoryInterface $repository,
        private readonly FactoryInterface $factory,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->staged = new \SplObjectStorage();
    }

    /** @return array{gtin: ?string, mpn: ?string} */
    public function load(ProductVariantInterface $variant): array
    {
        $identifiers = $this->repository->findOneByVariant($variant);

        return ['gtin' => $identifiers?->getGtin(), 'mpn' => $identifiers?->getMpn()];
    }

    public function stage(ProductVariantInterface $variant, ?string $gtin, ?string $mpn): void
    {
        $this->staged[$variant] = [$gtin, $mpn];
    }

    public function reset(): void
    {
        $this->staged = new \SplObjectStorage();
    }

    public function onPreSave(ResourceControllerEvent $event): void
    {
        $subject = $event->getSubject();
        $variants = $subject instanceof ProductInterface ? $subject->getVariants()->toArray() : [$subject];
        foreach ($variants as $variant) {
            if ($variant instanceof ProductVariantInterface && isset($this->staged[$variant])) {
                [$gtin, $mpn] = $this->staged[$variant];
                $this->write($variant, $gtin, $mpn);
                unset($this->staged[$variant]);
            }
        }
    }

    /** Persists (not flushed); empty values remove the row. GTINs are stored normalised. */
    public function write(ProductVariantInterface $variant, ?string $gtin, ?string $mpn): void
    {
        $gtin = null === $gtin || '' === trim($gtin) ? null : (Gtin::normalize($gtin) ?? trim($gtin));
        $mpn = null === $mpn || '' === trim($mpn) ? null : trim($mpn);
        $identifiers = $this->repository->findOneByVariant($variant);
        if (null === $gtin && null === $mpn) {
            if (null !== $identifiers) {
                $this->entityManager->remove($identifiers);
            }

            return;
        }
        if (null === $identifiers) {
            $identifiers = $this->factory->createNew();
            $identifiers->setVariant($variant);
            $this->entityManager->persist($identifiers);
        }
        $identifiers->setGtin($gtin);
        $identifiers->setMpn($mpn);
    }
}
