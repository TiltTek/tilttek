<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Signal;

use DateTimeImmutable;

/**
 * The answers to the last check-in of a table, counted without saying who gave which: how many are all good, unsure
 * or not okay since the game master asked. Without a check-in, nothing is counted.
 */
final readonly class CheckInTally
{
    private function __construct(
        public ?DateTimeImmutable $askedAt,
        public int $allGood,
        public int $unsure,
        public int $notOkay,
    ) {
    }

    /**
     * @param list<Signal> $signals the table's signals, in the order they were raised
     */
    public static function of(array $signals): self
    {
        $askedAt = null;
        $counts = [SignalKind::AllGood->value => 0, SignalKind::Unsure->value => 0, SignalKind::NotOkay->value => 0];

        foreach ($signals as $signal) {
            if (SignalKind::CheckIn === $signal->kind) {
                $askedAt = $signal->raisedAt;
                $counts = array_map(static fn (): int => 0, $counts);
            } elseif (null !== $askedAt && $signal->kind->isAnswer()) {
                ++$counts[$signal->kind->value];
            }
        }

        return new self($askedAt, $counts[SignalKind::AllGood->value], $counts[SignalKind::Unsure->value], $counts[SignalKind::NotOkay->value]);
    }

    public function answers(): int
    {
        return $this->allGood + $this->unsure + $this->notOkay;
    }

    /**
     * Whether someone asked to slow down or stop.
     */
    public function needsAttention(): bool
    {
        return $this->unsure + $this->notOkay > 0;
    }
}
