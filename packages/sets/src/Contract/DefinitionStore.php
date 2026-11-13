<?php

declare(strict_types=1);

namespace Tilttek\Sets\Contract;

use Tilttek\Sets\SetDefinition;

/**
 * Where the versions of the sets are kept: every version, never rewritten, so that a completion always names the
 * version it was earned under.
 */
interface DefinitionStore
{
    /**
     * Adds a version; returns false, writing nothing, when the store holds that version of that set.
     */
    public function add(SetDefinition $definition): bool;

    /**
     * The highest version of a set, or null when it was never defined.
     */
    public function latest(string $code): ?SetDefinition;

    public function version(string $code, int $version): ?SetDefinition;
}
