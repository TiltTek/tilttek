<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Fixture;

use Override;
use Tilttek\Collections\Pack\SeedSource;

/**
 * Seeds the test gives, in turn: `seed-0`, `seed-1`… padded to 16 bytes, unless the test names them.
 */
final class FixedSeeds implements SeedSource
{
    private int $given = 0;

    /**
     * @param list<string> $seeds
     */
    public function __construct(
        private readonly array $seeds = [],
    ) {
    }

    #[Override]
    public function seed(): string
    {
        $seed = $this->seeds[$this->given] ?? str_pad('seed-'.$this->given, 16, '.');
        ++$this->given;

        return $seed;
    }
}
