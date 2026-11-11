<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Quests\Duel\Duels;

/**
 * Expires the duel offers left unanswered and the duels whose time is up with no winner, telling the application
 * of each: `tilttek:quests:expire`, run every few minutes by the application's scheduler.
 */
#[AsCommand('tilttek:quests:expire', 'Expires the duels whose time is up.')]
final readonly class ExpireCommand
{
    public function __construct(
        private Duels $duels,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('%d duel(s) expired.', \count($this->duels->expire())));

        return Command::SUCCESS;
    }
}
