<?php

declare(strict_types=1);

namespace Tilttek\PlayLog;

use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\PlayLog\Contract\PlayerConsent;
use Tilttek\PlayLog\Contract\PlayObserver;
use Tilttek\PlayLog\Contract\PlayStore;
use Tilttek\PlayLog\Event\PlayCorrected;
use Tilttek\PlayLog\Event\PlayDeleted;
use Tilttek\PlayLog\Event\PlayEvent;
use Tilttek\PlayLog\Event\PlayRecorded;
use Tilttek\PlayLog\Event\SeatLinked;
use Tilttek\PlayLog\Event\SeatPending;
use Tilttek\PlayLog\Event\SeatUnlinked;
use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Exception\PlayConflict;
use Tilttek\PlayLog\Exception\PlayNotFound;
use Tilttek\PlayLog\Exception\PlayRefused;
use Tilttek\PlayLog\Internal\Identifier;

/**
 * The service of the log: it records, corrects and deletes plays, links the seats of members who confirm them, and
 * forgets a member. It reads the play from the store, changes it, keeps it, then tells the observer what happened,
 * each fact once. It keeps no state between calls: everything is in the store.
 *
 * A seat of the member who records the play is confirmed at once; the seat of another member is confirmed when the
 * application's consent allows it, and waits for that member otherwise. The confirmation the caller gives a seat is
 * never read.
 */
final readonly class PlayLog
{
    /** The most plays `plays()` and `forget()` read at once. */
    public const int MAX_READ = 1_000;

    public function __construct(
        private PlayStore $store,
        private Catalog $catalog,
        private ?PlayObserver $observer = null,
        private ?PlayerConsent $consent = null,
    ) {
    }

    /**
     * The play as it is kept: give a reader `Play::seenBy()`.
     *
     * @throws InvalidPlay  when the identifier is not one
     * @throws PlayNotFound when no play has it
     */
    public function play(string $id): Play
    {
        return $this->store->play(Identifier::check($id, 'a play')) ?? throw PlayNotFound::withId($id);
    }

    /**
     * The plays the query matches, the latest first, those whose date is unknown last.
     *
     * @param int $limit 1 to 1000
     *
     * @return iterable<Play>
     *
     * @throws InvalidPlay when the limit is out of its bounds
     */
    public function plays(PlayQuery $query, int $limit): iterable
    {
        return $this->store->plays($query, $this->limit($limit));
    }

    /**
     * Records a new play, at version 0.
     *
     * @throws EntryNotFound when the game is not in the catalogue
     * @throws PlayRefused   when the play is not at version 0, a play has its identifier, or the game is hidden
     * @throws PlayConflict  when a play of its identifier was kept meanwhile
     */
    public function record(Play $play): Play
    {
        if (0 !== $play->version) {
            throw PlayRefused::because('A new play is at version 0.');
        }

        if ($this->store->play($play->id) instanceof Play) {
            throw PlayRefused::because(\sprintf('A play has the identifier %s.', $play->id));
        }

        $this->refuseHidden($play->game);

        return $this->keep(null, $this->linked($play, null), true);
    }

    /**
     * Replaces a play with its correction, made on the version read. The seats the members confirmed stay confirmed.
     *
     * @throws InvalidPlay   when the member is not an identifier
     * @throws PlayNotFound  when no play has the identifier
     * @throws PlayRefused   when the member did not record the play, the correction changes who recorded it, or it
     *                       changes the game for a hidden one
     * @throws EntryNotFound when it changes the game for one the catalogue does not hold
     * @throws PlayConflict  when the play changed since it was read
     */
    public function correct(Play $play, string $by): Play
    {
        $before = $this->play($play->id);
        $this->refuseOthers($before, $by);

        if ($play->recordedBy !== $before->recordedBy) {
            throw PlayRefused::because('A correction keeps the member who recorded the play.');
        }

        if ($play->game !== $before->game) {
            $this->refuseHidden($play->game);
        }

        return $this->keep($before, $this->linked($play, $before), true);
    }

    /**
     * @throws InvalidPlay  when the member is not an identifier
     * @throws PlayNotFound when no play has the identifier
     * @throws PlayRefused  when the member did not record the play
     * @throws PlayConflict when the play changed meanwhile
     */
    public function delete(string $id, string $by): void
    {
        $before = $this->play($id);
        $this->refuseOthers($before, $by);
        $this->store->delete($before);
        $this->tell($before, $before, null, true);
    }

    /**
     * The member confirms their seat: the play counts for them. Confirming it again changes nothing.
     *
     * @throws InvalidPlay  when the member is not an identifier
     * @throws PlayNotFound when no play has the identifier
     * @throws PlayRefused  when the member holds no seat of the play
     * @throws PlayConflict when the play changed meanwhile
     */
    public function confirm(string $id, string $member): Play
    {
        $before = $this->play($id);
        $seat = $this->seatOf($before, $member);

        if ($seat->confirmed) {
            return $before;
        }

        return $this->keep($before, $this->replaced($before, $member, $seat->withConfirmed(true)), false);
    }

    /**
     * The member declines their seat, confirmed or not: it becomes anonymous, its score and its win stay.
     *
     * @throws InvalidPlay  when the member is not an identifier
     * @throws PlayNotFound when no play has the identifier
     * @throws PlayRefused  when the member holds no seat of the play
     * @throws PlayConflict when the play changed meanwhile
     */
    public function decline(string $id, string $member): Play
    {
        $before = $this->play($id);

        return $this->keep($before, $this->replaced($before, $member, $this->seatOf($before, $member)->anonymised()), false);
    }

    /**
     * Forgets a member, up to a number of plays: deletes the plays they recorded, then makes their seats of the others
     * anonymous. It returns how many plays it deleted or changed, at most the limit: call it again until it returns 0.
     *
     * @param int $limit 1 to 1000
     *
     * @throws InvalidPlay  when the member is not an identifier or the limit is out of its bounds
     * @throws PlayConflict when a play changed meanwhile
     */
    public function forget(string $member, int $limit): int
    {
        $done = 0;

        foreach (iterator_to_array($this->store->plays(PlayQuery::recordedBy($member), $this->limit($limit)), false) as $play) {
            $this->store->delete($play);
            $this->tell($play, $play, null, true);
            ++$done;
        }

        $done += $this->anonymise($member, PlayQuery::ofMember($member), $limit - $done);

        return $done + $this->anonymise($member, PlayQuery::pendingFor($member), $limit - $done);
    }

    /**
     * Makes the seat of the member anonymous in the plays of the query, up to the room left, and returns how many.
     */
    private function anonymise(string $member, PlayQuery $query, int $room): int
    {
        if ($room < 1) {
            return 0;
        }

        $done = 0;

        foreach (iterator_to_array($this->store->plays($query, $room), false) as $play) {
            $this->keep($play, $this->replaced($play, $member, $this->seatOf($play, $member)->anonymised()), false);
            ++$done;
        }

        return $done;
    }

    /**
     * @throws InvalidPlay when the limit is out of its bounds
     */
    private function limit(int $limit): int
    {
        if ($limit < 1 || $limit > self::MAX_READ) {
            throw InvalidPlay::because(\sprintf('At most 1 to %d plays are read at once.', self::MAX_READ));
        }

        return $limit;
    }

    /**
     * The play whose seats are confirmed as the log decides: the recorder's at once, those confirmed before, and
     * those the consent allows.
     */
    private function linked(Play $play, ?Play $before): Play
    {
        $seats = [];

        foreach ($play->seats as $seat) {
            $member = $seat->member;
            $seats[] = null === $member ? $seat : $seat->withConfirmed(
                $member === $play->recordedBy
                || true === $before?->seat($member)?->confirmed
                || true === $this->consent?->allows($member, $play->recordedBy),
            );
        }

        return $play->withSeats($seats);
    }

    private function replaced(Play $play, string $member, Seat $seat): Play
    {
        $seats = [];

        foreach ($play->seats as $kept) {
            $seats[] = $member === $kept->member ? $seat : $kept;
        }

        return $play->withSeats($seats);
    }

    /**
     * @throws InvalidPlay when the member is not an identifier
     * @throws PlayRefused when they hold no seat of the play
     */
    private function seatOf(Play $play, string $member): Seat
    {
        return $play->seat(Identifier::check($member, 'a member'))
            ?? throw PlayRefused::because(\sprintf('The member %s holds no seat of the play %s.', $member, $play->id));
    }

    /**
     * @throws InvalidPlay when the member is not an identifier
     * @throws PlayRefused when they did not record the play
     */
    private function refuseOthers(Play $play, string $by): void
    {
        if (Identifier::check($by, 'a member') !== $play->recordedBy) {
            throw PlayRefused::because(\sprintf('Only the member who recorded the play %s changes it.', $play->id));
        }
    }

    /**
     * @throws EntryNotFound when the game is not in the catalogue
     * @throws PlayRefused   when it is hidden
     */
    private function refuseHidden(string $game): void
    {
        if ($this->catalog->get($game)->hidden) {
            throw PlayRefused::because(\sprintf('The game %s is hidden in the catalogue.', $game));
        }
    }

    /**
     * @param bool $fact whether the play itself was recorded or corrected, besides its seats
     */
    private function keep(?Play $before, Play $after, bool $fact): Play
    {
        $kept = $this->store->save($after);
        $this->tell($kept, $before, $kept, $fact);

        return $kept;
    }

    /**
     * @param Play $play the play kept, or the play deleted
     */
    private function tell(Play $play, ?Play $before, ?Play $after, bool $fact): void
    {
        foreach ($this->events($play, $before, $after, $fact) as $event) {
            $this->observer?->notify($event);
        }
    }

    /**
     * @return list<PlayEvent>
     */
    private function events(Play $play, ?Play $before, ?Play $after, bool $fact): array
    {
        $events = [];

        if ($fact) {
            $events[] = match (true) {
                !$before instanceof Play => new PlayRecorded($play->id, $play->game, $play->recordedBy),
                !$after instanceof Play => new PlayDeleted($play->id, $play->game, $play->recordedBy),
                default => new PlayCorrected($play->id, $play->game, $play->recordedBy),
            };
        }

        $linkedBefore = $before?->linkedMembers() ?? [];
        $linkedAfter = $after?->linkedMembers() ?? [];

        foreach (array_diff($linkedAfter, $linkedBefore) as $member) {
            $events[] = new SeatLinked($play->id, $play->game, $member);
        }

        foreach (array_diff($this->pending($after), $this->pending($before)) as $member) {
            $events[] = new SeatPending($play->id, $play->game, $member);
        }

        foreach (array_diff($linkedBefore, $linkedAfter) as $member) {
            $events[] = new SeatUnlinked($play->id, ($before ?? $play)->game, $member);
        }

        return $events;
    }

    /**
     * @return list<string>
     */
    private function pending(?Play $play): array
    {
        $members = [];

        foreach ($play->seats ?? [] as $seat) {
            if (null !== $seat->member && !$seat->confirmed) {
                $members[] = $seat->member;
            }
        }

        return $members;
    }
}
