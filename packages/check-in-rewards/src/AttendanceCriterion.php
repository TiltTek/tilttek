<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards;

use Phil\Resource\ResourceReference;
use Tilttek\CheckInRewards\Exception\InvalidCriterion;

/**
 * An attendance rule the application declares, written as `tilttek/badges` and `tilttek/quests` read their criteria:
 * a number of activities of one verb, with the facts their context must hold. The library hands out no badge and
 * opens no quest; the application puts the criterion in the badge or the quest it defines.
 *
 * ```php
 * $catalog->add('con-passport', new CriterionReader()->read(AttendanceCriterion::sessions(5, $event)->toBadgeCriterion()));
 * $quest = $reader->read(['steps' => [AttendanceCriterion::everyDay($event, 3)->toQuestStep('every-day')]]);
 * ```
 *
 * A series of weeks counts the weeks a member came, not weeks in a row: a week missed takes nothing away.
 */
final readonly class AttendanceCriterion
{
    public const int MAX = 1_000_000_000;

    /**
     * @param array<string, string> $context
     */
    private function __construct(
        public string $verb,
        public array $context,
        public int $count,
    ) {
        if ($count < 1 || $count > self::MAX) {
            throw InvalidCriterion::because(\sprintf('An attendance criterion counts 1 to %d; this one counts %d.', self::MAX, $count));
        }
    }

    /**
     * Distinct sessions, tables or stands a member was admitted to — of the event, for the passport of a convention.
     */
    public static function sessions(int $sessions, ?ResourceReference $event = null): self
    {
        return new self(AttendanceVerbs::SESSION, self::of($event), $sessions);
    }

    /**
     * Days of the event a member was admitted on; with the number of days of the event, every day of it.
     */
    public static function everyDay(ResourceReference $event, int $days): self
    {
        return new self(AttendanceVerbs::DAY, self::of($event), $days);
    }

    /**
     * Distinct places a member was admitted at — tables, rooms, stands discovered —, of the event when one is given.
     */
    public static function distinctPlaces(int $places, ?ResourceReference $event = null): self
    {
        return new self(AttendanceVerbs::PLACE, self::of($event), $places);
    }

    /**
     * One place discovered: a member admitted at least once at it, of the event when one is given.
     */
    public static function place(string $place, ?ResourceReference $event = null): self
    {
        if (!Presence::fits($place)) {
            throw InvalidCriterion::because(\sprintf('A place is 1 to %d bytes; this one is %d.', Presence::MAX_CODE_LENGTH, \strlen($place)));
        }

        return new self(AttendanceVerbs::PLACE, [...self::of($event), 'place' => $place], 1);
    }

    /**
     * Weeks a member came, whether in a row or not: a week missed breaks nothing, the series resumes where it was.
     */
    public static function weeks(int $weeks): self
    {
        return new self(AttendanceVerbs::WEEK, [], $weeks);
    }

    /**
     * The criterion as `tilttek/badges` reads it, with its `CriterionReader`.
     *
     * @return array{count: array{verbs: list<string>, context: array<string, string>, times: int}}
     */
    public function toBadgeCriterion(): array
    {
        return ['count' => ['verbs' => [$this->verb], 'context' => $this->context, 'times' => $this->count]];
    }

    /**
     * The criterion as a step of `tilttek/quests`, as its `QuestReader` reads it.
     *
     * @return array{code: string, verbs: list<string>, context: array<string, string>, count: int}
     */
    public function toQuestStep(string $code): array
    {
        return ['code' => $code, 'verbs' => [$this->verb], 'context' => $this->context, 'count' => $this->count];
    }

    /**
     * @return array<string, string>
     */
    private static function of(?ResourceReference $event): array
    {
        if (!$event instanceof ResourceReference) {
            return [];
        }

        if (!Presence::fits($event->toString())) {
            throw InvalidCriterion::because(\sprintf('The reference of an event is at most %d bytes; "%s" is %d.', Presence::MAX_CODE_LENGTH, $event->toString(), \strlen($event->toString())));
        }

        return ['event' => $event->toString()];
    }
}
