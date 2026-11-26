<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Tests\Unit\Fixture;

use Phil\Resource\ResourceReference;
use Tilttek\FanAwards\Category;
use Tilttek\FanAwards\Contract\NomineeEligibility;
use Tilttek\FanAwards\Contract\VoterEligibility;
use Tilttek\FanAwards\Edition;
use Tilttek\FanAwards\Nominee;

/**
 * The rules of a test edition: a nominee whose key starts with "old" was published too early, and only the members
 * listed vote.
 */
final readonly class Rules implements NomineeEligibility, VoterEligibility
{
    /**
     * @param list<string> $voters
     */
    public function __construct(
        private array $voters,
    ) {
    }

    public function refusal(Edition $edition, Category $category, Nominee $nominee): ?string
    {
        return str_starts_with($nominee->key, 'old') ? \sprintf('"%s" was published before the award year of %s.', $nominee->title, $edition->name) : null;
    }

    public function mayVote(ResourceReference $voter, Edition $edition): bool
    {
        return \in_array($voter->toString(), $this->voters, true);
    }
}
