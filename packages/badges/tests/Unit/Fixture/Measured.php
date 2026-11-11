<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Measures;

/**
 * Measures the test sets, by measure and member.
 */
final class Measured implements Measures
{
    /**
     * @var array<string, int>
     */
    private array $values = [];

    public function set(string $measure, string $member, int $value): void
    {
        $this->values[$measure.' '.$member] = $value;
    }

    #[Override]
    public function value(string $measure, ResourceReference $member): int
    {
        return $this->values[$measure.' '.$member->toString()] ?? 0;
    }
}
