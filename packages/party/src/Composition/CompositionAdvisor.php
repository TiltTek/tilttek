<?php

declare(strict_types=1);

namespace Tilttek\Party\Composition;

use Tilttek\Party\Member;

/**
 * Reads a party's members against the roles of its system and tells which roles are missing, filled or crowded, who
 * fills no role, and which roles members fill that the system does not declare. Against a catalogue without roles it
 * gives no advice: only the active members are counted.
 *
 * It keeps no state: one instance serves every request. Its work grows with the members times their roles, both
 * bounded — 100 members of 5 roles at most.
 */
final readonly class CompositionAdvisor
{
    /**
     * @param iterable<Member> $members the party's members, in its order; away members are left out
     */
    public function advise(iterable $members, RoleCatalogue $catalogue): Composition
    {
        if ($catalogue->isEmpty()) {
            return new Composition([], [], [], $this->active($members), 0);
        }

        $roles = $catalogue->roles();
        $filling = array_fill_keys(array_map(static fn (Role $role): string => $role->key, $roles), []);
        $unassigned = [];
        $unknown = [];
        $active = 0;

        foreach ($members as $member) {
            if (!$member->isActive()) {
                continue;
            }

            ++$active;
            $known = false;

            foreach ($member->roles as $role) {
                if ($catalogue->has($role)) {
                    $filling[$role][] = $member->member;
                    $known = true;
                } else {
                    $unknown[$role][] = $member->member;
                }
            }

            if (!$known) {
                $unassigned[] = $member->member;
            }
        }

        $even = intdiv($active + \count($roles) - 1, \count($roles));
        $coverages = [];

        foreach ($roles as $role) {
            $count = \count($filling[$role->key]);
            $fair = max($even, $role->minimum);
            $coverages[] = new RoleCoverage($role, $filling[$role->key], match (true) {
                $count < $role->minimum => Coverage::Missing,
                $count > $fair => Coverage::Crowded,
                default => Coverage::Covered,
            });
        }

        return new Composition($coverages, $unassigned, $unknown, $active, $even);
    }

    /**
     * @param iterable<Member> $members
     */
    private function active(iterable $members): int
    {
        $active = 0;

        foreach ($members as $member) {
            if ($member->isActive()) {
                ++$active;
            }
        }

        return $active;
    }
}
