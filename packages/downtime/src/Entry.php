<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

use Tilttek\Downtime\Exception\DowntimeRefused;
use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Internal\Identifier;

/**
 * One activity a character undertakes between sessions, as written in its log: who chose it, the activity as the rules
 * declared it then, the days spent on it, the roll and the outcome it ended with, and where it stands. The activity is
 * kept whole, so that rules changed later never change an entry. It is checked as a whole, and each change returns a
 * new value.
 */
final readonly class Entry
{
    /** The bounds of a roll, either way. */
    public const int MAX_ROLL = 1_000_000;

    /**
     * @param string      $id        the entry's identifier
     * @param string      $campaign  the identifier of its campaign
     * @param string      $character the identifier of the character
     * @param string      $chosenBy  the identifier of the member who chose it
     * @param string      $system    the identifier of the game system whose rules declare the activity
     * @param Activity    $activity  the activity as the rules declared it when it was chosen
     * @param int         $progress  the days spent, 0 to the days of the activity: fewer while queued, all of them once
     *                               awaiting a roll, resolved or confirmed
     * @param int|null    $roll      the total rolled, `-MAX_ROLL` to `MAX_ROLL`, for a rolled activity with an outcome
     * @param string|null $outcome   the key of an outcome of the activity: required once resolved or confirmed
     * @param int         $version   0 for an entry not kept yet; the store raises it at each write
     *
     * @throws InvalidDowntime when a value is out of its shape or its bounds, or the values do not agree with the stage
     */
    public function __construct(
        public string $id,
        public string $campaign,
        public string $character,
        public string $chosenBy,
        public string $system,
        public Activity $activity,
        public Stage $stage = Stage::Queued,
        public int $progress = 0,
        public ?int $roll = null,
        public ?string $outcome = null,
        public int $version = 0,
    ) {
        Identifier::check($id, 'an entry');
        Identifier::check($campaign, 'a campaign');
        Identifier::check($character, 'a character');
        Identifier::check($chosenBy, 'a member');
        Identifier::check($system, 'a system');

        if ($version < 0) {
            throw InvalidDowntime::because('The version of an entry is 0 or more.');
        }

        $this->checkProgress();
        $this->checkOutcome();
    }

    /**
     * The days still to spend.
     */
    public function remaining(): int
    {
        return $this->activity->days - $this->progress;
    }

    /**
     * What the activity costs over its days, by resource.
     *
     * @return array<string, int>
     */
    public function costs(): array
    {
        return $this->activity->totals();
    }

    /**
     * The outcome the entry ended with, or null before it is resolved.
     */
    public function result(): ?Outcome
    {
        return null === $this->outcome ? null : $this->activity->outcome($this->outcome);
    }

    /**
     * The entry with up to so many more days spent: once all of them are, it awaits its roll.
     *
     * @param int $days 1 or more; the days beyond those remaining are not spent
     *
     * @throws DowntimeRefused when the entry is not queued
     * @throws InvalidDowntime when the days are below 1
     */
    public function advancedBy(int $days): self
    {
        $this->expect(Stage::Queued === $this->stage, 'Only a queued entry advances.');

        if ($days < 1) {
            throw InvalidDowntime::because('An entry advances by 1 day or more.');
        }

        $progress = $this->progress + min($days, $this->remaining());
        $stage = $progress === $this->activity->days ? Stage::AwaitingRoll : Stage::Queued;

        return $this->with($stage, $progress, null, null);
    }

    /**
     * The entry ended with an outcome, and the total rolled for it when it was rolled.
     *
     * @throws DowntimeRefused when the entry does not await its roll
     * @throws InvalidDowntime when the outcome is not one of the activity, or the roll is out of its bounds
     */
    public function resolved(string $outcome, ?int $roll): self
    {
        $this->expect(Stage::AwaitingRoll === $this->stage, 'Only an entry awaiting its roll is resolved.');

        return $this->with(Stage::Resolved, $this->progress, $roll, $outcome);
    }

    /**
     * The entry confirmed by the game master, with its outcome or the one they choose instead.
     *
     * @throws DowntimeRefused when the entry is neither resolved nor, with an outcome chosen, awaiting its roll
     * @throws InvalidDowntime when the outcome is not one of the activity
     */
    public function confirmed(?string $outcome = null): self
    {
        $this->expect(Stage::Resolved === $this->stage || (null !== $outcome && Stage::AwaitingRoll === $this->stage), 'Only a resolved entry, or one awaiting its roll with an outcome chosen, is confirmed.');

        return $this->with(Stage::Confirmed, $this->progress, $this->roll, $outcome ?? $this->outcome);
    }

    /**
     * The entry cancelled, with the days spent, the roll and the outcome it had.
     *
     * @throws DowntimeRefused when it is confirmed or cancelled already
     */
    public function cancelled(): self
    {
        $this->expect($this->stage->isOpen(), 'A confirmed or cancelled entry stays as it is.');

        return $this->with(Stage::Cancelled, $this->progress, $this->roll, $this->outcome);
    }

    /**
     * @throws InvalidDowntime when the version is below 0
     */
    public function withVersion(int $version): self
    {
        return new self($this->id, $this->campaign, $this->character, $this->chosenBy, $this->system, $this->activity, $this->stage, $this->progress, $this->roll, $this->outcome, $version);
    }

    private function with(Stage $stage, int $progress, ?int $roll, ?string $outcome): self
    {
        return new self($this->id, $this->campaign, $this->character, $this->chosenBy, $this->system, $this->activity, $stage, $progress, $roll, $outcome, $this->version);
    }

    /**
     * @throws DowntimeRefused
     */
    private function expect(bool $allowed, string $reason): void
    {
        if (!$allowed) {
            throw DowntimeRefused::because(\sprintf('%s The entry %s is %s.', $reason, $this->id, $this->stage->value));
        }
    }

    /**
     * @throws InvalidDowntime
     */
    private function checkProgress(): void
    {
        $days = $this->activity->days;

        if ($this->progress < 0 || $this->progress > $days) {
            throw InvalidDowntime::because(\sprintf('The days spent on the entry %s are 0 to %d.', $this->id, $days));
        }

        if (Stage::Queued === $this->stage && $this->progress === $days) {
            throw InvalidDowntime::because(\sprintf('A queued entry has days still to spend: %s has none.', $this->id));
        }

        if (Stage::Queued !== $this->stage && Stage::Cancelled !== $this->stage && $this->progress !== $days) {
            throw InvalidDowntime::because(\sprintf('An entry awaiting its roll, resolved or confirmed has spent all its days: %s has not.', $this->id));
        }
    }

    /**
     * @throws InvalidDowntime
     */
    private function checkOutcome(): void
    {
        if (null === $this->outcome) {
            if (Stage::Resolved === $this->stage || Stage::Confirmed === $this->stage) {
                throw InvalidDowntime::because(\sprintf('A resolved or confirmed entry has an outcome: %s has none.', $this->id));
            }

            if (null !== $this->roll) {
                throw InvalidDowntime::because(\sprintf('An entry has a roll only with its outcome: %s has none.', $this->id));
            }

            return;
        }

        if (Stage::Queued === $this->stage || Stage::AwaitingRoll === $this->stage) {
            throw InvalidDowntime::because(\sprintf('A queued entry, or one awaiting its roll, has no outcome yet: %s has one.', $this->id));
        }

        if (!$this->activity->outcome($this->outcome) instanceof Outcome) {
            throw InvalidDowntime::because(\sprintf('The activity %s has no outcome %s.', $this->activity->key, $this->outcome));
        }

        if (null === $this->roll) {
            return;
        }

        if (!$this->activity->resolution->isRolled()) {
            throw InvalidDowntime::because(\sprintf('Only a rolled activity has a roll: %s is not rolled.', $this->activity->key));
        }

        if ($this->roll < -self::MAX_ROLL || $this->roll > self::MAX_ROLL) {
            throw InvalidDowntime::because(\sprintf('A roll is -%1$d to %1$d.', self::MAX_ROLL));
        }
    }
}
