<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Trade;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Points\Movement;

/**
 * A trade one member offers another: the items the proposer gives, the items they ask for, and points either side may
 * add, in a currency; when it was made and when it expires unless answered, where it stands and when it closed, and
 * the offer it answers when it is a counter-offer. An offer holds at least one item, at most 50 on each side, and no
 * item twice.
 */
final readonly class Offer
{
    public const int MAX_ITEMS = 50;

    /**
     * @param list<ResourceReference> $offered   the items the proposer gives
     * @param list<ResourceReference> $requested the items the proposer asks the recipient for
     */
    public function __construct(
        public ExchangeKey $key,
        public ResourceReference $proposer,
        public ResourceReference $recipient,
        public array $offered,
        public array $requested,
        public int $offeredPoints,
        public int $requestedPoints,
        public string $currency,
        public DateTimeImmutable $madeAt,
        public DateTimeImmutable $expiresAt,
        public OfferStatus $status = OfferStatus::Open,
        public ?DateTimeImmutable $closedAt = null,
        public ?ExchangeKey $counters = null,
    ) {
        if ([] === $offered && [] === $requested) {
            throw InvalidExchange::because(\sprintf('The offer "%s" trades no item.', $key->value));
        }

        if (\count($offered) > self::MAX_ITEMS || \count($requested) > self::MAX_ITEMS) {
            throw InvalidExchange::because(\sprintf('An offer trades at most %d items on each side.', self::MAX_ITEMS));
        }

        $items = $this->itemNames();

        if (\count(array_unique($items)) !== \count($items)) {
            throw InvalidExchange::because(\sprintf('The offer "%s" names an item twice.', $key->value));
        }

        foreach ([$offeredPoints, $requestedPoints] as $points) {
            if ($points < 0 || $points > Movement::MAX_AMOUNT) {
                throw InvalidExchange::because(\sprintf('An offer adds 0 to %d points on each side; "%s" adds %d.', Movement::MAX_AMOUNT, $key->value, $points));
            }
        }

        if ($expiresAt <= $madeAt) {
            throw InvalidExchange::because(\sprintf('The offer "%s" expires before it is made.', $key->value));
        }

        if ((OfferStatus::Open === $status) !== (!$closedAt instanceof DateTimeImmutable)) {
            throw InvalidExchange::because(\sprintf('The offer "%s" is closed when, and only when, it is no longer open.', $key->value));
        }
    }

    /**
     * Whether the other offer is the same one: the same members, items in the same order, points, currency and answered
     * offer. The moments are left out, since an offer made again may stamp them again.
     */
    public function sameAs(self $other): bool
    {
        return $this->proposer->equals($other->proposer)
            && $this->recipient->equals($other->recipient)
            && $this->names($this->offered) === $this->names($other->offered)
            && $this->names($this->requested) === $this->names($other->requested)
            && $this->offeredPoints === $other->offeredPoints
            && $this->requestedPoints === $other->requestedPoints
            && $this->currency === $other->currency
            && $this->counters?->value === $other->counters?->value;
    }

    /**
     * Whether the offer is still open past its lifetime.
     */
    public function isDue(DateTimeImmutable $now): bool
    {
        return OfferStatus::Open === $this->status && $this->expiresAt <= $now;
    }

    /**
     * The offer closed: accepted, declined, withdrawn, countered or expired at the moment.
     */
    public function closed(OfferStatus $status, DateTimeImmutable $at): self
    {
        return $this->with($this->proposer, $this->recipient, $status, $at);
    }

    /**
     * The offer with `erased` in place of the member, wherever the member is one of its two sides.
     */
    public function without(ResourceReference $member, ResourceReference $erased): self
    {
        return $this->with(
            $this->proposer->equals($member) ? $erased : $this->proposer,
            $this->recipient->equals($member) ? $erased : $this->recipient,
            $this->status,
            $this->closedAt,
        );
    }

    /**
     * The moves of an accepted offer: the items offered to the recipient, the items requested to the proposer.
     *
     * @return list<ItemMove>
     */
    public function moves(): array
    {
        $moves = [];

        foreach ($this->offered as $item) {
            $moves[] = new ItemMove($item, $this->proposer, $this->recipient);
        }

        foreach ($this->requested as $item) {
            $moves[] = new ItemMove($item, $this->recipient, $this->proposer);
        }

        return $moves;
    }

    /**
     * The key of the ledger's transfer of the points one side adds: `offered` or `requested`.
     */
    public function pointsKey(string $side): string
    {
        return 'exchange:offer:'.$this->key->value.':'.$side;
    }

    /**
     * The offer as plain values, for an export or a log.
     *
     * @return array{key: string, proposer: string, recipient: string, offered: list<string>, requested: list<string>, offered_points: int, requested_points: int, currency: string, status: string, made_at: string, expires_at: string, closed_at: ?string, counters: ?string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key->value,
            'proposer' => $this->proposer->toString(),
            'recipient' => $this->recipient->toString(),
            'offered' => $this->names($this->offered),
            'requested' => $this->names($this->requested),
            'offered_points' => $this->offeredPoints,
            'requested_points' => $this->requestedPoints,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'made_at' => $this->madeAt->format(DateTimeInterface::ATOM),
            'expires_at' => $this->expiresAt->format(DateTimeInterface::ATOM),
            'closed_at' => $this->closedAt?->format(DateTimeInterface::ATOM),
            'counters' => $this->counters?->value,
        ];
    }

    /**
     * @return list<string>
     */
    private function itemNames(): array
    {
        return [...$this->names($this->offered), ...$this->names($this->requested)];
    }

    /**
     * @param list<ResourceReference> $items
     *
     * @return list<string>
     */
    private function names(array $items): array
    {
        return array_map(static fn (ResourceReference $item): string => $item->toString(), $items);
    }

    private function with(ResourceReference $proposer, ResourceReference $recipient, OfferStatus $status, ?DateTimeImmutable $closedAt): self
    {
        return new self($this->key, $proposer, $recipient, $this->offered, $this->requested, $this->offeredPoints, $this->requestedPoints, $this->currency, $this->madeAt, $this->expiresAt, $status, $closedAt, $this->counters);
    }
}
