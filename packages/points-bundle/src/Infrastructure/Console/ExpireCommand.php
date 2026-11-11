<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Points\Points;

/**
 * Expires the credits whose lifetime has ended, for every member and in every currency with a lifetime: the task a
 * scheduler runs, once a day for instance. A spending and a transfer expire the payer's credits first anyway.
 */
#[AsCommand('tilttek:points:expire', 'Expires the credits of points whose lifetime has ended')]
final readonly class ExpireCommand
{
    public function __construct(
        private Points $points,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->writeln(\sprintf('%d points expired.', $this->points->expireAll()));

        return Command::SUCCESS;
    }
}
