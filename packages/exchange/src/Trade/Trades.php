<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Trade;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Exchange\Announcer;
use Tilttek\Exchange\Event\OfferAccepted;
use Tilttek\Exchange\Event\OfferCountered;
use Tilttek\Exchange\Event\OfferDeclined;
use Tilttek\Exchange\Event\OfferExpired;
use Tilttek\Exchange\Event\OfferMade;
use Tilttek\Exchange\Event\OfferWithdrawn;
use Tilttek\Exchange\Exception\ExchangeClosed;
use Tilttek\Exchange\Exception\ExchangeConflict;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\Exception\UnknownExchange;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Guards;
use Tilttek\Exchange\ImmediateTransaction;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\SilentAnnouncer;
use Tilttek\Exchange\Transaction;
use Tilttek\Points\Account;
use Tilttek\Points\Currencies;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Points;

/**
 * Items members trade with each other, when the application allows it: one member offers items — and points if they
 * like — for items of another, who accepts, declines or answers with a counter-offer. The items offered are held
 * from the offer on, and cannot be offered elsewhere; on acceptance both sides change hands in one transaction, or
 * nothing moves. An offer left unanswered expires and frees its items.
 *
 * ```php
 * $trades->offer('trade:812', $member, $friend, offered: [$card], requested: [$otherCard]);
 * $trades->accept('trade:812', $friend);
 * ```
 *
 * The guards hold for each offer: never to oneself, not between accounts younger than the delay, and no more offers
 * than the allowance over its period. Only the items the application's inventory says are tradable change hands.
 */
final readonly class Trades
{
    public const string DEFAULT_LIFETIME = 'P7D';

    public const int DEFAULT_BATCH_SIZE = 500;

    private DateInterval $lifetime;

    /**
     * @param string $lifetime how long an offer waits for its answer, as an ISO 8601 duration
     */
    public function __construct(
        private OfferStore $offers,
        private Inventory $inventory,
        private Points $points,
        private Currencies $currencies,
        private ClockInterface $clock,
        private Guards $guards = new Guards(),
        private Transaction $transaction = new ImmediateTransaction(),
        private Announcer $announcer = new SilentAnnouncer(),
        string $lifetime = self::DEFAULT_LIFETIME,
    ) {
        try {
            $this->lifetime = new DateInterval($lifetime);
        } catch (Exception) {
            throw InvalidExchange::because(\sprintf('"%s" is not an ISO 8601 duration like "P7D".', $lifetime));
        }

        $now = new DateTimeImmutable();

        if ($now->add($this->lifetime) <= $now) {
            throw InvalidExchange::because(\sprintf('An offer waits for some time; "%s" is no time.', $lifetime));
        }
    }

    /**
     * Offers a trade: the proposer's items, and points if any, for the recipient's items, and points if any. The same
     * key given again returns the same offer.
     *
     * @param list<ResourceReference> $offered   the proposer's items
     * @param list<ResourceReference> $requested the recipient's items
     *
     * @throws ExchangeRefused  when the guards refuse it, an item is not its giver's, not tradable or already held
     * @throws ExchangeConflict when the key is written for another offer
     */
    public function offer(string $key, ResourceReference $proposer, ResourceReference $recipient, array $offered, array $requested, int $offeredPoints = 0, int $requestedPoints = 0, ?string $currency = null): Offer
    {
        [$offer, $written] = $this->prepare(ExchangeKey::fromString($key), $proposer, $recipient, $offered, $requested, $offeredPoints, $requestedPoints, $currency, null);

        if ($written) {
            return $offer;
        }

        $this->store($offer);
        $this->announcer->announce(new OfferMade($offer));

        return $offer;
    }

    /**
     * Accepts the offer, by the member it was made to: the items and the points change hands, all or nothing.
     * Accepting it again returns it as it is.
     *
     * @throws UnknownExchange
     * @throws ExchangeRefused    when the member is not the recipient, or an item is no longer its giver's, no longer
     *                            tradable, or held by another offer
     * @throws InsufficientPoints when a side's balance does not cover the points it adds
     * @throws ExchangeClosed     when the offer is no longer open
     */
    public function accept(string $key, ResourceReference $by): Offer
    {
        $offer = $this->get($key);

        if (OfferStatus::Accepted === $offer->status) {
            return $offer;
        }

        $this->checkParty($offer, $offer->recipient, $by);
        $this->checkOpen($offer);

        foreach ($offer->offered as $item) {
            $this->checkItem($item, $offer->proposer);
        }

        foreach ($offer->requested as $item) {
            $this->checkItem($item, $offer->recipient);
            $this->checkFree($item);
        }

        $this->checkBalance($offer->proposer, $offer->offeredPoints, $offer->currency);
        $this->checkBalance($offer->recipient, $offer->requestedPoints, $offer->currency);
        $now = $this->clock->now();

        $this->transaction->run(function () use ($offer, $now): void {
            if (!$this->offers->close($offer->key, OfferStatus::Accepted, $now)) {
                throw ExchangeClosed::because(\sprintf('The offer "%s" was closed by another answer.', $offer->key->value));
            }

            if ($offer->offeredPoints > 0) {
                $this->points->transfer($offer->proposer, $offer->recipient, $offer->offeredPoints, $offer->pointsKey('offered'), $offer->currency);
            }

            if ($offer->requestedPoints > 0) {
                $this->points->transfer($offer->recipient, $offer->proposer, $offer->requestedPoints, $offer->pointsKey('requested'), $offer->currency);
            }

            $this->inventory->move($offer->moves());
        });

        $accepted = $offer->closed(OfferStatus::Accepted, $now);
        $this->announcer->announce(new OfferAccepted($accepted));

        return $accepted;
    }

    /**
     * Declines the offer, by the member it was made to: its items are free again. Declining it again returns it as it
     * is.
     *
     * @throws UnknownExchange
     * @throws ExchangeRefused when the member is not the recipient
     * @throws ExchangeClosed  when the offer is no longer open
     */
    public function decline(string $key, ResourceReference $by): Offer
    {
        $offer = $this->get($key);
        $this->checkParty($offer, $offer->recipient, $by);

        return $this->answer($offer, OfferStatus::Declined);
    }

    /**
     * Withdraws the offer, by the member who made it: its items are free again. Withdrawing it again returns it as it
     * is.
     *
     * @throws UnknownExchange
     * @throws ExchangeRefused when the member is not the proposer
     * @throws ExchangeClosed  when the offer is no longer open
     */
    public function withdraw(string $key, ResourceReference $by): Offer
    {
        $offer = $this->get($key);
        $this->checkParty($offer, $offer->proposer, $by);

        return $this->answer($offer, OfferStatus::Withdrawn);
    }

    /**
     * Answers the offer, by the member it was made to, with an offer of their own to the member who made it: the
     * first offer closes as countered and the counter-offer opens, in one transaction. The same key given again
     * returns the same counter-offer.
     *
     * @param list<ResourceReference> $offered   the items the member who counters gives
     * @param list<ResourceReference> $requested the items they ask for
     *
     * @throws UnknownExchange
     * @throws ExchangeRefused  as an offer and an acceptance are
     * @throws ExchangeClosed   when the offer is no longer open
     * @throws ExchangeConflict when the key is written for another offer
     */
    public function counter(string $key, ResourceReference $by, string $counterKey, array $offered, array $requested, int $offeredPoints = 0, int $requestedPoints = 0): Offer
    {
        $offer = $this->get($key);
        $this->checkParty($offer, $offer->recipient, $by);
        [$counter, $written] = $this->prepare(ExchangeKey::fromString($counterKey), $by, $offer->proposer, $offered, $requested, $offeredPoints, $requestedPoints, $offer->currency, $offer->key);

        if ($written) {
            return $counter;
        }

        $this->checkOpen($offer);
        $now = $this->clock->now();

        $this->transaction->run(function () use ($offer, $counter, $now): void {
            if (!$this->offers->close($offer->key, OfferStatus::Countered, $now)) {
                throw ExchangeClosed::because(\sprintf('The offer "%s" was closed by another answer.', $offer->key->value));
            }

            $this->store($counter);
        });

        $this->announcer->announce(new OfferCountered($offer->closed(OfferStatus::Countered, $now), $counter));

        return $counter;
    }

    /**
     * Expires the offers whose lifetime has ended, freeing their items: the task a scheduler runs. Returns how many
     * expired.
     */
    public function expireDue(int $batchSize = self::DEFAULT_BATCH_SIZE): int
    {
        $expired = 0;

        do {
            $due = $this->offers->due($this->clock->now(), $batchSize);

            foreach ($due as $offer) {
                if ($this->close($offer, OfferStatus::Expired)) {
                    ++$expired;
                }
            }
        } while (\count($due) === $batchSize);

        return $expired;
    }

    /**
     * @throws UnknownExchange
     */
    public function get(string $key): Offer
    {
        return $this->offers->find(ExchangeKey::fromString($key)) ?? throw UnknownExchange::offer($key);
    }

    /**
     * The offers the member made or was made, in the order they were made: the journal of their trades.
     *
     * @return list<Offer>
     */
    public function ofMember(ResourceReference $member): array
    {
        return $this->offers->ofMember($member);
    }

    /**
     * How many offers the member made over the allowance's current period, or ever without an allowance.
     */
    public function made(ResourceReference $member): int
    {
        [$since, $until] = $this->guards->window($this->clock->now());

        return $this->offers->made($member, $since, $until);
    }

    /**
     * The offer as asked for, checked against the guards and the inventory, and false; the offer already written
     * under its key, when it is the same one, and true.
     *
     * @param list<ResourceReference> $offered
     * @param list<ResourceReference> $requested
     *
     * @return array{Offer, bool}
     */
    private function prepare(ExchangeKey $key, ResourceReference $proposer, ResourceReference $recipient, array $offered, array $requested, int $offeredPoints, int $requestedPoints, ?string $currency, ?ExchangeKey $counters): array
    {
        $now = $this->clock->now();
        $offer = new Offer($key, $proposer, $recipient, $offered, $requested, $offeredPoints, $requestedPoints, $this->currencies->get($currency)->code, $now, $now->add($this->lifetime), counters: $counters);
        $existing = $this->offers->find($key);

        if ($existing instanceof Offer) {
            return [$this->existing($existing, $offer), true];
        }

        $this->guards->checkParties($proposer, $recipient, $now);
        [$since, $until] = $this->guards->window($now);
        $this->guards->checkAllowance($proposer, $this->offers->made($proposer, $since, $until), 1);

        foreach ($offered as $item) {
            $this->checkItem($item, $proposer);
            $this->checkFree($item);
        }

        foreach ($requested as $item) {
            $this->checkItem($item, $recipient);
        }

        return [$offer, false];
    }

    private function existing(Offer $existing, Offer $asked): Offer
    {
        if (!$existing->sameAs($asked)) {
            throw ExchangeConflict::on('offer', $existing->key->value);
        }

        return $existing;
    }

    private function store(Offer $offer): void
    {
        if (!$this->offers->open($offer)) {
            $this->existing($this->offers->find($offer->key) ?? $offer, $offer);
        }
    }

    private function checkItem(ResourceReference $item, ResourceReference $giver): void
    {
        $owner = $this->inventory->ownerOf($item);

        if (!$owner instanceof ResourceReference || !$owner->equals($giver)) {
            throw ExchangeRefused::because(Refusal::NotOwned, \sprintf('"%s" does not belong to "%s".', $item->toString(), $giver->toString()));
        }

        if (!$this->inventory->isTradable($item)) {
            throw ExchangeRefused::because(Refusal::NotTradable, \sprintf('"%s" cannot change hands.', $item->toString()));
        }
    }

    private function checkFree(ResourceReference $item): void
    {
        $holder = $this->offers->holder($item);

        if ($holder instanceof ExchangeKey) {
            throw ExchangeRefused::because(Refusal::AlreadyHeld, \sprintf('"%s" is held by the offer "%s".', $item->toString(), $holder->value));
        }
    }

    private function checkParty(Offer $offer, ResourceReference $party, ResourceReference $by): void
    {
        if (!$party->equals($by)) {
            throw ExchangeRefused::because(Refusal::NotParty, \sprintf('"%s" cannot answer the offer "%s" this way.', $by->toString(), $offer->key->value));
        }
    }

    /**
     * Refuses an offer that is no longer open, expiring it first when its lifetime has ended.
     */
    private function checkOpen(Offer $offer): void
    {
        if ($offer->isDue($this->clock->now())) {
            $this->close($offer, OfferStatus::Expired);

            throw ExchangeClosed::because(\sprintf('The offer "%s" has expired.', $offer->key->value));
        }

        if (OfferStatus::Open !== $offer->status) {
            throw ExchangeClosed::because(\sprintf('The offer "%s" is %s.', $offer->key->value, $offer->status->value));
        }
    }

    private function checkBalance(ResourceReference $member, int $amount, string $currency): void
    {
        $balance = $this->points->balance($member, $currency);

        if ($balance < $amount) {
            throw InsufficientPoints::in(Account::member($member), $currency, $balance, $amount);
        }
    }

    private function answer(Offer $offer, OfferStatus $status): Offer
    {
        if ($status === $offer->status) {
            return $offer;
        }

        $this->checkOpen($offer);

        if (!$this->close($offer, $status)) {
            throw ExchangeClosed::because(\sprintf('The offer "%s" was closed by another answer.', $offer->key->value));
        }

        return $this->get($offer->key->value);
    }

    /**
     * Closes the offer if it is still open and tells it; returns whether this call closed it.
     */
    private function close(Offer $offer, OfferStatus $status): bool
    {
        $now = $this->clock->now();

        if (!$this->offers->close($offer->key, $status, $now)) {
            return false;
        }

        $closed = $offer->closed($status, $now);
        $this->announcer->announce(match ($status) {
            OfferStatus::Declined => new OfferDeclined($closed),
            OfferStatus::Withdrawn => new OfferWithdrawn($closed),
            default => new OfferExpired($closed),
        });

        return true;
    }
}
