<?php

declare(strict_types=1);

namespace Tilttek\Quests\Schedule;

use DateTimeImmutable;
use Tilttek\Quests\Window;

/**
 * One time a quest is open: its code — empty for a quest open once, the date it opens on for one that comes back —,
 * when it opens and when it closes; an open end has no bound. A quest is accomplished once per occurrence.
 */
final readonly class Occurrence
{
    public function __construct(
        public string $code = '',
        public ?DateTimeImmutable $opensAt = null,
        public ?DateTimeImmutable $closesAt = null,
    ) {
    }

    public function window(): Window
    {
        return new Window($this->opensAt, $this->closesAt);
    }
}
