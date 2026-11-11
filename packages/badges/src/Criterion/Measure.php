<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use Override;
use Tilttek\Activity\Activity;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Progress;

/**
 * A threshold on a value the application measures — *have 1,000 points* —, read through its `Measures` when one of
 * the scope's activities happens. The value is the current one, not the one at the activity's moment: a rebuild reads
 * it again as it is then.
 */
final readonly class Measure implements Leaf
{
    public function __construct(
        public Scope $scope,
        public string $name,
        public int $threshold,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name)) {
            throw InvalidBadge::because(\sprintf('"%s" is not a measure name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $name));
        }

        if ($threshold < 1) {
            throw InvalidBadge::because(\sprintf('The threshold of a measure is at least 1; %d is not.', $threshold));
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
        return new Progress(max(0, $reading->measure($this->name)), $this->threshold);
    }
}
