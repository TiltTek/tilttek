<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use Tilttek\SeasonalEvents\Exception\InvalidSeason;

/**
 * An event inside a season: from one of its days for a number of days — to the season's end by default —, with the
 * quests, items and decorations it adds and, when it is announced ahead, a multiplier of gains. An event with a
 * multiplier is announced at least one day before it starts: the members know of it before it begins, and nothing
 * multiplies by surprise.
 */
final readonly class SeasonalEvent
{
    public const int MAX_CONTENTS = 100;

    public const int MAX_ANNOUNCEMENT_DAYS = 90;

    /**
     * @param string        $id           stable, unique in its season: 1 to 64 lower-case letters, digits, "_", "." or "-"
     * @param int           $fromDay      the day of the season it starts on, 1 for the first
     * @param int|null      $days         how many days it lasts, to the season's end when null
     * @param list<Content> $contents     what it adds while it lasts, 0 to 100
     * @param int           $announceDays how many days before it starts it is announced, 0 to 90
     */
    public function __construct(
        public string $id,
        public int $fromDay = 1,
        public ?int $days = null,
        public array $contents = [],
        public ?Multiplier $multiplier = null,
        public int $announceDays = 0,
    ) {
        $reason = match (true) {
            1 !== preg_match(Season::IDENTIFIER, $id) => \sprintf('An event identifier is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "%s" is not.', $id),
            $fromDay < 1 || $fromDay > SeasonSchedule::MAX_DAYS => \sprintf('An event starts on day 1 to %d of its season; %d is not one.', SeasonSchedule::MAX_DAYS, $fromDay),
            null !== $days && ($days < 1 || $days > SeasonSchedule::MAX_DAYS) => \sprintf('An event lasts 1 to %d days; %d is not one.', SeasonSchedule::MAX_DAYS, $days),
            \count($contents) > self::MAX_CONTENTS => \sprintf('An event brings at most %d contents.', self::MAX_CONTENTS),
            $announceDays < 0 || $announceDays > self::MAX_ANNOUNCEMENT_DAYS => \sprintf('An event is announced 0 to %d days ahead; %d is not.', self::MAX_ANNOUNCEMENT_DAYS, $announceDays),
            $multiplier instanceof Multiplier && $announceDays < 1 => \sprintf('The event "%s" multiplies gains: it is announced at least one day before it starts.', $id),
            default => null,
        };

        if (null !== $reason) {
            throw InvalidSeason::because($reason);
        }
    }
}
