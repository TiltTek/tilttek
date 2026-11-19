<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Store;

use Override;
use Tilttek\DiceJail\Contract\JailStore;
use Tilttek\DiceJail\Exception\JailConflict;
use Tilttek\DiceJail\Jail\JailEvent;

/**
 * The histories in memory, for tests and for a table that lives in one process; `reset()` empties it between the
 * requests of a worker.
 */
final class InMemoryJailStore implements JailStore
{
    /** @var array<string, array<string, list<JailEvent>>> by keeper, then die */
    private array $events = [];

    #[Override]
    public function append(JailEvent $event): void
    {
        $expected = \count($this->events($event->keeper, $event->die)) + 1;

        if ($event->position !== $expected) {
            throw JailConflict::at($event->die, $expected, $event->position);
        }

        $this->events[$event->keeper][$event->die][] = $event;
    }

    #[Override]
    public function events(string $keeper, string $die): array
    {
        return $this->events[$keeper][$die] ?? [];
    }

    #[Override]
    public function dice(string $keeper): array
    {
        return array_map(strval(...), array_keys($this->events[$keeper] ?? []));
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
