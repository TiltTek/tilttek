<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Seeding;

use Override;
use Tilttek\Tournament\Contract\SeedingSource;

/**
 * The strengths given, by participant: an organiser's own seeding, or a list of seeds read as strengths.
 */
final readonly class FixedSeeding implements SeedingSource
{
    /**
     * @param array<string, int> $strengths by participant, the strongest highest
     */
    public function __construct(
        private array $strengths,
    ) {
    }

    /**
     * The participants in seed order, the first seed first.
     *
     * @param list<string> $participants
     */
    public static function inOrder(array $participants): self
    {
        $strengths = [];

        foreach ($participants as $index => $participant) {
            $strengths[$participant] = \count($participants) - $index;
        }

        return new self($strengths);
    }

    #[Override]
    public function strengths(array $participants): array
    {
        return array_intersect_key($this->strengths, array_flip($participants));
    }
}
