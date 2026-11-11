<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Activity\Exception\UnknownProjection;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;

/**
 * Hands each projection, or those named, the entries of the journal it has not seen: the task a scheduler runs when
 * the projections are not kept up to date live, and the repair after a projection failed.
 */
#[AsCommand('tilttek:activity:catch-up', 'Hands the projections the activity they have not read')]
final readonly class CatchUpCommand
{
    public function __construct(
        private Projector $projector,
        private Projections $projections,
    ) {
    }

    /**
     * @param list<string> $projections
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The projections to catch up; all of them when none is named')]
        array $projections = [],
    ): int {
        try {
            $selected = [] === $projections ? $this->projections->all() : array_map($this->projections->get(...), $projections);
        } catch (UnknownProjection $unknownProjection) {
            $io->error($unknownProjection->getMessage());

            return Command::FAILURE;
        }

        foreach ($selected as $projection) {
            $io->writeln(\sprintf('%s: %d entries applied.', $projection->name(), $this->projector->catchUp($projection)));
        }

        return Command::SUCCESS;
    }
}
