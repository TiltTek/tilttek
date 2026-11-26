<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Exception\InvalidEntry;

/**
 * A team of a match and its members, whose totals compose its score.
 */
final readonly class Team
{
    /** @var non-empty-list<ParticipantId> */
    public array $members;

    /**
     * @param list<ParticipantId> $members
     *
     * @throws InvalidEntry when the team has no member
     */
    public function __construct(
        public TeamId $id,
        array $members,
    ) {
        if ([] === $members) {
            throw InvalidEntry::because(\sprintf('The team "%s" has at least one member.', $id->value));
        }

        $this->members = $members;
    }
}
