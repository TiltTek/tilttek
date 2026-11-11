<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use Phil\Resource\ResourceReference;

/**
 * The values the application measures for its members — a balance of points, a level —, which `Measure` criteria
 * read. Badges never depend on another gamification package: the application plugs its own measures in.
 */
interface Measures
{
    public function value(string $measure, ResourceReference $member): int;
}
