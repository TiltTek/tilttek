<?php

declare(strict_types=1);

namespace Tilttek\Kudos;

use DateInterval;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Context;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Exception\IdempotencyConflict;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Verb;
use Tilttek\Kudos\Contract\KudosObserver;
use Tilttek\Kudos\Contract\KudosPreferences;
use Tilttek\Kudos\Contract\MemberTimeZones;
use Tilttek\Kudos\Exception\InvalidKudos;
use Tilttek\Kudos\Exception\KudosRefused;
use Tilttek\Kudos\Exception\UnknownMessage;
use Tilttek\Kudos\Preferences\AcceptAll;
use Tilttek\Kudos\TimeZone\SiteTimeZone;

/**
 * Thanks between members, recorded in the activity journal: a member thanks another with one of the application's
 * fixed messages, about an object or not; the receiver reads what they received, the giver what they gave, and nobody
 * reads what another member received — the library offers no total of thanks received and no ranking.
 *
 * ```php
 * $kudos->give($giver, $receiver, 'help', ResourceReference::of('comment', '4512'));
 * $kudos->received($signedInMember);
 * $kudos->givenCount($member);
 * ```
 *
 * Thanks are an activity of the verb `kudos.given`: the giver is its member, the receiver its object, the message's
 * name and the object's reference its context. Erasing a member through the journal deletes the thanks they gave and
 * forgets them as the receiver of the thanks of others.
 */
final readonly class Kudos
{
    /**
     * The verb of the activity that records thanks, which a badge's criterion reads.
     */
    public const string VERB = 'kudos.given';

    /**
     * The context entry of the activity that holds the message's name.
     */
    public const string CONTEXT_MESSAGE = 'message';

    /**
     * The context entry of the activity that holds the reference of the object thanked for.
     */
    public const string CONTEXT_OBJECT = 'object';

    /**
     * What a key the library builds starts with.
     */
    public const string KEY_PREFIX = 'kudos:';

    public const int MAX_PAGE = 100;

    private Messages $messages;

    /**
     * @var list<KudosObserver>
     */
    private array $observers;

    /**
     * @param iterable<KudosObserver> $observers
     */
    public function __construct(
        private Journal $journal,
        private ClockInterface $clock,
        ?Messages $messages = null,
        private Limits $limits = new Limits(),
        private KudosPreferences $preferences = new AcceptAll(),
        private MemberTimeZones $timeZones = new SiteTimeZone(),
        iterable $observers = [],
    ) {
        $this->messages = $messages ?? Messages::defaults();
        $list = [];

        foreach ($observers as $observer) {
            $list[] = $observer;
        }

        $this->observers = $list;
    }

    /**
     * Gives thanks, once per key: given again under the same key, it returns the thanks already given, refuses nothing
     * and tells nothing. Without a key, the key is made of the giver, the receiver, the message, the object and the
     * giver's day, so that the same thanks clicked twice in a day are given once.
     *
     * @throws UnknownMessage      when the list of messages holds none of this name
     * @throws InvalidKudos        when the object's reference is longer than 100 bytes
     * @throws KudosRefused        when the thanks cannot be given, with the reason
     * @throws IdempotencyConflict when the key was given to other thanks
     */
    public function give(ResourceReference $giver, ResourceReference $receiver, Message|string $message, ?ResourceReference $object = null, IdempotencyKey|string|null $key = null): Given
    {
        $message = $this->messages->get($message instanceof Message ? $message->name : $message);
        $context = [self::CONTEXT_MESSAGE => $message->name];

        if ($object instanceof ResourceReference) {
            $context[self::CONTEXT_OBJECT] = $object->toString();

            if (\strlen($context[self::CONTEXT_OBJECT]) > Context::MAX_STRING_LENGTH) {
                throw InvalidKudos::because(\sprintf('The reference of the object thanked for is at most %d bytes; "%s" is %d.', Context::MAX_STRING_LENGTH, $context[self::CONTEXT_OBJECT], \strlen($context[self::CONTEXT_OBJECT])));
            }
        }

        $now = $this->clock->now();
        $key = match (true) {
            $key instanceof IdempotencyKey => $key,
            \is_string($key) => IdempotencyKey::fromString($key),
            default => $this->keyOf([$giver->toString(), $receiver->toString(), $message->name, $context[self::CONTEXT_OBJECT] ?? '', $this->dayStart($giver, $now)->format('Y-m-d')]),
        };

        if (!$this->journal->find($key) instanceof Entry) {
            $refusal = $this->refusalAt($giver, $receiver, $now);

            if ($refusal instanceof Refusal) {
                throw KudosRefused::because($refusal);
            }
        }

        $appended = $this->journal->record(new Activity($key, $giver, Verb::fromString(self::VERB), $now, $receiver, 1, new Context($context)));
        $thanks = Thanks::fromActivity($appended->entry->activity);

        if ($appended->new) {
            $event = new KudosGiven($thanks->key, $giver->toString(), $receiver->toString(), $thanks->message, $thanks->object?->toString(), $thanks->givenAt);

            foreach ($this->observers as $observer) {
                $observer->given($event);
            }
        }

        return new Given($thanks, $appended->new);
    }

    /**
     * Why these thanks would be refused now; null when they can be given. A screen reads it to offer the button or not.
     */
    public function refusal(ResourceReference $giver, ResourceReference $receiver): ?Refusal
    {
        return $this->refusalAt($giver, $receiver, $this->clock->now());
    }

    /**
     * How many thanks the member may still give today, in their time zone.
     */
    public function remainingToday(ResourceReference $giver): int
    {
        return $this->limits->daily - $this->givenToday($giver, $this->clock->now());
    }

    /**
     * Withdraws thanks the member gave; false when the key records no thanks of theirs. Withdrawn thanks still count
     * towards the giver's caps.
     */
    public function withdraw(ResourceReference $giver, IdempotencyKey|string $key): bool
    {
        $entry = $this->thanksEntry($key);

        return $entry instanceof Entry && $entry->activity->member->equals($giver) && $this->cancelled($entry, true);
    }

    /**
     * Cancels thanks whoever gave them — a moderator's removal; false when the key records no thanks.
     */
    public function cancel(IdempotencyKey|string $key): bool
    {
        $entry = $this->thanksEntry($key);

        return $entry instanceof Entry && $this->cancelled($entry, false);
    }

    /**
     * The thanks the member received, oldest first, withdrawn thanks left out. An application passes the signed-in
     * member only: what a member received is theirs to read.
     *
     * @throws InvalidKudos when the position is negative or the limit is not 1 to 100
     */
    public function received(ResourceReference $member, int $after = 0, int $limit = 50): ThanksPage
    {
        return $this->page(Filter::all()->verbs(self::VERB)->object($member), $after, $limit);
    }

    /**
     * The thanks the member gave, oldest first, withdrawn thanks left out.
     *
     * @throws InvalidKudos when the position is negative or the limit is not 1 to 100
     */
    public function given(ResourceReference $member, int $after = 0, int $limit = 50): ThanksPage
    {
        return $this->page(Filter::all()->member($member)->verbs(self::VERB), $after, $limit);
    }

    /**
     * How many thanks the member gave in the period — from `since`, included, to `until`, excluded —, withdrawn thanks
     * left out: the measure of a badge for generous members. Thanks received are never counted.
     */
    public function givenCount(ResourceReference $member, ?DateTimeImmutable $since = null, ?DateTimeImmutable $until = null): int
    {
        $filter = Filter::all()->member($member)->verbs(self::VERB);

        if ($since instanceof DateTimeImmutable) {
            $filter = $filter->since($since);
        }

        if ($until instanceof DateTimeImmutable) {
            $filter = $filter->until($until);
        }

        return $this->journal->totals($filter)->count;
    }

    private function refusalAt(ResourceReference $giver, ResourceReference $receiver, DateTimeImmutable $now): ?Refusal
    {
        if ($giver->equals($receiver)) {
            return Refusal::Self;
        }

        if (!$this->preferences->accepts($receiver, $giver)) {
            return Refusal::Declined;
        }

        if ($this->givenToday($giver, $now) >= $this->limits->daily) {
            return Refusal::DailyCap;
        }

        $perReceiver = Filter::all()->member($giver)->verbs(self::VERB)->object($receiver)->since($now->sub($this->limits->period));

        if ($this->gestures($perReceiver, $this->limits->perReceiver) >= $this->limits->perReceiver) {
            return Refusal::PairLimit;
        }

        return null;
    }

    private function givenToday(ResourceReference $giver, DateTimeImmutable $now): int
    {
        $start = $this->dayStart($giver, $now);

        return $this->gestures(Filter::all()->member($giver)->verbs(self::VERB)->since($start)->until($start->add(new DateInterval('P1D'))), $this->limits->daily);
    }

    /**
     * The key of thanks: each part written after its length, so that no two lists of parts write the same text.
     *
     * @param list<string> $parts
     */
    private function keyOf(array $parts): IdempotencyKey
    {
        return IdempotencyKey::fromString(self::KEY_PREFIX.hash('sha256', implode('', array_map(static fn (string $part): string => \strlen($part).':'.$part, $parts))));
    }

    private function dayStart(ResourceReference $member, DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->setTimezone($this->timeZones->of($member))->setTime(0, 0);
    }

    /**
     * How many thanks match, withdrawn ones included, counted up to `max`. A cancellation is an entry written after the
     * thanks it cancels, so the first `2 × max` entries hold at least `max` thanks, or every entry that matches.
     */
    private function gestures(Filter $filter, int $max): int
    {
        $thanks = array_filter($this->journal->read(0, 2 * $max, $filter), static fn (Entry $entry): bool => !$entry->isCancellation());

        return min($max, \count($thanks));
    }

    private function thanksEntry(IdempotencyKey|string $key): ?Entry
    {
        $entry = $this->journal->find($key);

        return $entry instanceof Entry && self::VERB === $entry->activity->verb->value ? $entry : null;
    }

    private function cancelled(Entry $entry, bool $byGiver): bool
    {
        // The entry was just found: the journal cancels it, finds it already cancelled, or answers null when another
        // request erased it in between — gone all the same, and nobody is told.
        if (true === $this->journal->cancel($entry->activity->key)?->new) {
            $event = new KudosCancelled($entry->activity->key->value, $entry->activity->member->toString(), $entry->activity->object?->toString(), $byGiver);

            foreach ($this->observers as $observer) {
                $observer->cancelled($event);
            }
        }

        return true;
    }

    private function page(Filter $filter, int $after, int $limit): ThanksPage
    {
        if ($after < 0) {
            throw InvalidKudos::because(\sprintf('A page starts after a position of 0 or more; %d is not one.', $after));
        }

        if ($limit < 1 || $limit > self::MAX_PAGE) {
            throw InvalidKudos::because(\sprintf('A page holds 1 to %d thanks; %d is not a limit.', self::MAX_PAGE, $limit));
        }

        $entries = $this->journal->read($after, $limit, $filter);
        $thanks = [];

        foreach ($entries as $entry) {
            // A cancellation's own key is cancelled too: withdrawn thanks and their cancellations are both left out.
            if (!$this->journal->isCancelled($entry->activity->key)) {
                $thanks[] = Thanks::fromActivity($entry->activity);
            }
        }

        return new ThanksPage($thanks, \count($entries) === $limit ? $entries[$limit - 1]->position : null);
    }
}
