<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Tilttek\Badges\Criterion\Reading;

/**
 * A reading the test fills, by path.
 */
final readonly class Read implements Reading
{
    /**
     * @param array<string, int>                     $counts
     * @param array<string, int>                     $quantities
     * @param array<string, list<DateTimeImmutable>> $moments
     * @param array<string, int>                     $measures
     */
    public function __construct(
        private array $counts = [],
        private array $quantities = [],
        private array $moments = [],
        private array $measures = [],
    ) {
    }

    #[Override]
    public function count(string $path): int
    {
        return $this->counts[$path] ?? 0;
    }

    #[Override]
    public function quantity(string $path): int
    {
        return $this->quantities[$path] ?? 0;
    }

    #[Override]
    public function moments(string $path): array
    {
        return $this->moments[$path] ?? [];
    }

    #[Override]
    public function measure(string $name): int
    {
        return $this->measures[$name] ?? 0;
    }
}
