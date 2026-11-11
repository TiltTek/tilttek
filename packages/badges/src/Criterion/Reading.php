<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use DateTimeImmutable;

/**
 * What one member's activity brought to the leaves of one badge, by path.
 */
interface Reading
{
    /**
     * How many activities the leaf accepted, cancelled ones left out.
     */
    public function count(string $path): int;

    /**
     * The quantities of those activities, summed.
     */
    public function quantity(string $path): int;

    /**
     * The moments of those activities.
     *
     * @return list<DateTimeImmutable>
     */
    public function moments(string $path): array;

    /**
     * The member's value of a measure the application provides, such as their balance of points.
     */
    public function measure(string $name): int;
}
