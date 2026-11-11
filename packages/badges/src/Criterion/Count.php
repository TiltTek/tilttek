<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use Override;
use Tilttek\Activity\Activity;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Progress;

/**
 * A number of activities: *have commented on 10 news items*. A first time is a count of one: *have published a first
 * sheet*.
 */
final readonly class Count implements Leaf
{
    public const int MAX = 1_000_000_000;

    public function __construct(
        public Scope $scope,
        public int $times = 1,
    ) {
        if ($times < 1 || $times > self::MAX) {
            throw InvalidBadge::because(\sprintf('A count is 1 to %d activities; this one is %d.', self::MAX, $times));
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
        return new Progress($reading->count($path), $this->times);
    }
}
