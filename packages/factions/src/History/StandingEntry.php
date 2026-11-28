<?php

declare(strict_types=1);

namespace Tilttek\Factions\History;

use DateTimeImmutable;
use Override;
use Tilttek\Factions\Reason;
use Tilttek\Factions\Subject;

/**
 * Where a subject stands with a faction changed, from one standing to another.
 */
final readonly class StandingEntry implements Entry
{
    /**
     * @param int $before the standing before, the start of the scale when none was set
     * @param int $after  the standing after
     */
    public function __construct(
        public string $campaign,
        public string $faction,
        public Subject $subject,
        public int $before,
        public int $after,
        public Reason $reason,
        public string $by,
        public DateTimeImmutable $at,
    ) {
    }

    #[Override]
    public function concerns(string $faction): bool
    {
        return $faction === $this->faction;
    }
}
