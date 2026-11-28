<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\InvalidCalendar;

/**
 * An era that numbers the years again: from its first year, which it calls 1, until the next era. An era without a
 * first year counts backward the years before the first dated era: the last year before that era is its year 1.
 */
final readonly class Era
{
    /**
     * @throws InvalidCalendar when a value is out of its bounds
     */
    public function __construct(public string $id, public string $name, public ?int $startYear)
    {
        Limits::identifier($id, 'An era identifier');
        Limits::label($name, 'An era name');
        if (null !== $startYear) {
            Limits::between($startYear, -Limits::YEAR, Limits::YEAR, 'The first year of an era');
        }
    }

    public function countsBackward(): bool
    {
        return null === $this->startYear;
    }
}
