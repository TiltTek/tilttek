<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

/**
 * What opening a box gives: the opening recorded, its surprise, and whether this call opened it — false when the member
 * had opened it before, which grants nothing again.
 */
final readonly class OpenedBox
{
    public function __construct(
        public Opening $opening,
        public Surprise $surprise,
        public bool $first,
    ) {
    }
}
