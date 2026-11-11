<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Activity\Projection\Projection;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;

/**
 * Lists the projections and how many entries of the journal each has yet to see.
 */
#[AsCommand('tilttek:activity:status', 'Lists the projections and what they have yet to read')]
final readonly class StatusCommand
{
    public function __construct(
        private Projector $projector,
        private Projections $projections,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $rows = array_map(fn (Projection $projection): array => [$projection->name(), $this->projector->lag($projection)], $this->projections->all());

        if ([] === $rows) {
            $io->writeln('No projection is registered.');

            return Command::SUCCESS;
        }

        $io->table(['Projection', 'Entries behind'], $rows);

        return Command::SUCCESS;
    }
}
