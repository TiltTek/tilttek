<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use Override;
use Tilttek\Activity\Activity;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Progress;

/**
 * A threshold on the quantities of the activities, summed: *have read 1,000 pages*, *have spent 500 in the store*.
 */
final readonly class Total implements Leaf
{
    public const int MAX = 1_000_000_000_000;

    public function __construct(
        public Scope $scope,
        public int $amount,
    ) {
        if ($amount < 1 || $amount > self::MAX) {
            throw InvalidBadge::because(\sprintf('A total is 1 to %d; this one is %d.', self::MAX, $amount));
        }
    }

    #[Override]
    public function accepts(Activity $activity): bool
    {
        return $this->scope->accepts($activity);
    }

    #[Override]
    public function patterns(): array
    {
        return $this->scope->patterns;
    }

    #[Override]
    public function leaves(string $path): array
    {
        return [$path => $this];
    }

    #[Override]
    public function progress(string $path, Reading $reading): Progress
    {
        return new Progress($reading->quantity($path), $this->amount);
    }
}
