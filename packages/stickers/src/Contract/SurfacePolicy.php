<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Contract;

use Phil\Resource\ResourceReference;

/**
 * Whether a holder may place stickers on a surface: their own profile, their own messages, as the application knows.
 * There is no permissive default: the application says which surfaces are whose.
 */
interface SurfacePolicy
{
    public function allows(ResourceReference $holder, ResourceReference $surface): bool;
}
