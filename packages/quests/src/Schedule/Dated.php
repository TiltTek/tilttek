<?php

declare(strict_types=1);

namespace Tilttek\Quests\Schedule;

use DateTimeImmutable;
use Override;
use Tilttek\Quests\Exception\InvalidQuest;

/**
 * A quest open once, from its opening, included, to its closing, excluded — a quest of a season or of an event —;
 * either end may be left open, not both.
 */
final readonly class Dated implements Schedule
{
    public function __construct(
        public ?DateTimeImmutable $opensAt,
        public ?DateTimeImmutable $closesAt,
    ) {
        if (!$opensAt instanceof DateTimeImmutable && !$closesAt instanceof DateTimeImmutable) {
            throw InvalidQuest::because('A dated quest opens or closes at a date; one that does neither is permanent.');
        }

        if ($opensAt instanceof DateTimeImmutable && $closesAt instanceof DateTimeImmutable && $closesAt <= $opensAt) {
            throw InvalidQuest::because('A dated quest closes after it opens.');
        }
    }

    #[Override]
    public function occurrenceAt(DateTimeImmutable $moment): ?Occurrence
    {
        $occurrence = new Occurrence('', $this->opensAt, $this->closesAt);

        return $occurrence->window()->contains($moment) ? $occurrence : null;
    }
}
