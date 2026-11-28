<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

use DateTimeImmutable;

/**
 * One line of an inventory's log: what changed, the item's key, how many units the change concerned, where they came
 * from and went — places written as `Place::toString()` gives them —, the reference that acted, the reason given, and
 * the moment.
 *
 * The reason is 1 to 200 characters of UTF-8 without control characters nor surrounding spaces: `sold in Waterdeep`,
 * `drunk during the fight`. A carrier erased from the application leaves `carrier` without its reference in the places.
 */
final readonly class LogEntry
{
    public const int MAX_REASON_LENGTH = 200;

    public function __construct(
        public string $inventory,
        public Change $change,
        public ?string $item,
        public ?int $quantity,
        public ?string $from,
        public ?string $to,
        public ?string $by,
        public ?string $reason,
        public DateTimeImmutable $at,
    ) {
        if (null !== $reason) {
            Text::name($reason, 'A reason', self::MAX_REASON_LENGTH);
        }
    }

    /**
     * The entry without the reference erased: the acting reference dropped, a place on that carrier written `carrier`.
     */
    public function without(string $reference): self
    {
        $place = 'carrier:'.$reference;

        return new self(
            $this->inventory,
            $this->change,
            $this->item,
            $this->quantity,
            $this->from === $place ? 'carrier' : $this->from,
            $this->to === $place ? 'carrier' : $this->to,
            $this->by === $reference ? null : $this->by,
            $this->reason,
            $this->at,
        );
    }

    /**
     * Whether the entry names the reference, as the acting one or as a carrier.
     */
    public function names(string $reference): bool
    {
        $place = 'carrier:'.$reference;

        return $this->by === $reference || $this->from === $place || $this->to === $place;
    }
}
