<?php

declare(strict_types=1);

namespace Tilttek\RpgCalendarEvents\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\RpgCalendarEvents\Catalog;

/**
 * What a page does: build the shipped catalog, then ask for the dates of a day and of a year.
 */
final readonly class CatalogBench
{
    private Catalog $catalog;

    private DateTimeZone $zone;

    public function __construct()
    {
        $this->zone = new DateTimeZone('America/Toronto');
        $this->catalog = Catalog::shipped($this->zone);
    }

    /**
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchBuildTheShippedCatalog(): void
    {
        Catalog::shipped($this->zone);
    }

    /**
     * @Revs(200)
     *
     * @Iterations(3)
     */
    public function benchTheDatesOfADay(): void
    {
        $this->catalog->on(new DateTimeImmutable('2026-05-25', $this->zone));
    }

    /**
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchTheDatesOfAYear(): void
    {
        $this->catalog->year(2026);
    }
}
