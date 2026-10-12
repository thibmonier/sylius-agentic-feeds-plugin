<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Form\Extension;

use Sylius\Bundle\ProductBundle\Form\Type\ProductVariantType;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Tmonier\SyliusAgenticFeedsPlugin\Form\Type\VariantIdentifiersType;
use Tmonier\SyliusAgenticFeedsPlugin\Form\VariantIdentifiersManager;

/** GTIN / MPN on the variant form (and the product form of simple products, which embeds it). */
final class ProductVariantTypeExtension extends AbstractTypeExtension
{
    public const FIELD = 'tmonierAgenticFeeds';

    public function __construct(private readonly VariantIdentifiersManager $manager)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                $variant = $event->getData();
                $event->getForm()->add(self::FIELD, VariantIdentifiersType::class, [
                    'mapped' => false,
                    'data' => $variant instanceof ProductVariantInterface ? $this->manager->load($variant) : ['gtin' => null, 'mpn' => null],
                ]);
            })
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
                $variant = $event->getData();
                $form = $event->getForm();
                if (!$variant instanceof ProductVariantInterface || !$form->has(self::FIELD)) {
                    return;
                }
                $data = $form->get(self::FIELD)->getData();
                if (\is_array($data)) {
                    $this->manager->stage($variant, \is_string($data['gtin'] ?? null) ? $data['gtin'] : null, \is_string($data['mpn'] ?? null) ? $data['mpn'] : null);
                }
            }, -100)
        ;
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductVariantType::class];
    }
}
