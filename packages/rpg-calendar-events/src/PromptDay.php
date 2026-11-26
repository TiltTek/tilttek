<?php

declare(strict_types=1);

namespace Tilttek\RpgCalendarEvents;

/**
 * A day of a themed period of daily prompts: the period computed that year, and the index of the day in it, from 1
 * to its length. The prompt of that index is the application's: the package ships none.
 */
final readonly class PromptDay
{
    public function __construct(
        public Occurrence $occurrence,
        public int $index,
    ) {
    }

    /**
     * How many days, and prompts, the period has that year.
     */
    public function length(): int
    {
        return $this->occurrence->span->days();
    }

    public function isFirst(): bool
    {
        return 1 === $this->index;
    }

    public function isLast(): bool
    {
        return $this->index === $this->length();
    }
}
