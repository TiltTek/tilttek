<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Trade;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;

/**
 * Where the offers are kept. An offer is opened once per key, holding its offered items in the same step, so that an
 * item is never offered twice at the same time; an offer leaves `Open` once, releasing its items, so that two
 * processes never both accept, decline or expire it.
 */
interface OfferStore
{
    /**
     * Opens the offer and holds its offered items; returns false, writing nothing, when its key is already there.
     *
     * @throws ExchangeRefused with `Refusal::AlreadyHeld` when an offered item is held by another open offer
     */
    public function open(Offer $offer): bool;

    public function find(ExchangeKey $key): ?Offer;

    /**
     * Closes the offer if it is still open and releases its items; returns whether this call closed it.
     */
    public function close(ExchangeKey $key, OfferStatus $status, DateTimeImmutable $at): bool;

    /**
     * The open offer that holds the item; null when none does.
     */
    public function holder(ResourceReference $item): ?ExchangeKey;

    /**
     * How many offers the member made from `since`, included, to `until`, excluded, whatever became of them; a bound
     * left null does not limit.
     */
    public function made(ResourceReference $proposer, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int;

    /**
     * The open offers whose lifetime ended at the moment or before, the oldest first, at most `limit` of them.
     *
     * @return list<Offer>
     */
    public function due(DateTimeImmutable $now, int $limit): array;

    /**
     * The offers the member made or was made, in the order they were made.
     *
     * @return list<Offer>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * Writes `erased:member` in place of the member in their offers, which stay the other members' trades too;
     * returns how many offers it changed. The member's open offers are closed before.
     */
    public function eraseMember(ResourceReference $member): int;
}
