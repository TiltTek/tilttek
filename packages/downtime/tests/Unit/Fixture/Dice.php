<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Unit\Fixture;

use Override;
use Tilttek\Downtime\Contract\Roller;

/**
 * Rolls the totals it is given, in order, and records the expressions it was asked to roll.
 */
final class Dice implements Roller
{
    /** @var list<string> */
    public array $rolled = [];

    /** @var list<int> */
    private array $totals;

    public function __construct(int ...$totals)
    {
        $this->totals = array_values($totals);
    }

    #[Override]
    public function roll(string $dice): int
    {
        $this->rolled[] = $dice;

        return array_shift($this->totals) ?? 1;
    }
}
