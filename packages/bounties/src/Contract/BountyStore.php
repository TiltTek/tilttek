<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Contract;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\Exception\BountyConflict;

/**
 * Where the application keeps the bounties. `Bounty::toArray()` gives the record to keep and `Bounty::fromArray()`
 * reads it back.
 */
interface BountyStore
{
    public function get(string $id): ?Bounty;

    /**
     * The identifier of the open bounty on the subject, or null: a subject has one open bounty at a time.
     */
    public function openOn(ResourceReference $subject): ?string;

    /**
     * How many open bounties the member sponsors.
     */
    public function openBy(ResourceReference $sponsor): int;

    /**
     * Keeps a new bounty.
     *
     * @throws BountyConflict when a bounty of the same identifier, or an open bounty on the same subject, is kept already
     */
    public function add(Bounty $bounty): void;

    /**
     * Keeps the bounty at its next version, if and only if the kept one is still at `$expected`.
     *
     * @throws BountyConflict when the kept bounty is at another version; nothing is written
     */
    public function update(Bounty $bounty, int $expected): void;

    /**
     * The identifiers of the open bounties whose grace ended at the moment, the earliest first, `$limit` at most.
     *
     * @return list<string>
     */
    public function due(DateTimeImmutable $at, int $limit): array;

    /**
     * The identifiers of the bounties that name the member — see `Bounty::involves()` —, `$limit` at most: what the
     * erasure of the member goes through.
     *
     * @return list<string>
     */
    public function involving(ResourceReference $member, int $limit): array;
}
