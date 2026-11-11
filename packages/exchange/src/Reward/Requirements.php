<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reward;

use Phil\Resource\ResourceReference;

/**
 * Checks the conditions of the rewards — `badge:helper`, `level:5` —, which the application names and answers from
 * its badges, its levels or anything else: the library depends on none of them.
 */
interface Requirements
{
    public function meets(ResourceReference $member, string $requirement): bool;
}
