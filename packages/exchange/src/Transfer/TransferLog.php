<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Transfer;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\ExchangeKey;

/**
 * The journal of the transfers between members, which the application examines and the allowance reads.
 */
interface TransferLog
{
    /**
     * Writes the transfer; returns false, writing nothing, when its key is already there.
     */
    public function record(Transfer $transfer): bool;

    public function find(ExchangeKey $key): ?Transfer;

    /**
     * What the member gave in the currency, counting the transfers from `since`, included, to `until`, excluded; a
     * bound left null does not limit.
     */
    public function given(ResourceReference $member, string $currency, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int;

    /**
     * The transfers the member gave or received, oldest first.
     *
     * @return list<Transfer>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * The latest transfers of every member, newest first, at most `limit` of them.
     *
     * @return list<Transfer>
     */
    public function latest(int $limit): array;

    /**
     * Writes `erased:member` in place of the member in their transfers, which stay the other members' too; returns
     * how many transfers it changed.
     */
    public function eraseMember(ResourceReference $member): int;
}
