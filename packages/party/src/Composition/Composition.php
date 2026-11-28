<?php

declare(strict_types=1);

namespace Tilttek\Party\Composition;

use Phil\Resource\ResourceReference;

/**
 * What a party's composition looks like against the roles of its system: advice to read, never a rule — the library
 * refuses no member, no role and no party for it.
 *
 * Each role of the catalogue is `Missing` when fewer active members fill it than its minimum, `Crowded` when more fill
 * it than its fair share — the active members divided among the roles, rounded up, and never below the role's minimum
 * —, `Covered` otherwise. Away members count nowhere. Against a catalogue without roles there is nothing to advise:
 * no coverage, no unassigned member, no unknown role, and the party is balanced.
 */
final readonly class Composition
{
    /**
     * @param list<RoleCoverage>                     $coverages    the roles of the catalogue, in its order
     * @param list<ResourceReference>                $unassigned   the active members who fill no role of the catalogue
     * @param array<string, list<ResourceReference>> $unknownRoles the roles members fill that the catalogue lacks, by key
     * @param int                                    $active       the active members counted
     * @param int                                    $fairShare    the active members divided among the roles, rounded up: a role holds more than this, or than its minimum when that is higher, before it is crowded; 0 without roles
     */
    public function __construct(
        public array $coverages,
        public array $unassigned,
        public array $unknownRoles,
        public int $active,
        public int $fairShare,
    ) {
    }

    /**
     * True when the composition was read against roles and has advice to give; false for a game without roles.
     */
    public function hasAdvice(): bool
    {
        return [] !== $this->coverages;
    }

    /**
     * True when no role is missing nor crowded.
     */
    public function isBalanced(): bool
    {
        return [] === $this->missing() && [] === $this->crowded();
    }

    /**
     * @return list<RoleCoverage> the roles below their minimum
     */
    public function missing(): array
    {
        return $this->with(Coverage::Missing);
    }

    /**
     * @return list<RoleCoverage> the roles above their fair share
     */
    public function crowded(): array
    {
        return $this->with(Coverage::Crowded);
    }

    public function coverageOf(string $role): ?RoleCoverage
    {
        foreach ($this->coverages as $coverage) {
            if ($coverage->role->key === $role) {
                return $coverage;
            }
        }

        return null;
    }

    /**
     * @return list<RoleCoverage>
     */
    private function with(Coverage $wanted): array
    {
        return array_values(array_filter($this->coverages, static fn (RoleCoverage $coverage): bool => $wanted === $coverage->coverage));
    }
}
