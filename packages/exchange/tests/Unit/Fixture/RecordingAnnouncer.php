<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Fixture;

use Override;
use ReflectionClass;
use Tilttek\Exchange\Announcer;
use Tilttek\Exchange\Event\ExchangeEvent;

/**
 * Keeps what it is told, in order.
 */
final class RecordingAnnouncer implements Announcer
{
    /**
     * @var list<ExchangeEvent>
     */
    public array $events = [];

    #[Override]
    public function announce(ExchangeEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * The short class names of the events told, in order, and forgets them.
     *
     * @return list<string>
     */
    public function told(): array
    {
        $names = array_map(static fn (ExchangeEvent $event): string => new ReflectionClass($event)->getShortName(), $this->events);
        $this->events = [];

        return $names;
    }
}
