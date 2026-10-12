<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Form\Type;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;

final class ProductFeedType extends AbstractResourceType
{
    /**
     * @param list<string> $validationGroups
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     * @param RepositoryInterface<CurrencyInterface> $currencyRepository
     */
    public function __construct(
        string $dataClass,
        array $validationGroups,
        private readonly FormatRegistry $formats,
        private readonly RepositoryInterface $localeRepository,
        private readonly RepositoryInterface $currencyRepository,
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $formats = [];
        foreach ($this->formats->all() as $code => $format) {
            $formats[$format->getLabel()] = $code;
        }
        $codes = static fn (iterable $resources): array => array_combine(
            $list = array_map(static fn (LocaleInterface|CurrencyInterface $resource): string => (string) $resource->getCode(), [...$resources]),
            $list,
        );

        $builder
            ->addEventSubscriber(new AddCodeFormSubscriber(null, ['help' => 'tmonier_agentic_feeds.form.code_help']))
            ->add('name', TextType::class, ['label' => 'sylius.ui.name'])
            ->add('channel', ChannelChoiceType::class, ['label' => 'sylius.ui.channel', 'placeholder' => false])
            ->add('localeCode', ChoiceType::class, [
                'label' => 'sylius.ui.locale',
                'choices' => $codes($this->localeRepository->findAll()),
                'help' => 'tmonier_agentic_feeds.form.locale_help',
            ])
            ->add('currencyCode', ChoiceType::class, [
                'label' => 'sylius.ui.currency',
                'choices' => $codes($this->currencyRepository->findAll()),
                'help' => 'tmonier_agentic_feeds.form.currency_help',
            ])
            ->add('countryCode', CountryType::class, [
                'label' => 'tmonier_agentic_feeds.form.country',
                'help' => 'tmonier_agentic_feeds.form.country_help',
                'required' => false,
                'placeholder' => 'tmonier_agentic_feeds.form.no_country',
            ])
            ->add('format', ChoiceType::class, [
                'label' => 'tmonier_agentic_feeds.form.format',
                'choices' => $formats,
                'help' => 'tmonier_agentic_feeds.form.format_help',
            ])
            ->add('accessToken', TextType::class, [
                'label' => 'tmonier_agentic_feeds.form.access_token',
                'help' => 'tmonier_agentic_feeds.form.access_token_help',
                'required' => false,
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add('enabled', CheckboxType::class, ['label' => 'sylius.ui.enabled', 'required' => false])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_agentic_feeds_product_feed';
    }
}
