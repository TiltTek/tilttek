<?php

declare(strict_types=1);

namespace Tilttek\Rating;

use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Tilttek\Rating\Contract\PlaySource;
use Tilttek\Rating\Contract\RatingStore;
use Tilttek\Rating\Contract\RatingSystem;
use Tilttek\Rating\Event\PeakReached;
use Tilttek\Rating\Event\RatingChanged;
use Tilttek\Rating\Exception\InvalidRating;
use Tilttek\Rating\Exception\RatingConflict;
use Tilttek\Rating\Exception\RatingRefused;
use Tilttek\Rating\Exception\ResultAlreadyRecorded;

/**
 * The ratings of the members, game by game, in one rating system.
 *
 * `record()` rates a result once — a friendly one changes nothing, and a result recorded already is refused — and
 * dispatches `Event\RatingChanged`, then `Event\PeakReached` for a member above the highest score they reached;
 * `replay()` computes the ratings of a game again from its source of results, from the start, with the same values
 * every time. A rating is provisional while its member has played few rated games, or while the system finds it
 * uncertain; `isProvisional()` says so, and `standings()` may leave such ratings out. A rating is private until its
 * member makes it public with `publish()`; `standings()` lists the public ones only. `export()` and `forget()` give and
 * erase every rating of a member.
 *
 * The events of a result are kept with it in the store, in the same write as the ratings, and each stays there until
 * it was dispatched without an exception: a listener that throws leaves it there, the other events are dispatched all
 * the same, and the first exception is thrown again at the end. `record()` called again with the result, or
 * `deliver()`, dispatches what is left, without rating the result again. An event may thus be dispatched more than
 * once: the listeners are idempotent, keyed by the event's `key()`.
 *
 * The service keeps nothing between calls: the store holds the state.
 */
final readonly class Ratings
{
    /** The longest standings. */
    public const int MAX_STANDINGS = 1000;

    /** The rated games a rating is provisional for, by default. */
    public const int PROVISIONAL_GAMES = 10;

    /**
     * @param int $celebrateAfter   the rated games a member plays before a new highest score is celebrated, 1 to 1 000
     * @param int $provisionalGames the rated games a rating stays provisional for, 0 to 1 000
     *
     * @throws InvalidRating when the games before a celebration or of a provisional rating are out of range
     */
    public function __construct(
        private RatingStore $store,
        private RatingSystem $system,
        private ?EventDispatcherInterface $events = null,
        private ?PlaySource $source = null,
        private int $celebrateAfter = 5,
        private int $provisionalGames = self::PROVISIONAL_GAMES,
    ) {
        if ($celebrateAfter < 1 || $celebrateAfter > 1000) {
            throw InvalidRating::because('A new highest score is celebrated after 1 to 1000 rated games.');
        }

        if ($provisionalGames < 0 || $provisionalGames > 1000) {
            throw InvalidRating::because('A rating is provisional for 0 to 1000 rated games.');
        }
    }

    /**
     * Rates a result and keeps the new ratings of its members, all at once, with the result counted for its game and
     * its events, then dispatches them. A friendly result changes no rating and gives no change. A result counted
     * already whose events are not all delivered is not rated again: its events left are dispatched, and no change is
     * given.
     *
     * @return list<RatingChange> in the order of the members of the result
     *
     * @throws ResultAlreadyRecorded when the result is counted already and its events are all delivered; nothing is kept
     * @throws RatingRefused         when the ratings of the game were computed by another system
     * @throws RatingConflict        when the ratings of a member changed since they were read; nothing is kept
     */
    public function record(GameResult $result): array
    {
        if ($result->friendly) {
            return [];
        }

        if ($this->store->hasRecorded($result->game, $result->id)) {
            $left = $this->store->undelivered($result->game, $result->id);

            if ([] === $left) {
                throw ResultAlreadyRecorded::of($result->game, $result->id);
            }

            $this->dispatch($result->game, $result->id, $left);

            return [];
        }

        $current = $this->current($result->game, $result->members());
        $rated = $this->system->rate($result, $current);
        $kept = [];
        $changes = [];

        foreach ($result->members() as $member) {
            $before = $current[$member];
            $score = $this->system->score($rated[$member]);
            $kept[] = $before->after($rated[$member], $score, $result->id);
            $changes[] = new RatingChange($member, $before->score, $score, $before->peaksAt($score) && $before->games + 1 >= $this->celebrateAfter);
        }

        $events = [];

        foreach ($this->events instanceof EventDispatcherInterface ? $changes : [] as $change) {
            $events[] = new RatingChanged($result->game, $change->member, $result->id);

            if ($change->peak) {
                $events[] = new PeakReached($result->game, $change->member, $result->id);
            }
        }

        $this->store->save($kept, $result->id, $events);
        $this->dispatch($result->game, $result->id, $events);

        return $changes;
    }

    /**
     * Dispatches again the events of the result not delivered yet, to resume after a listener failed.
     *
     * @return int how many were delivered
     */
    public function deliver(string $game, string $result): int
    {
        return $this->dispatch($game, $result, $this->store->undelivered($game, $result));
    }

    /**
     * Computes every rating of the game again, from the first result of its source to the last, and replaces the ones
     * kept; each member keeps the visibility they chose. Nothing is dispatched, and the events of the game's results not
     * delivered yet are cancelled: a replay is a computation again, whose ratings may no longer give them.
     *
     * @return int the rated results replayed
     *
     * @throws RatingRefused when no source of results is configured
     * @throws InvalidRating when a result is of another game, out of order, or given twice
     */
    public function replay(string $game): int
    {
        Identifier::check($game, 'game');

        if (!$this->source instanceof PlaySource) {
            throw RatingRefused::because('A replay reads the results from a source of results, and none is configured.');
        }

        $before = $this->store->ofGame($game);
        $visibility = [];

        foreach ($before as $kept) {
            $visibility[$kept->member] = $kept->visibility;
        }

        $ratings = [];
        $seen = [];
        $rated = [];
        $last = null;

        foreach ($this->source->results($game) as $result) {
            if ($result->game !== $game) {
                throw InvalidRating::because(\sprintf('The source gives the result %s of the game %s when asked for the game %s.', $result->id, $result->game, $game));
            }

            if ($last instanceof DateTimeImmutable && $result->playedAt < $last) {
                throw InvalidRating::because(\sprintf('The source gives the result %s out of the order the games were played in.', $result->id));
            }

            if (isset($seen[$result->id])) {
                throw InvalidRating::because(\sprintf('The source gives the result %s twice.', $result->id));
            }

            $seen[$result->id] = true;
            $last = $result->playedAt;

            if ($result->friendly) {
                continue;
            }

            $current = [];

            foreach ($result->members() as $member) {
                $current[$member] = $ratings[$member] ?? MemberRating::initial($game, $member, $this->system)->withVisibility($visibility[$member] ?? Visibility::Private);
            }

            foreach ($this->system->rate($result, $current) as $member => $rating) {
                $ratings[$member] = $current[$member]->after($rating, $this->system->score($rating), $result->id);
            }

            $rated[] = $result->id;
        }

        foreach ($before as $kept) {
            if (!isset($ratings[$kept->member]) && Visibility::Public === $kept->visibility) {
                $ratings[$kept->member] = MemberRating::initial($game, $kept->member, $this->system)->withVisibility(Visibility::Public);
            }
        }

        $this->store->replace($game, array_values($ratings), $rated);

        return \count($rated);
    }

    /**
     * The member's rating of the game: the initial one, private, before a rated game.
     *
     * @throws RatingRefused when the ratings of the game were computed by another system
     *
     * @phpstan-impure
     */
    public function rating(string $game, string $member): MemberRating
    {
        return $this->current($game, [$member])[$member];
    }

    /**
     * The public ratings of the game with at least one rated game, the highest score first, then by member; without
     * `$provisional`, the provisional ones are left out.
     *
     * @return list<MemberRating>
     *
     * @throws InvalidRating when the limit is not 1 to 1 000
     *
     * @phpstan-impure
     */
    public function standings(string $game, int $limit = 100, bool $provisional = true): array
    {
        Identifier::check($game, 'game');

        if ($limit < 1 || $limit > self::MAX_STANDINGS) {
            throw InvalidRating::because(\sprintf('The standings list 1 to %d members.', self::MAX_STANDINGS));
        }

        if ($provisional) {
            return $this->store->standings($game, $limit, 1);
        }

        $minGames = max(1, $this->provisionalGames);

        for ($read = $limit;; $read = min(self::MAX_STANDINGS, $read * 2)) {
            $found = $this->store->standings($game, $read, $minGames);
            $settled = array_filter($found, fn (MemberRating $rating): bool => !$this->system->isProvisional($rating->rating));

            if (\count($settled) >= $limit || \count($found) < $read || self::MAX_STANDINGS === $read) {
                return \array_slice($settled, 0, $limit);
            }
        }
    }

    /**
     * Whether the rating is provisional: fewer rated games than the service asks for, or uncertain by the system's own
     * measure. A page marks it, as a question mark beside the score.
     *
     * @throws RatingRefused when the rating was computed by another system
     */
    public function isProvisional(MemberRating $rating): bool
    {
        if ($rating->system !== $this->system->id()) {
            throw RatingRefused::because(\sprintf('The rating of the member %s for the game %s was computed by the system %s, not %s.', $rating->member, $rating->game, $rating->system, $this->system->id()));
        }

        return $rating->games < $this->provisionalGames || $this->system->isProvisional($rating->rating);
    }

    /**
     * Makes the member's rating of the game public, on the member's own choice: it appears in the standings.
     *
     * @throws RatingConflict when the rating changed since it was read
     */
    public function publish(string $game, string $member): MemberRating
    {
        return $this->show($game, $member, Visibility::Public);
    }

    /**
     * Makes the member's rating of the game private again: only the member sees it.
     *
     * @throws RatingConflict when the rating changed since it was read
     */
    public function hide(string $game, string $member): MemberRating
    {
        return $this->show($game, $member, Visibility::Private);
    }

    /**
     * Every rating of the member, whatever the game: what the member is given on request.
     *
     * @return list<MemberRating>
     *
     * @phpstan-impure
     */
    public function export(string $member): array
    {
        return $this->store->ofMember(Identifier::check($member, 'member'));
    }

    /**
     * Erases every rating of the member. The results stay in their source, where the member's erasure is the
     * source's; a replay after it rates the member again only if the source still names them.
     */
    public function forget(string $member): void
    {
        $this->store->forget(Identifier::check($member, 'member'));
    }

    private function show(string $game, string $member, Visibility $visibility): MemberRating
    {
        $rating = $this->rating($game, $member);

        if ($visibility === $rating->visibility) {
            return $rating;
        }

        $shown = $rating->withVisibility($visibility);
        $this->store->save([$shown]);

        return $shown->withVersion($shown->version + 1);
    }

    /**
     * The rating of each member, the initial one for a member without one.
     *
     * @param list<string> $members
     *
     * @return array<string, MemberRating>
     */
    private function current(string $game, array $members): array
    {
        Identifier::check($game, 'game');
        $found = $this->store->find($game, $members);
        $current = [];

        foreach ($members as $member) {
            $current[$member] = $found[$member] ?? MemberRating::initial($game, Identifier::check($member, 'member'), $this->system);

            if ($current[$member]->system !== $this->system->id()) {
                throw RatingRefused::because(\sprintf('The ratings of the game %s were computed by the system %s; replay the game to rate it with the system %s.', $game, $current[$member]->system, $this->system->id()));
            }
        }

        return $current;
    }

    /**
     * Dispatches the events, marks delivered those dispatched without an exception, and throws the first exception
     * again once every event was tried.
     *
     * @param list<RatingChanged|PeakReached> $events
     *
     * @return int how many were delivered
     */
    private function dispatch(string $game, string $result, array $events): int
    {
        $dispatcher = $this->events;

        if (!$dispatcher instanceof EventDispatcherInterface) {
            return 0;
        }

        $keys = [];
        $failure = null;

        foreach ($events as $event) {
            try {
                $dispatcher->dispatch($event);
            } catch (Throwable $thrown) {
                $failure ??= $thrown;

                continue;
            }

            $keys[] = $event->key();
        }

        if ([] !== $keys) {
            $this->store->markDelivered($game, $result, ...$keys);
        }

        if ($failure instanceof Throwable) {
            throw $failure;
        }

        return \count($keys);
    }
}
