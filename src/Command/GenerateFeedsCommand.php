<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedGenerator;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\ProductFeedRepositoryInterface;

#[AsCommand(name: 'tmonier:feeds:generate', description: 'Generate product feeds (all enabled feeds, or the given codes)')]
final class GenerateFeedsCommand extends Command
{
    public function __construct(
        private readonly ProductFeedRepositoryInterface $feedRepository,
        private readonly FeedGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('codes', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Feed codes (default: every enabled feed)')
            ->addOption('stale', null, InputOption::VALUE_NONE, 'Only the feeds whose catalog data changed since their last generation (and never generated ones)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate only: no file written, reports unchanged')
            ->addOption('fail-on-errors', null, InputOption::VALUE_NONE, 'Exit with code 1 when items were left out because of errors (CI)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<string> $codes */
        $codes = $input->getArgument('codes');
        $feeds = [];
        foreach ($codes as $code) {
            $feed = $this->feedRepository->findOneByCode($code);
            if (!$feed instanceof \Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface) {
                $io->error(sprintf('Unknown feed "%s".', $code));

                return Command::FAILURE;
            }
            $feeds[] = $feed;
        }
        if ([] === $codes) {
            $feeds = $this->feedRepository->findEnabled();
        }
        if ((bool) $input->getOption('stale')) {
            $feeds = array_values(array_filter($feeds, static fn (ProductFeedInterface $feed): bool => $feed->getStaleSince() instanceof \DateTimeInterface || !$feed->getLastGeneratedAt() instanceof \DateTimeInterface));
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $rows = [];
        $failed = false;
        $errors = false;
        foreach ($feeds as $feed) {
            $result = $this->generator->generate($feed, $dryRun);
            $failed = $failed || $result->failed();
            $errors = $errors || $result->hasErrors();
            $rows[] = [$result->feedCode, $result->status(), $result->items(), $result->errorCount(), $result->warningCount(), $result->durationMs() . ' ms', (string) $result->failure()];
            if ($output->isVerbose()) {
                foreach ($result->issues() as [$issue, $itemId, $productCode]) {
                    $io->writeln(sprintf('  [%s] %s %s%s %s', $issue->severity, (string) $itemId, $issue->code, null === $issue->field ? '' : ' (' . $issue->field . ')', json_encode($issue->parameters, \JSON_THROW_ON_ERROR)));
                }
            }
        }
        $io->table(['Feed', 'Status', 'Items', 'Errors', 'Warnings', 'Duration', 'Failure'], $rows);
        if ([] === $feeds) {
            $io->note('No feed to generate.');
        }

        return $failed || ((bool) $input->getOption('fail-on-errors') && $errors) ? Command::FAILURE : Command::SUCCESS;
    }
}
