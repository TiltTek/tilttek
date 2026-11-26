<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

use Tilttek\Conditions\Exception\InvalidCondition;

/**
 * How long a condition lasts, and on an applied condition what is left of it.
 *
 * - In rounds: the amount counts down at each start or end — the boundary — of the anchor's turns, or of the rounds, and
 *   the condition ends when it reaches zero. A condition of 1 round to the end of the target's turn, applied during that
 *   turn, ends at its end: the boundaries counted are those after it is applied.
 * - In game time: seconds of the fiction, counted down when the table advances the time.
 * - In real time: milliseconds, the condition ending at the moment it was applied plus the amount.
 * - Until removed: a mutation, a lost limb.
 */
final readonly class Duration
{
    /** The most rounds of a duration. */
    public const int MAX_ROUNDS = 10_000;

    /** The most seconds of a duration in game time: a hundred years of 366 days. */
    public const int MAX_GAME_SECONDS = 3_162_240_000;

    /** The fewest milliseconds of a duration in real time: one second. */
    public const int MIN_REAL_MILLISECONDS = 1_000;

    /** The most milliseconds of a duration in real time: 366 days. */
    public const int MAX_REAL_MILLISECONDS = 31_622_400_000;

    /**
     * @param int $amount rounds, seconds or milliseconds after its kind, 0 until removed
     *
     * @throws InvalidCondition when the amount is out of the bounds of the kind
     */
    public function __construct(
        public DurationKind $kind,
        public int $amount = 0,
        public Boundary $boundary = Boundary::End,
        public Anchor $anchor = Anchor::Target,
    ) {
        [$min, $max] = match ($kind) {
            DurationKind::Rounds => [1, self::MAX_ROUNDS],
            DurationKind::GameTime => [1, self::MAX_GAME_SECONDS],
            DurationKind::RealTime => [self::MIN_REAL_MILLISECONDS, self::MAX_REAL_MILLISECONDS],
            DurationKind::UntilRemoved => [0, 0],
        };
        if ($amount < $min || $amount > $max) {
            throw InvalidCondition::because(\sprintf('A duration of kind %s has an amount of %d to %d.', $kind->value, $min, $max));
        }
    }

    /**
     * So many rounds, counted at the boundary of the anchor's turns.
     *
     * @throws InvalidCondition when the rounds are out of 1 to 10,000
     */
    public static function rounds(int $rounds, Boundary $boundary = Boundary::End, Anchor $anchor = Anchor::Target): self
    {
        return new self(DurationKind::Rounds, $rounds, $boundary, $anchor);
    }

    /**
     * So many seconds of the fiction.
     *
     * @throws InvalidCondition when the seconds are out of 1 to a hundred years
     */
    public static function gameTime(int $seconds): self
    {
        return new self(DurationKind::GameTime, $seconds);
    }

    /**
     * So many milliseconds of real time.
     *
     * @throws InvalidCondition when the milliseconds are out of one second to 366 days
     */
    public static function realTime(int $milliseconds): self
    {
        return new self(DurationKind::RealTime, $milliseconds);
    }

    public static function untilRemoved(): self
    {
        return new self(DurationKind::UntilRemoved);
    }

    /**
     * The duration with less left: rounds or seconds.
     */
    public function withAmount(int $amount): self
    {
        return new self($this->kind, $amount, $this->boundary, $this->anchor);
    }

    /**
     * Whether it counts down at a boundary of a turn, of the character whose turn it is or of the round when null, for
     * a condition on a target from a source.
     */
    public function countsAt(Boundary $boundary, ?string $turnOf, string $target, ?string $source): bool
    {
        if (DurationKind::Rounds !== $this->kind || $boundary !== $this->boundary) {
            return false;
        }

        return match ($this->anchor) {
            Anchor::Round => null === $turnOf,
            Anchor::Target => $turnOf === $target,
            Anchor::Source => null !== $turnOf && $turnOf === $source,
        };
    }

    /**
     * @return array{kind: string, amount: int, boundary: string, anchor: string}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'amount' => $this->amount, 'boundary' => $this->boundary->value, 'anchor' => $this->anchor->value];
    }
}
