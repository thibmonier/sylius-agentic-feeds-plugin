<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Command;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tmonier\SyliusAgenticFeedsPlugin\Form\VariantIdentifiersManager;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Gtin;

/** CSV "variant_code;gtin;mpn" (or comma), header row; all or nothing. */
#[AsCommand(name: 'tmonier:feeds:import-identifiers', description: 'Import the GTIN and MPN of variants from a CSV file (variant_code;gtin;mpn)')]
final class ImportIdentifiersCommand extends Command
{
    /** @param RepositoryInterface<ProductVariantInterface> $variantRepository */
    public function __construct(
        private readonly RepositoryInterface $variantRepository,
        private readonly VariantIdentifiersManager $manager,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'CSV file with the columns variant_code, gtin, mpn')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Check the file without writing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        $contents = \is_string($file) && is_file($file) ? file_get_contents($file) : false;
        if (false === $contents) {
            $io->error('Cannot read the file.');

            return Command::FAILURE;
        }
        $rows = preg_split('/\R/', (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents)) ?: [];
        $header = (string) array_shift($rows);
        $delimiter = substr_count($header, ';') >= substr_count($header, ',') ? ';' : ',';
        $columns = array_map(static fn (?string $column): string => strtolower(trim((string) $column)), str_getcsv($header, $delimiter, '"', ''));
        if (!\in_array('variant_code', $columns, true)) {
            $io->error('Missing column variant_code.');

            return Command::FAILURE;
        }

        $errors = [];
        $lines = [];
        $seen = [];
        foreach ($rows as $offset => $row) {
            if ('' === trim($row)) {
                continue;
            }
            $values = array_combine($columns, array_pad(str_getcsv($row, $delimiter, '"', ''), \count($columns), ''));
            $number = $offset + 2;
            $variant = $this->variantRepository->findOneBy(['code' => trim((string) $values['variant_code'])]);
            $gtin = trim((string) ($values['gtin'] ?? ''));
            $mpn = trim((string) ($values['mpn'] ?? ''));
            if (!$variant instanceof ProductVariantInterface) {
                $errors[$number] = sprintf('Unknown variant "%s".', (string) $values['variant_code']);

                continue;
            }
            $normalized = '' === $gtin ? null : Gtin::normalize($gtin);
            if ('' !== $gtin && null === $normalized) {
                $errors[$number] = sprintf('Invalid GTIN "%s".', $gtin);
            } elseif (null !== $normalized && isset($seen[$normalized])) {
                $errors[$number] = sprintf('GTIN "%s" already on line %d.', $gtin, $seen[$normalized]);
            } else {
                if (null !== $normalized) {
                    $seen[$normalized] = $number;
                }
                $lines[] = [$variant, $gtin, $mpn];
            }
        }
        foreach ($errors as $number => $error) {
            $io->writeln(sprintf('<comment>Line %d:</comment> %s', $number, $error));
        }
        if ([] !== $errors) {
            $io->error(sprintf('%d lines rejected: nothing was written.', \count($errors)));

            return Command::FAILURE;
        }
        if (!(bool) $input->getOption('dry-run')) {
            foreach ($lines as [$variant, $gtin, $mpn]) {
                $this->manager->write($variant, $gtin, $mpn);
            }
            $this->entityManager->flush();
        }
        $io->success(sprintf('%s%d variants imported.', (bool) $input->getOption('dry-run') ? '[dry run] ' : '', \count($lines)));

        return Command::SUCCESS;
    }
}
