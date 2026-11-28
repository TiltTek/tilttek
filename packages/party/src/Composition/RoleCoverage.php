<?php

declare(strict_types=1);

namespace Tilttek\Party\Composition;

use Phil\Resource\ResourceReference;

/**
 * A role of the catalogue, the active members who fill it, in the order of the party, and how well it is filled.
 */
final readonly class RoleCoverage
{
    /**
     * @param list<ResourceReference> $members
     */
    public function __construct(
        public Role $role,
        public array $members,
        public Coverage $coverage,
    ) {
    }

    public function count(): int
    {
        return \count($this->members);
    }
}
