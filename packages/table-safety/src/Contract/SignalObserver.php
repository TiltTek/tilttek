<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Contract;

use Tilttek\TableSafety\Signal\Signal;

/**
 * Told of each signal once it is kept, to push it to the viewers of its table.
 */
interface SignalObserver
{
    public function raised(Signal $signal): void;

    public function acknowledged(Signal $signal): void;
}
