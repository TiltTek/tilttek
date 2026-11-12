<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;

/**
 * A member's score on a board in a period — 0 to 10^15 —, the values of the board's tie-breaks — each 0 to 10^15 —,
 * and the moment they reached it, which orders the members still equal when the first to reach a score ranks first.
 */
final readonly class Score
{
    public const int MAX = 1_000_000_000_000_000;

    /**
     * @param list<int> $tieBreaks
     */
    public function __construct(
        public string $board,
        public string $period,
        public ResourceReference $member,
        public int $value,
        public DateTimeImmutable $reachedAt,
        public array $tieBreaks = [],
    ) {
        foreach ([$value, ...$tieBreaks] as $number) {
            if ($number < 0 || $number > self::MAX) {
                throw InvalidLeaderboard::because(\sprintf('A score and its tie-breaks are 0 to %d; %d is not one.', self::MAX, $number));
            }
        }

        if (\count($tieBreaks) > Ranking::MAX_TIE_BREAKS) {
            throw InvalidLeaderboard::because(\sprintf('A score holds %d tie-breaks at most; %d were given.', Ranking::MAX_TIE_BREAKS, \count($tieBreaks)));
        }
    }

    /**
     * @return array{board: string, period: string, value: int, tie_breaks: list<int>, reached_at: string}
     */
    public function toArray(): array
    {
        return ['board' => $this->board, 'period' => $this->period, 'value' => $this->value, 'tie_breaks' => $this->tieBreaks, 'reached_at' => $this->reachedAt->format(DateTimeInterface::ATOM)];
    }
}
