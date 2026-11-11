<?php

declare(strict_types=1);

namespace Tilttek\Collections\Daily;

use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Names;

/**
 * The item of a day, and why.
 */
final readonly class DailyPick
{
    public function __construct(
        public string $day,
        public string $item,
        public PickOrigin $origin,
    ) {
        if (!Days::isDay($day)) {
            throw InvalidCollection::because(\sprintf('"%s" is not a day written Y-m-d.', $day));
        }

        Names::check($item, 'item');
    }
}
