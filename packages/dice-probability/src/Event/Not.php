<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Event;

use Override;

/**
 * The event does not happen.
 */
final readonly class Not implements Event
{
    public function __construct(
        public Event $event,
    ) {
    }

    #[Override]
    public function matches(Faces $faces): bool
    {
        return !$this->event->matches($faces);
    }
}
