<?php

declare(strict_types=1);

namespace Tilttek\Collections\Daily;

use DateTimeImmutable;
use Override;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Names;

/**
 * An item put forward on a fixed date: every year on a month and a day (`10-31`), or once on a full date
 * (`2026-12-25`).
 */
final readonly class FixedDate implements DateRule
{
    public function __construct(
        public string $date,
        public string $item,
    ) {
        Names::check($item, 'item');

        if (!Days::isDay($date) && !Days::isDay('2000-'.$date)) {
            throw InvalidCollection::because(\sprintf('"%s" is not a date: a month and a day, such as "10-31", or a full date, such as "2026-12-25".', $date));
        }
    }

    #[Override]
    public function itemOn(DateTimeImmutable $day): ?string
    {
        return \in_array($this->date, [$day->format('Y-m-d'), $day->format('m-d')], true) ? $this->item : null;
    }
}
