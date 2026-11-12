<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;

/**
 * The members a board is ranked among, for a ranking between friends or inside a group — a club, a table —: 1 to
 * 1000 members, which the application gives. Tilttek keeps no friendship nor group.
 */
final readonly class Circle
{
    public const int MAX_MEMBERS = 1000;

    /**
     * @var list<string> the members as `kind:identifier`, each once, sorted
     */
    public array $members;

    public function __construct(ResourceReference ...$members)
    {
        $references = array_unique(array_map(static fn (ResourceReference $member): string => $member->toString(), $members));
        sort($references, \SORT_STRING);

        if ([] === $references || \count($references) > self::MAX_MEMBERS) {
            throw InvalidLeaderboard::because(\sprintf('A circle holds 1 to %d members; this one holds %d.', self::MAX_MEMBERS, \count($references)));
        }

        $this->members = $references;
    }

    /**
     * A circle from members written `kind:identifier`.
     */
    public static function of(string ...$members): self
    {
        return new self(...array_map(ResourceReference::fromString(...), $members));
    }

    public function has(ResourceReference $member): bool
    {
        return \in_array($member->toString(), $this->members, true);
    }
}
