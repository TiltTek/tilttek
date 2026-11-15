<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Leaderboards\Exception\LeaderboardsException;
use Tilttek\Leaderboards\Leaderboards;
use Tilttek\Leaderboards\Row;

/**
 * A board in a period as the public sees it — the members who chose not to appear without a name —:
 * `tilttek:leaderboards:show games-month 2026-10 --limit=20`.
 */
#[AsCommand('tilttek:leaderboards:show', 'Shows a board in a period as the public sees it.')]
final readonly class ShowCommand
{
    public function __construct(
        private Leaderboards $leaderboards,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The board.')]
        string $board,
        #[Argument('The period, by its code; the one under way when left out.')]
        ?string $period = null,
        #[Option('How many lines to show, 1 to 100.')]
        int $limit = Leaderboards::DEFAULT_LIMIT,
    ): int {
        try {
            $standing = $this->leaderboards->standing($board, null, $period, $limit);
        } catch (LeaderboardsException $leaderboardsException) {
            $io->error($leaderboardsException->getMessage());

            return Command::FAILURE;
        }

        $io->title(\sprintf('%s — %s — %d ranked', $standing->board->name, $standing->period->code, $standing->participants));
        $io->table(['Rank', 'Member', 'Score'], array_map(
            static fn (Row $row): array => [$row->rank, $row->shownAs() ?? '—', $row->value],
            $standing->rows,
        ));

        return Command::SUCCESS;
    }
}
