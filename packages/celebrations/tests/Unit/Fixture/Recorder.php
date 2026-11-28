<?php

declare(strict_types=1);

namespace Tilttek\Celebrations\Tests\Unit\Fixture;

use Tilttek\Celebrations\CelebrationChanged;
use Tilttek\Celebrations\Contract\CelebrationObserver;

/**
 * An observer that keeps what it was told, for the tests to read.
 */
final class Recorder implements CelebrationObserver
{
    /** @var list<CelebrationChanged> */
    public array $changes = [];

    public function changed(CelebrationChanged $event): void
    {
        $this->changes[] = $event;
    }

    /**
     * The changes told, each as `member change source occurrence`, a dash for a missing value.
     *
     * @return list<string>
     */
    public function summary(): array
    {
        return array_map(static fn (CelebrationChanged $event): string => \sprintf('%s %s %s %s', $event->member, $event->change->value, $event->source ?? '-', $event->occurrence ?? '-'), $this->changes);
    }
}
