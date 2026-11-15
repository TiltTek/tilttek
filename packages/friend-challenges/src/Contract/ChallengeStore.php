<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Contract;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\Exception\ChallengeConflict;

/**
 * Where the application keeps the challenges. `Challenge::toArray()` gives the record to keep and
 * `Challenge::fromArray()` reads it back.
 */
interface ChallengeStore
{
    public function get(string $id): ?Challenge;

    /**
     * Keeps a new challenge.
     *
     * @throws ChallengeConflict when a challenge of the same identifier is kept already
     */
    public function add(Challenge $challenge): void;

    /**
     * Keeps the challenge at its next version, if and only if the kept one is still at `$expected`.
     *
     * @throws ChallengeConflict when the kept challenge is at another version; nothing is written
     */
    public function update(Challenge $challenge, int $expected): void;

    /**
     * The identifiers of the open challenges whose review ended at the moment, the earliest first, `$limit` at most.
     *
     * @return list<string>
     */
    public function due(DateTimeImmutable $at, int $limit): array;

    /**
     * The identifiers of the challenges that name the member — see `Challenge::involves()` —, `$limit` at most: what
     * the erasure of the member goes through.
     *
     * @return list<string>
     */
    public function involving(ResourceReference $member, int $limit): array;
}
