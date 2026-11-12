<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Period;

use DateTimeImmutable;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Names;

/**
 * One period of a board: its code — `2026-11-07`, `2026-w45`, `2026-11`, `2026-q4`, `2026`, a season's code, or
 * `ever` or `window` —, its start, included, and its end, excluded. The period of a board ranked since ever, or over a
 * sliding window, has neither.
 */
final readonly class Period
{
    public const string EVER = 'ever';

    public const string WINDOW = 'window';

    public function __construct(
        public string $code,
        public ?DateTimeImmutable $start = null,
        public ?DateTimeImmutable $end = null,
    ) {
        Names::check($code, 'period');

        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
            if ($start instanceof DateTimeImmutable || $end instanceof DateTimeImmutable) {
                throw InvalidLeaderboard::because(\sprintf('The period "%s" has both a start and an end, or neither.', $code));
            }

            return;
        }

        if ($end <= $start) {
            throw InvalidLeaderboard::because(\sprintf('The period "%s" ends after it starts.', $code));
        }
    }

    public static function ever(): self
    {
        return new self(self::EVER);
    }

    /**
     * The one period of a board ranked over a sliding window: the gestures older than the window leave it as days go.
     */
    public static function window(): self
    {
        return new self(self::WINDOW);
    }

    public function holds(DateTimeImmutable $moment): bool
    {
        return (!$this->start instanceof DateTimeImmutable || $moment >= $this->start)
            && (!$this->end instanceof DateTimeImmutable || $moment < $this->end);
    }

    /**
     * Whether the period has ended at that moment; one without an end never does.
     */
    public function isOverAt(DateTimeImmutable $moment): bool
    {
        return $this->end instanceof DateTimeImmutable && $this->end <= $moment;
    }
}
