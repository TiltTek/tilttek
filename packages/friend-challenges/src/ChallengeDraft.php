<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;

/**
 * What a member asks for when they create a challenge: its identifier, chosen by the application, its title and
 * terms, who may take part, who judges the proofs, the prize and when it ends. Between friends by default.
 *
 * A draft also reads back a stored challenge, whose invitees may have been erased since: `FriendChallenges::create()`,
 * not the draft, refuses an invitee of the kind `erased`.
 */
final readonly class ChallengeDraft
{
    public const int TITLE_LENGTH = 120;

    public const int DESCRIPTION_LENGTH = 2000;

    public const int MAX_VOTES = 25;

    public string $title;

    public string $description;

    public DateTimeImmutable $endsAt;

    /** @var list<ResourceReference> */
    public array $invitees;

    /**
     * @param list<ResourceReference> $invitees    the friends invited, for a challenge between friends
     * @param int                     $votesNeeded the approvals that accept a proof, or the rejections that reject
     *                                             it, when the proofs are put to a vote
     */
    public function __construct(
        public string $id,
        string $title,
        public Prize $prize,
        DateTimeImmutable $endsAt,
        array $invitees = [],
        public Visibility $visibility = Visibility::Friends,
        public Validation $validation = Validation::Creator,
        string $description = '',
        public int $votesNeeded = 1,
    ) {
        Check::identifier($id, 'A challenge identifier');
        $this->title = Check::text($title, 'The title of a challenge', self::TITLE_LENGTH, true);
        $this->description = Check::text($description, 'The terms of a challenge', self::DESCRIPTION_LENGTH, false, true);
        $this->endsAt = Instant::utc($endsAt);

        if ($votesNeeded < 1 || $votesNeeded > self::MAX_VOTES) {
            throw InvalidChallenge::because(\sprintf('A vote needs 1 to %d votes; %d is not.', self::MAX_VOTES, $votesNeeded));
        }

        if (Validation::Vote !== $validation && 1 !== $votesNeeded) {
            throw InvalidChallenge::because('Votes are needed only when the proofs are put to a vote.');
        }

        $unique = [];

        foreach ($invitees as $invitee) {
            $unique[$invitee->toString()] = $invitee;
        }

        if (Visibility::Open === $visibility && [] !== $unique) {
            throw InvalidChallenge::because('A challenge open to every member invites nobody in particular.');
        }

        if (Visibility::Friends === $visibility && [] === $unique) {
            throw InvalidChallenge::because('A challenge between friends invites at least one friend.');
        }

        if (\count($unique) > Challenge::MAX_INVITEES) {
            throw InvalidChallenge::because(\sprintf('A challenge invites %d friends at most.', Challenge::MAX_INVITEES));
        }

        $this->invitees = array_values($unique);
    }
}
