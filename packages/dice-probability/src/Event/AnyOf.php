<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Event;

use Override;

/**
 * At least one of the events: a 1 or a double.
 */
final readonly class AnyOf implements Event
{
    /**
     * @var non-empty-list<Event>
     */
    public array $events;

    public function __construct(Event $event, Event ...$events)
    {
        // Named arguments land in the variadic under their names: the events are kept as a list.
        $this->events = [$event, ...array_values($events)];
    }

    #[Override]
    public function matches(Faces $faces): bool
    {
        return array_any($this->events, static fn (Event $event): bool => $event->matches($faces));
    }
}
