<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit\Fixture;

use Override;
use Tilttek\TableSafety\Contract\SignalObserver;
use Tilttek\TableSafety\Signal\Signal;

/**
 * Keeps what it is told, in order.
 */
final class RecordingObserver implements SignalObserver
{
    /** @var list<array{string, Signal}> */
    public array $told = [];

    #[Override]
    public function raised(Signal $signal): void
    {
        $this->told[] = ['raised', $signal];
    }

    #[Override]
    public function acknowledged(Signal $signal): void
    {
        $this->told[] = ['acknowledged', $signal];
    }
}
