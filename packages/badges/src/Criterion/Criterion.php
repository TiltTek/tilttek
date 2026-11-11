<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use Tilttek\Badges\Progress;

/**
 * What a member must do to earn a badge. A criterion is a tree: its leaves read the members' activity, each under its
 * path — `r` for the root, `r.0`, `r.1.2` under a combination —, and a combination reads the progress of its children.
 */
interface Criterion
{
    /**
     * The leaves of the tree under this criterion, by path.
     *
     * @return array<string, Leaf>
     */
    public function leaves(string $path): array;

    /**
     * The member's progress towards this criterion, read from what their activity brought to its leaves.
     */
    public function progress(string $path, Reading $reading): Progress;
}
