<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use Override;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Progress;

/**
 * A combination: a number of the criteria of a list reached — all of them by default. Its progress counts the
 * criteria reached.
 */
final readonly class AtLeast implements Criterion
{
    /**
     * @var list<Criterion>
     */
    public array $criteria;

    public int $required;

    /**
     * @param list<Criterion> $criteria
     */
    public function __construct(array $criteria, ?int $required = null)
    {
        if ([] === $criteria) {
            throw InvalidBadge::because('A combination holds at least one criterion.');
        }

        $required ??= \count($criteria);

        if ($required < 1 || $required > \count($criteria)) {
            throw InvalidBadge::because(\sprintf('A combination of %d criteria requires 1 to %d of them; this one requires %d.', \count($criteria), \count($criteria), $required));
        }

        $this->criteria = $criteria;
        $this->required = $required;
    }

    #[Override]
    public function leaves(string $path): array
    {
        $leaves = [];

        foreach ($this->criteria as $index => $criterion) {
            $leaves += $criterion->leaves($path.'.'.$index);
        }

        return $leaves;
    }

    #[Override]
    public function progress(string $path, Reading $reading): Progress
    {
        $reached = 0;

        foreach ($this->criteria as $index => $criterion) {
            if ($criterion->progress($path.'.'.$index, $reading)->isReached()) {
                ++$reached;
            }
        }

        return new Progress($reached, $this->required);
    }
}
