<?php

declare(strict_types=1);

namespace Tilttek\GameNight;

use Closure;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameNight\Contract\NightObserver;
use Tilttek\GameNight\Contract\NightStore;
use Tilttek\GameNight\Event\NightCancelled;
use Tilttek\GameNight\Event\NightEvent;
use Tilttek\GameNight\Event\NightFull;
use Tilttek\GameNight\Event\NightPublished;
use Tilttek\GameNight\Event\SeatOffered;
use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\Exception\NightConflict;
use Tilttek\GameNight\Exception\NightNotFound;
use Tilttek\GameNight\Exception\NightRefused;
use Tilttek\GameNight\Vote\VoteResult;

/**
 * The service of the nights: planning, tables, publication, sign-ups, seats, the vote and cancellation. It reads the
 * night from the store, changes it, keeps it, then tells the observer what happened, each fact once. Sign-ups, seats
 * and votes close when the night starts. It keeps no state between calls: everything is in the store.
 *
 * Every method that changes a night raises `NightNotFound` when no night has the identifier, and `NightConflict` when
 * the night changed since it was read.
 */
final readonly class GameNights
{
    public function __construct(
        private NightStore $store,
        private ClockInterface $clock,
        private Catalog $catalog,
        private ?NightObserver $observer = null,
    ) {
    }

    /**
     * Plans a night, as a draft.
     *
     * @param list<Table> $tables
     *
     * @throws InvalidNight  when a value is out of its shape or its bounds
     * @throws NightRefused  when the night starts in the past
     * @throws NightConflict when a night has the identifier
     */
    public function plan(
        string $id,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        string $venue,
        string $host,
        array $tables = [],
    ): GameNight {
        $night = new GameNight($id, $startsAt, $endsAt, $venue, $host, $tables);
        $this->refuseWhenStarted($night);

        return $this->store->save($night);
    }

    /**
     * @throws NightNotFound when no night has the identifier
     */
    public function get(string $id): GameNight
    {
        return $this->store->night($id);
    }

    /**
     * Adds a table, or replaces the table of the same identifier; members waiting take the seats it adds.
     *
     * @throws NightRefused when the night is cancelled, or the table has fewer seats than the places taken at it
     */
    public function addTable(string $night, Table $table): GameNight
    {
        return $this->change($night, static fn (GameNight $kept): GameNight => $kept->withTable($table));
    }

    /**
     * @throws NightRefused when the night is cancelled, the table is not one of it, or the other tables do not seat
     *                      the confirmed places
     */
    public function removeTable(string $night, string $table): GameNight
    {
        return $this->change($night, static fn (GameNight $kept): GameNight => $kept->withoutTable($table));
    }

    /**
     * Opens the night to sign-ups.
     *
     * @throws NightRefused when the night is not a draft, has no seat, or has started
     */
    public function publish(string $night): GameNight
    {
        return $this->change($night, function (GameNight $kept): GameNight {
            $this->refuseWhenStarted($kept);

            return $kept->published();
        });
    }

    /**
     * Cancels the night for good. The members signed up are told once, from the event.
     *
     * @throws NightRefused when the night is already cancelled, or has ended
     */
    public function cancel(string $night): GameNight
    {
        return $this->change($night, function (GameNight $kept): GameNight {
            if ($kept->endsAt <= $this->clock->now()) {
                throw NightRefused::because('The night has ended.');
            }

            return $kept->cancelled();
        });
    }

    /**
     * Signs a member up, with a guest or alone. The sign-up holds its places when they are free and nobody waits, and
     * waits on the waiting list otherwise.
     *
     * @throws InvalidNight when the member is not an identifier
     * @throws NightRefused when the night is not published, has started, the member is signed up, or the waiting list
     *                      is full
     */
    public function register(string $night, string $member, bool $guest = false): Registration
    {
        $kept = $this->changeBeforeStart($night, static fn (GameNight $night): GameNight => $night->registered($member, $guest));

        return $kept->signedUp($member);
    }

    /**
     * Withdraws a member's sign-up and vote: the places they free go to the members waiting, who are told once.
     *
     * @throws NightRefused when the night is not published, has started, or the member is not signed up
     */
    public function withdraw(string $night, string $member): GameNight
    {
        return $this->changeBeforeStart($night, static fn (GameNight $kept): GameNight => $kept->withdrawn($member));
    }

    /**
     * Seats a confirmed member, and their guest, at a table, or nowhere with `null`.
     *
     * @throws NightRefused when the night is not published, has started, the member is not confirmed, or the table is
     *                      not one of the night or has not the free seats
     */
    public function seat(string $night, string $member, ?string $table): GameNight
    {
        return $this->changeBeforeStart($night, static fn (GameNight $kept): GameNight => $kept->seated($member, $table));
    }

    /**
     * Proposes games of the catalogue to the vote, in place of those proposed before; the votes for a game no longer
     * proposed are dropped.
     *
     * @param list<string> $games at most 30, each once
     *
     * @throws InvalidNight  when an identifier is not one, a game is there twice, or there are too many
     * @throws EntryNotFound when a game is not in the catalogue
     * @throws NightRefused  when the night is cancelled, has started, or a game is hidden in the catalogue
     */
    public function propose(string $night, array $games): GameNight
    {
        if (\count($games) > GameNight::MAX_CANDIDATES) {
            throw InvalidNight::because(\sprintf('A night proposes at most %d games to the vote.', GameNight::MAX_CANDIDATES));
        }

        foreach ($games as $game) {
            if ($this->catalog->get($game)->hidden) {
                throw NightRefused::because(\sprintf('The game %s is hidden in the catalogue.', $game));
            }
        }

        return $this->changeBeforeStart($night, static fn (GameNight $kept): GameNight => $kept->withCandidates($games));
    }

    /**
     * Records a member's vote for a game proposed, in place of their earlier vote: one vote per member.
     *
     * @throws NightRefused when the night is not published, has started, the member is not signed up, or the game is
     *                      not proposed
     */
    public function vote(string $night, string $member, string $game): GameNight
    {
        return $this->changeBeforeStart($night, static fn (GameNight $kept): GameNight => $kept->voted($member, $game));
    }

    /**
     * @throws NightRefused when the night is not published, or has started
     */
    public function withdrawVote(string $night, string $member): GameNight
    {
        return $this->changeBeforeStart($night, static fn (GameNight $kept): GameNight => $kept->unvoted($member));
    }

    /**
     * @throws NightNotFound when no night has the identifier
     */
    public function result(string $night): VoteResult
    {
        return $this->store->night($night)->voteResult();
    }

    /**
     * @param Closure(GameNight): GameNight $change
     */
    private function changeBeforeStart(string $night, Closure $change): GameNight
    {
        return $this->change($night, function (GameNight $kept) use ($change): GameNight {
            $this->refuseWhenStarted($kept);

            return $change($kept);
        });
    }

    /**
     * @param Closure(GameNight): GameNight $change
     */
    private function change(string $id, Closure $change): GameNight
    {
        $before = $this->store->night($id);
        $after = $this->store->save($change($before));

        foreach ($this->events($before, $after) as $event) {
            $this->observer?->notify($event);
        }

        return $after;
    }

    /**
     * @return list<NightEvent>
     */
    private function events(GameNight $before, GameNight $after): array
    {
        $events = [];

        if (NightStatus::Published === $after->status && NightStatus::Draft === $before->status) {
            $events[] = new NightPublished($after->id);
        }

        if (NightStatus::Cancelled === $after->status) {
            $events[] = new NightCancelled($after->id);
        }

        foreach ($after->promotedSince($before) as $member) {
            $events[] = new SeatOffered($after->id, $member);
        }

        if (NightStatus::Published === $after->status && $after->capacity() > 0 && $after->isFull() && !$before->isFull()) {
            $events[] = new NightFull($after->id);
        }

        return $events;
    }

    private function refuseWhenStarted(GameNight $night): void
    {
        if ($night->startsAt <= $this->clock->now()) {
            throw NightRefused::because('The night has started.');
        }
    }
}
