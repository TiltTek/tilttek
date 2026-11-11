<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Progression\Progression;

/**
 * Finds the streaks broken since the members' last gestures and tells each, once: `tilttek:progression:sweep`, from
 * the scheduler, once an hour for members across timezones.
 */
#[AsCommand('tilttek:progression:sweep', "Finds the streaks broken since the members' last gestures.")]
final readonly class SweepCommand
{
    public function __construct(
        private Progression $progression,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->writeln(\sprintf('%d streaks broke.', $this->progression->sweep()));

        return Command::SUCCESS;
    }
}
