<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Leaderboards\Exception\LeaderboardsException;
use Tilttek\Leaderboards\League\Leagues;
use Tilttek\Leaderboards\League\Movement;
use Tilttek\Leaderboards\League\Settlement;

/**
 * Settles the weeks of the leagues due — every week over that a board with a league has not settled —, to run from a
 * scheduler soon after the week ends: `tilttek:leaderboards:settle`; or one board's week: `tilttek:leaderboards:settle
 * weekly-xp 2026-w45`.
 */
#[AsCommand('tilttek:leaderboards:settle', 'Moves the members of the leagues up and down at the end of their week.')]
final readonly class SettleCommand
{
    public function __construct(
        private Leagues $leagues,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The board whose week to settle; every board with a league when left out.')]
        ?string $board = null,
        #[Argument('The week of the board to settle, by its code.')]
        ?string $week = null,
    ): int {
        try {
            if (null === $board && null === $week) {
                $settled = $this->leagues->settleDue();
            } elseif (null !== $board && null !== $week) {
                $settled = array_filter([$this->leagues->settle($board, $week)]);
            } else {
                $io->error('Name a board and its week, or neither.');

                return Command::INVALID;
            }
        } catch (LeaderboardsException $leaderboardsException) {
            $io->error($leaderboardsException->getMessage());

            return Command::FAILURE;
        }

        $io->table(['Board', 'Week', 'Members', 'Up', 'Down'], array_map(
            static fn (Settlement $settlement): array => [$settlement->board, $settlement->week, \count($settlement->memberships), \count($settlement->moved(Movement::Promoted)), \count($settlement->moved(Movement::Relegated))],
            $settled,
        ));
        $io->success(\sprintf('%d week(s) settled.', \count($settled)));

        return Command::SUCCESS;
    }
}
