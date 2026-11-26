<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Seeding;

use Tilttek\Tournament\Contract\SeedingSource;
use Tilttek\Tournament\SeededDraw;

/**
 * The order of participants before the first round: the strongest first, by the seeding source, then those of the same
 * strength or without one in the order of the published seed.
 */
final class InitialOrder
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $participants
     *
     * @return list<string>
     */
    public static function of(array $participants, string $seed, ?SeedingSource $seeding = null): array
    {
        $strengths = $seeding?->strengths($participants) ?? [];
        $drawn = array_flip(SeededDraw::order($seed, $participants));

        usort($participants, static function (string $a, string $b) use ($strengths, $drawn): int {
            $known = isset($strengths[$b]) <=> isset($strengths[$a]);

            if (0 !== $known) {
                return $known;
            }

            return [$strengths[$b] ?? 0, $drawn[$a]] <=> [$strengths[$a] ?? 0, $drawn[$b]];
        });

        return $participants;
    }
}
