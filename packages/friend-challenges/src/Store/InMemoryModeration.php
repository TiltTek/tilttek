<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Contract\Moderation;
use Tilttek\FriendChallenges\Report;

/**
 * A moderation in memory, for the tests and the demonstrations: its moderators named at construction, its reports
 * kept in a list.
 */
final class InMemoryModeration implements Moderation
{
    /** @var list<Report> */
    public private(set) array $reports = [];

    /** @var list<string> */
    private readonly array $moderators;

    public function __construct(ResourceReference ...$moderators)
    {
        $this->moderators = array_values(array_map(static fn (ResourceReference $member): string => $member->toString(), $moderators));
    }

    #[Override]
    public function report(Report $report): void
    {
        $this->reports[] = $report;
    }

    #[Override]
    public function mayModerate(ResourceReference $member): bool
    {
        return \in_array($member->toString(), $this->moderators, true);
    }
}
