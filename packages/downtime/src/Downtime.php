<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

use Tilttek\Downtime\Contract\DowntimeObserver;
use Tilttek\Downtime\Contract\EntryStore;
use Tilttek\Downtime\Contract\Roller;
use Tilttek\Downtime\Contract\Roster;
use Tilttek\Downtime\Contract\RuleBook;
use Tilttek\Downtime\Event\ActivityCancelled;
use Tilttek\Downtime\Event\ActivityChosen;
use Tilttek\Downtime\Event\ActivityCompleted;
use Tilttek\Downtime\Event\ActivityConfirmed;
use Tilttek\Downtime\Event\ActivityProgressed;
use Tilttek\Downtime\Event\ActivityResolved;
use Tilttek\Downtime\Event\DowntimeEvent;
use Tilttek\Downtime\Exception\DowntimeConflict;
use Tilttek\Downtime\Exception\DowntimeRefused;
use Tilttek\Downtime\Exception\EntryNotFound;
use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Exception\UnknownActivity;
use Tilttek\Downtime\Internal\Identifier;

/**
 * The service of the time between sessions: a player chooses an activity the rules of the system declare for their
 * character, which waits in the character's queue; the game master advances the campaign by days, spent on each
 * character's queue in the order chosen; an activity whose days are spent ends with its outcome, rolled by the roller
 * or recorded from the table; the game master confirms it, or cancels it. It reads from the store, changes, keeps, then
 * tells the observer what happened, each fact once. It keeps no state between calls.
 */
final readonly class Downtime
{
    /** The most entries one call reads. */
    public const int MAX_READ = 1_000;

    /** The most open entries of one character. */
    public const int MAX_QUEUE = 8;

    /** The most days one advance spends. */
    public const int MAX_ADVANCE = 365;

    public function __construct(
        private EntryStore $store,
        private RuleBook $rules,
        private Roster $roster,
        private ?Roller $roller = null,
        private ?DowntimeObserver $observer = null,
    ) {
    }

    /**
     * @throws InvalidDowntime when the identifier is not one
     * @throws EntryNotFound   when no entry has it
     */
    public function entry(string $id): Entry
    {
        return $this->store->entry(Identifier::check($id, 'an entry')) ?? throw EntryNotFound::withId($id);
    }

    /**
     * The log of a character, in the order its entries were chosen, as its player or the game master reads it.
     *
     * @param int $limit 1 to 1000
     *
     * @return list<Entry>
     *
     * @throws InvalidDowntime when an identifier is not one, or the limit is out of its bounds
     * @throws DowntimeRefused when the member neither plays the character nor runs the campaign
     */
    public function log(string $campaign, string $character, string $by, int $limit = self::MAX_READ): array
    {
        if ($limit < 1 || $limit > self::MAX_READ) {
            throw InvalidDowntime::because(\sprintf('A read of entries is of 1 to %d.', self::MAX_READ));
        }

        $this->atTable($campaign, $character, $by);

        return $this->store->entriesOf($campaign, $character, $limit);
    }

    /**
     * Writes an activity the rules of the system declare in the log of the character, after the open entries of its
     * queue.
     *
     * @throws InvalidDowntime  when an identifier or a key is not one
     * @throws DowntimeRefused  when the member neither plays the character nor runs the campaign, an entry has the
     *                          identifier, or the queue of the character is full
     * @throws UnknownActivity  when the rules of the system declare no activity of the key
     * @throws DowntimeConflict when an entry of the identifier was kept meanwhile
     */
    public function choose(string $id, string $campaign, string $character, string $system, string $activity, string $by): Entry
    {
        Identifier::check($id, 'an entry');
        Identifier::check($system, 'a system');
        Identifier::key($activity, 'an activity');
        $this->atTable($campaign, $character, $by);

        if ($this->store->entry($id) instanceof Entry) {
            throw DowntimeRefused::because(\sprintf('An entry has the identifier %s.', $id));
        }

        $declared = $this->rules->activity($system, $activity) ?? throw UnknownActivity::in($system, $activity);

        if (\count($this->store->openOf($campaign, $character, self::MAX_QUEUE)) >= self::MAX_QUEUE) {
            throw DowntimeRefused::because(\sprintf('The character %s has %d open entries already.', $character, self::MAX_QUEUE));
        }

        [$kept] = $this->store->save([new Entry($id, $campaign, $character, $by, $system, $declared)]);
        $this->tell(new ActivityChosen($kept->id, $kept->campaign, $kept->character));

        return $kept;
    }

    /**
     * Spends so many days of the campaign on the queue of each character, in the order chosen: the days an entry does
     * not need go to the next one. An entry whose days are all spent ends with its outcome when its resolution is
     * fixed, or when a roller rolls its dice; otherwise it awaits the roll from the table. Every entry is kept, or none.
     *
     * @param int $days 1 to 365
     *
     * @return list<Entry> the entries changed, in the order chosen
     *
     * @throws InvalidDowntime  when an identifier is not one, or the days are out of their bounds
     * @throws DowntimeRefused  when the member does not run the campaign, or it has more queued entries than one call
     *                          reads
     * @throws DowntimeConflict when an entry changed meanwhile; nothing is kept
     */
    public function advance(string $campaign, int $days, string $by): array
    {
        if ($days < 1 || $days > self::MAX_ADVANCE) {
            throw InvalidDowntime::because(\sprintf('An advance spends 1 to %d days.', self::MAX_ADVANCE));
        }

        $this->gameMaster($campaign, $by);
        $queued = $this->store->queuedIn($campaign, self::MAX_READ + 1);

        if (\count($queued) > self::MAX_READ) {
            throw DowntimeRefused::because(\sprintf('The campaign %s has more than %d queued entries.', $campaign, self::MAX_READ));
        }

        $left = [];
        $changed = [];

        foreach ($queued as $entry) {
            $budget = $left[$entry->character] ?? $days;

            if (0 === $budget) {
                continue;
            }

            $left[$entry->character] = $budget - min($budget, $entry->remaining());
            $changed[] = $this->settled($entry->advancedBy($budget));
        }

        $kept = $this->store->save($changed);

        foreach ($kept as $entry) {
            $this->tell(match ($entry->stage) {
                Stage::Queued => new ActivityProgressed($entry->id, $entry->campaign, $entry->character),
                Stage::AwaitingRoll => new ActivityCompleted($entry->id, $entry->campaign, $entry->character),
                default => new ActivityResolved($entry->id, $entry->campaign, $entry->character),
            });
        }

        return $kept;
    }

    /**
     * Records the total rolled at the table for an entry awaiting its roll, which ends with the outcome of its band.
     *
     * @throws EntryNotFound    when no entry has the identifier
     * @throws DowntimeRefused  when the member neither plays the character nor runs the campaign, or the entry does not
     *                          await its roll
     * @throws InvalidDowntime  when the total is out of its bounds
     * @throws DowntimeConflict when the entry changed meanwhile
     */
    public function record(string $id, int $roll, string $by): Entry
    {
        $entry = $this->entry($id);
        $this->atTable($entry->campaign, $entry->character, $by);

        return $this->keep($entry->resolved($entry->activity->resolution->outcomeFor($roll), $roll), ActivityResolved::class);
    }

    /**
     * Confirms the outcome of a resolved entry, or the outcome the game master chooses instead: the application applies
     * its effects.
     *
     * @throws EntryNotFound    when no entry has the identifier
     * @throws DowntimeRefused  when the member does not run the campaign, or the entry is neither resolved nor, with an
     *                          outcome chosen, awaiting its roll
     * @throws InvalidDowntime  when the outcome chosen is not one of the activity
     * @throws DowntimeConflict when the entry changed meanwhile
     */
    public function confirm(string $id, string $by, ?string $outcome = null): Entry
    {
        $entry = $this->entry($id);
        $this->gameMaster($entry->campaign, $by);

        return $this->keep($entry->confirmed($outcome), ActivityConfirmed::class);
    }

    /**
     * Cancels an open entry: its player cancels it while it is queued, the game master at any open stage.
     *
     * @throws EntryNotFound    when no entry has the identifier
     * @throws DowntimeRefused  when the member neither plays the character nor runs the campaign, the player cancels an
     *                          entry no longer queued, or the entry is confirmed or cancelled already
     * @throws DowntimeConflict when the entry changed meanwhile
     */
    public function cancel(string $id, string $by): Entry
    {
        $entry = $this->entry($id);
        $isGameMaster = $this->atTable($entry->campaign, $entry->character, $by);

        if (!$isGameMaster && Stage::Queued !== $entry->stage && $entry->stage->isOpen()) {
            throw DowntimeRefused::because(\sprintf('Only the game master cancels an entry no longer queued: %s is %s.', $entry->id, $entry->stage->value));
        }

        return $this->keep($entry->cancelled(), ActivityCancelled::class);
    }

    /**
     * The entry ended with its outcome when it awaits a roll the service can make.
     */
    private function settled(Entry $entry): Entry
    {
        if (Stage::AwaitingRoll !== $entry->stage) {
            return $entry;
        }

        $resolution = $entry->activity->resolution;

        if (null === $resolution->dice) {
            return $entry->resolved($resolution->outcomeFor(0), null);
        }

        if (!$this->roller instanceof Roller) {
            return $entry;
        }

        $roll = max(-Entry::MAX_ROLL, min(Entry::MAX_ROLL, $this->roller->roll($resolution->dice)));

        return $entry->resolved($resolution->outcomeFor($roll), $roll);
    }

    /**
     * Whether the member runs the campaign; they play the character otherwise.
     *
     * @throws InvalidDowntime
     * @throws DowntimeRefused
     */
    private function atTable(string $campaign, string $character, string $by): bool
    {
        Identifier::check($campaign, 'a campaign');
        Identifier::check($character, 'a character');
        Identifier::check($by, 'a member');

        if ($this->roster->isGameMaster($campaign, $by)) {
            return true;
        }

        if (!$this->roster->plays($campaign, $character, $by)) {
            throw DowntimeRefused::because(\sprintf('Only the player of the character %s, or the game master of the campaign %s, does that.', $character, $campaign));
        }

        return false;
    }

    /**
     * @throws InvalidDowntime
     * @throws DowntimeRefused
     */
    private function gameMaster(string $campaign, string $by): void
    {
        Identifier::check($campaign, 'a campaign');
        Identifier::check($by, 'a member');

        if (!$this->roster->isGameMaster($campaign, $by)) {
            throw DowntimeRefused::because(\sprintf('Only the game master of the campaign %s does that.', $campaign));
        }
    }

    /**
     * @param class-string<ActivityResolved|ActivityConfirmed|ActivityCancelled> $event
     *
     * @throws DowntimeConflict
     */
    private function keep(Entry $entry, string $event): Entry
    {
        [$kept] = $this->store->save([$entry]);
        $this->tell(new $event($kept->id, $kept->campaign, $kept->character));

        return $kept;
    }

    private function tell(DowntimeEvent $event): void
    {
        $this->observer?->notify($event);
    }
}
