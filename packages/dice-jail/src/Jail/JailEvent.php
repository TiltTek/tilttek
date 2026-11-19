<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Jail;

use DateTimeImmutable;

/**
 * One line of a die's history: jailed or released, by whom, when, why, and — for a jailing — until when and on what
 * statistics.
 */
final readonly class JailEvent
{
    /**
     * @param int                $position the event's place in the die's history, from 1
     * @param ?DateTimeImmutable $until    when the sentence ends by itself; null for a release, or for a sentence that
     *                                     lasts until the die is released
     */
    public function __construct(
        public EventKind $kind,
        public string $keeper,
        public string $die,
        public int $position,
        public DateTimeImmutable $at,
        public string $reason = '',
        public ?DateTimeImmutable $until = null,
        public ?Evidence $evidence = null,
    ) {
    }
}
