<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Tilttek\SeasonalEvents\Exception\InvalidSeason;

/**
 * Reads seasons from plain arrays — a configuration, a JSON or YAML file —, and writes them back:
 *
 * ```php
 * [
 *     'id' => 'winter-festival',
 *     'timezone' => 'America/Toronto',
 *     'yearly' => ['from' => '2026-12-15', 'days' => 23],          // or 'once' => ['from' => …, 'through' => …]
 *     'decorations' => ['decoration:snowflakes'],
 *     'events' => [
 *         ['id' => 'gift-week', 'from_day' => 8, 'days' => 7, 'announce_days' => 7,
 *          'items' => ['item:wrapped-die'], 'multiplier' => ['ledger' => 'points', 'percent' => 200]],
 *     ],
 * ]
 * ```
 *
 * A key it does not know is refused, so that a misspelt one does not pass for an absent one.
 */
final class SeasonReader
{
    /** The most seasons one list holds: every question of the library reads them all. */
    public const int MAX_SEASONS = 100;

    private const array SEASON_KEYS = ['id', 'timezone', 'once', 'yearly', 'quests', 'items', 'decorations', 'events'];

    private const array EVENT_KEYS = ['id', 'from_day', 'days', 'announce_days', 'quests', 'items', 'decorations', 'multiplier'];

    private const array KINDS = ['quests' => ContentKind::Quest, 'items' => ContentKind::Item, 'decorations' => ContentKind::Decoration];

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $definitions a list of season definitions
     *
     * @return list<Season>
     */
    public static function all(array $definitions): array
    {
        if (!array_is_list($definitions)) {
            throw InvalidSeason::because('The seasons are a list of definitions.');
        }

        if (\count($definitions) > self::MAX_SEASONS) {
            throw InvalidSeason::because(\sprintf('A list holds at most %d seasons; %d were given.', self::MAX_SEASONS, \count($definitions)));
        }

        return array_map(static fn (mixed $definition): Season => self::season(self::map($definition, 'A season')), $definitions);
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    public static function season(array $definition): Season
    {
        self::keys($definition, self::SEASON_KEYS, 'season');
        $id = self::text($definition, 'id', 'A season');
        $timezone = self::text($definition, 'timezone', \sprintf('The season "%s"', $id));

        $schedule = match (true) {
            isset($definition['once']) && !isset($definition['yearly']) => self::once($timezone, self::map($definition['once'], 'The "once" of a season')),
            isset($definition['yearly']) && !isset($definition['once']) => self::yearly($timezone, self::map($definition['yearly'], 'The "yearly" of a season')),
            default => throw InvalidSeason::because(\sprintf('The season "%s" comes "once" or "yearly", one of the two.', $id)),
        };

        $events = self::map($definition['events'] ?? [], 'The events of a season');

        if (!array_is_list($events)) {
            throw InvalidSeason::because('The events of a season are a list.');
        }

        return new Season($id, $schedule, self::contents($definition), array_map(static fn (mixed $event): SeasonalEvent => self::event(self::map($event, 'An event')), $events));
    }

    /**
     * A season written as `season()` reads it.
     *
     * @return array<string, mixed>
     */
    public static function write(Season $season): array
    {
        $schedule = $season->schedule;
        $first = Days::number($schedule->firstDate);
        $written = ['id' => $season->id, 'timezone' => $schedule->timezone->getName()];
        $written += $schedule->isYearly()
            ? ['yearly' => ['from' => $schedule->firstDate, 'days' => $schedule->days, 'rule' => (string) $schedule->rule]]
            : ['once' => ['from' => $schedule->firstDate, 'through' => Days::date($first + $schedule->days - 1)]];

        return $written + self::writeContents($season->contents) + ['events' => array_map(self::writeEvent(...), $season->events)];
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    private static function event(array $definition): SeasonalEvent
    {
        self::keys($definition, self::EVENT_KEYS, 'event');
        $multiplier = null;

        if (isset($definition['multiplier'])) {
            $written = self::map($definition['multiplier'], 'The multiplier of an event');
            self::keys($written, ['ledger', 'percent'], 'multiplier');
            $multiplier = new Multiplier(self::text($written, 'ledger', 'A multiplier'), self::integer($written, 'percent', 'A multiplier'));
        }

        return new SeasonalEvent(
            self::text($definition, 'id', 'An event'),
            isset($definition['from_day']) ? self::integer($definition, 'from_day', 'An event') : 1,
            isset($definition['days']) ? self::integer($definition, 'days', 'An event') : null,
            self::contents($definition),
            $multiplier,
            isset($definition['announce_days']) ? self::integer($definition, 'announce_days', 'An event') : 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function writeEvent(SeasonalEvent $event): array
    {
        $written = ['id' => $event->id, 'from_day' => $event->fromDay];

        if (null !== $event->days) {
            $written['days'] = $event->days;
        }

        $written['announce_days'] = $event->announceDays;

        if ($event->multiplier instanceof Multiplier) {
            $written['multiplier'] = $event->multiplier->toArray();
        }

        return $written + self::writeContents($event->contents);
    }

    /**
     * @param array<array-key, mixed> $once
     */
    private static function once(string $timezone, array $once): SeasonSchedule
    {
        self::keys($once, ['from', 'through'], '"once"');

        return SeasonSchedule::once($timezone, self::text($once, 'from', 'A season'), self::text($once, 'through', 'A season'));
    }

    /**
     * @param array<array-key, mixed> $yearly
     */
    private static function yearly(string $timezone, array $yearly): SeasonSchedule
    {
        self::keys($yearly, ['from', 'days', 'rule'], '"yearly"');

        return SeasonSchedule::yearly(
            $timezone,
            self::text($yearly, 'from', 'A season'),
            self::integer($yearly, 'days', 'A season'),
            isset($yearly['rule']) ? self::text($yearly, 'rule', 'A season') : SeasonSchedule::EACH_YEAR,
        );
    }

    /**
     * @param array<array-key, mixed> $definition
     *
     * @return list<Content>
     */
    private static function contents(array $definition): array
    {
        $contents = [];

        foreach (self::KINDS as $key => $kind) {
            $references = self::map($definition[$key] ?? [], \sprintf('The %s', $key));

            if (!array_is_list($references)) {
                throw InvalidSeason::because(\sprintf('The %s are a list of references.', $key));
            }

            foreach ($references as $reference) {
                if (!\is_string($reference)) {
                    throw InvalidSeason::because(\sprintf('The %s are references written "kind:identifier".', $key));
                }

                try {
                    $contents[] = new Content($kind, ResourceReference::fromString($reference));
                } catch (InvalidResource $invalidResource) {
                    throw InvalidSeason::because($invalidResource->getMessage(), $invalidResource);
                }
            }
        }

        return $contents;
    }

    /**
     * @param list<Content> $contents
     *
     * @return array<string, list<string>>
     */
    private static function writeContents(array $contents): array
    {
        $written = [];

        foreach (self::KINDS as $key => $kind) {
            $references = array_values(array_map(
                static fn (Content $content): string => $content->reference->toString(),
                array_filter($contents, static fn (Content $content): bool => $kind === $content->kind),
            ));

            if ([] !== $references) {
                $written[$key] = $references;
            }
        }

        return $written;
    }

    /**
     * @param array<array-key, mixed> $definition
     * @param list<string>            $known
     */
    private static function keys(array $definition, array $known, string $what): void
    {
        $unknown = array_diff(array_map(strval(...), array_keys($definition)), $known);

        if ([] !== $unknown) {
            throw InvalidSeason::because(\sprintf('A %s has no key "%s"; its keys are "%s".', $what, implode('", "', $unknown), implode('", "', $known)));
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(mixed $value, string $what): array
    {
        if (!\is_array($value)) {
            throw InvalidSeason::because(\sprintf('%s is written as an array.', $what));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    private static function text(array $definition, string $key, string $what): string
    {
        $value = $definition[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidSeason::because(\sprintf('%s has a text "%s".', $what, $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    private static function integer(array $definition, string $key, string $what): int
    {
        $value = $definition[$key] ?? null;

        if (!\is_int($value)) {
            throw InvalidSeason::because(\sprintf('%s has a whole number "%s".', $what, $key));
        }

        return $value;
    }
}
