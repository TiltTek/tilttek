<?php

declare(strict_types=1);

namespace Tilttek\Party\Tests\Unit\Fixture;

use Tilttek\Party\Contract\PartyObserver;
use Tilttek\Party\PartyChanged;

/**
 * An observer that keeps what it was told, for the tests to read.
 */
final class Recorder implements PartyObserver
{
    /** @var list<PartyChanged> */
    public array $changes = [];

    public function changed(PartyChanged $event): void
    {
        $this->changes[] = $event;
    }

    /**
     * The changes told, each as `party change member subject by`, a dash for a missing value.
     *
     * @return list<string>
     */
    public function summary(): array
    {
        return array_map(static fn (PartyChanged $event): string => \sprintf('%s %s %s %s %s', $event->party, $event->change->value, $event->member ?? '-', $event->subject ?? '-', $event->by ?? '-'), $this->changes);
    }
}
