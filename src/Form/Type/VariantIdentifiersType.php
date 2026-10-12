<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Gtin;

/** GTIN and MPN of a variant (unmapped field of the variant form). */
final class VariantIdentifiersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('gtin', TextType::class, [
                'label' => 'tmonier_agentic_feeds.form.gtin',
                'help' => 'tmonier_agentic_feeds.form.gtin_help',
                'required' => false,
                'constraints' => [new Callback(callback: static function (mixed $value, ExecutionContextInterface $context): void {
                    if (\is_string($value) && '' !== trim($value) && null === Gtin::normalize($value)) {
                        $context->buildViolation('tmonier_agentic_feeds.variant.gtin.invalid')->addViolation();
                    }
                }, groups: ['sylius'])],
            ])
            ->add('mpn', TextType::class, [
                'label' => 'tmonier_agentic_feeds.form.mpn',
                'required' => false,
                'constraints' => [new Length(max: 70, groups: ['sylius'])],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['label' => false]);
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_agentic_feeds_variant_identifiers';
    }
}
