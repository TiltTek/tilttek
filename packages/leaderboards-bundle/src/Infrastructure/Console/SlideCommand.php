<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Console;

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Leaderboards\LeaderboardProjection;

/**
 * Takes out of each board ranked over a sliding window the gestures that have grown too old, to run from a scheduler
 * soon after midnight: `tilttek:leaderboards:slide`.
 */
#[AsCommand('tilttek:leaderboards:slide', 'Takes the gestures older than their window out of the boards ranked over a sliding window.')]
final readonly class SlideCommand
{
    public function __construct(
        private LeaderboardProjection $projection,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('%d contribution(s) left their window.', $this->projection->slide($this->clock->now())));

        return Command::SUCCESS;
    }
}
