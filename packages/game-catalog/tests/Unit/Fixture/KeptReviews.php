<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit\Fixture;

use Override;
use Tilttek\GameCatalog\Contract\ReviewSystem;
use Tilttek\GameCatalog\Experience;

/**
 * The experiences kept for the reviews, in memory.
 */
final class KeptReviews implements ReviewSystem
{
    /** @var array<string, array{string, string, Experience}> by review: the entry, the member and the experience */
    public array $kept = [];

    #[Override]
    public function experience(string $review): ?Experience
    {
        return ($this->kept[$review] ?? null)[2] ?? null;
    }

    #[Override]
    public function keepExperience(string $review, string $entry, string $member, Experience $experience): void
    {
        $this->kept[$review] = [$entry, $member, $experience];
    }
}
