<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Leaderboards\Exception\LeaderboardsException;
use Tilttek\Leaderboards\Leaderboards;
use Tilttek\Leaderboards\Snapshot;

/**
 * Takes the snapshots due — the period each board ended last, when it has none yet —, to run from a scheduler soon
 * after midnight: `tilttek:leaderboards:snapshot`; or the snapshot of one board and period: `tilttek:leaderboards:
 * snapshot games-month 2026-10`.
 */
#[AsCommand('tilttek:leaderboards:snapshot', 'Freezes the boards whose period ended, or one board for one period.')]
final readonly class SnapshotCommand
{
    public function __construct(
        private Leaderboards $leaderboards,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The board to freeze; every board whose period ended when left out.')]
        ?string $board = null,
        #[Argument('The period of the board to freeze, by its code.')]
        ?string $period = null,
    ): int {
        try {
            if (null === $board && null === $period) {
                $taken = $this->leaderboards->snapshotDue();
            } elseif (null !== $board && null !== $period) {
                $taken = [$this->leaderboards->snapshot($board, $period)];
            } else {
                $io->error('Name a board and its period, or neither.');

                return Command::INVALID;
            }
        } catch (LeaderboardsException $leaderboardsException) {
            $io->error($leaderboardsException->getMessage());

            return Command::FAILURE;
        }

        $io->table(['Board', 'Period', 'Ranked', 'Kept'], array_map(
            static fn (Snapshot $snapshot): array => [$snapshot->board, $snapshot->period, $snapshot->participants, \count($snapshot->placings)],
            $taken,
        ));
        $io->success(\sprintf('%d snapshot(s).', \count($taken)));

        return Command::SUCCESS;
    }
}
