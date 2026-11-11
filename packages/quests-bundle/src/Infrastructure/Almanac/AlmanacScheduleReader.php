<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Almanac;

use DateTimeZone;
use Override;
use Phil\Almanac\Definition;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Rule\RuleParser;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Schedule\ScheduleReader;

/**
 * Reads the `recurs` key of a quest's definition: the rule of the day it opens on, in the words of `phil/almanac`, and
 * how long it lasts — so many days, or until the day of another rule:
 *
 * ```yaml
 * recurs: { from: 'easter - 2 days', until: 'easter + 1 day' }
 * recurs: { from: '10-25', days: 7 }
 * ```
 */
final readonly class AlmanacScheduleReader implements ScheduleReader
{
    public const string KEY = 'recurs';

    #[Override]
    public function keys(): array
    {
        return [self::KEY];
    }

    #[Override]
    public function read(array $definition, DateTimeZone $timezone): ?AlmanacSchedule
    {
        if (!\array_key_exists(self::KEY, $definition)) {
            return null;
        }

        $recurs = $definition[self::KEY];

        if (!\is_array($recurs) || !\is_string($recurs['from'] ?? null) || [] !== array_diff(array_keys($recurs), ['from', 'until', 'days'])) {
            throw InvalidQuest::because('A quest that comes back recurs from a rule, until another rule or for so many days.');
        }

        $until = $recurs['until'] ?? null;
        $days = $recurs['days'] ?? 1;

        if ((null !== $until && !\is_string($until)) || !\is_int($days)) {
            throw InvalidQuest::because('A quest that comes back lasts until a rule, or for a number of days.');
        }

        try {
            return new AlmanacSchedule(
                new Definition('quest', 'Quest', RuleParser::parse($recurs['from']), null === $until ? null : RuleParser::parse($until), $days),
                $timezone,
            );
        } catch (InvalidRule $invalidRule) {
            throw InvalidQuest::because($invalidRule->getMessage());
        }
    }
}
