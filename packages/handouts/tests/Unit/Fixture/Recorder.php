<?php

declare(strict_types=1);

namespace Tilttek\Handouts\Tests\Unit\Fixture;

use Tilttek\Handouts\Contract\HandoutObserver;
use Tilttek\Handouts\HandoutChanged;

/**
 * An observer that keeps what it was told, for the tests to read.
 */
final class Recorder implements HandoutObserver
{
    /** @var list<HandoutChanged> */
    public array $changes = [];

    public function changed(HandoutChanged $event): void
    {
        $this->changes[] = $event;
    }

    /**
     * The changes told, each as `handout table change player by`, a dash for a missing value.
     *
     * @return list<string>
     */
    public function summary(): array
    {
        return array_map(static fn (HandoutChanged $event): string => \sprintf('%s %s %s %s %s', $event->handout, $event->table, $event->change->value, $event->player ?? '-', $event->by ?? '-'), $this->changes);
    }

    public function clear(): void
    {
        $this->changes = [];
    }
}
