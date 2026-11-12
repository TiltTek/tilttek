<?php

declare(strict_types=1);

namespace Tilttek\Weather;

use Symfony\Component\Yaml\Yaml;
use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Loading\Reader;
use Tilttek\Weather\Exception\InvalidClimate;
use Tilttek\Weather\Exception\UnknownClimate;

/**
 * The weather as a table with a memory of `tilttek/generator`, written from declared climates in one language: a
 * generator whose state holds the zone, the season, the condition and the temperature, and lists made from the zones,
 * their seasons and the conditions.
 *
 * Each day, the condition stays, with the weight the season gives it times the zone's persistence, or moves to a
 * neighbour the season brings, with the weight the season gives that neighbour; a condition the season does not bring
 * moves to one of its neighbours the season brings, or else to any condition of the season. The temperature moves by
 * the zone's roll and one degree toward the middle of the season's range moved by the condition, and stays in the
 * range; out of it, after the season changed, it comes back by two degrees a day. The zone and the season have no next
 * template: the application sets them.
 */
final class WeatherTable
{
    /**
     * The degrees a day a temperature out of the season's range comes back by.
     */
    public const int RETURN = 2;

    /**
     * The languages of the generator files the library ships, written from its own climates.
     */
    public const array LOCALES = ['en', 'fr'];

    private function __construct()
    {
    }

    /**
     * The directory of the generator file the library ships in one language — `weather.yaml`, written from
     * `Climates::default()` — for an application that loads its generators from files.
     *
     * @throws UnknownClimate when the library ships no file in this language
     */
    public static function directory(string $locale = 'en'): string
    {
        if (!\in_array($locale, self::LOCALES, true)) {
            throw UnknownClimate::of('language', $locale, self::LOCALES);
        }

        return \dirname(__DIR__).'/generators/'.$locale;
    }

    /**
     * The catalog of the weather: the generator `$name` and its lists `$name.…`.
     *
     * @throws UnknownClimate when the labels are not written in this language
     * @throws InvalidClimate when the name is not a name of `tilttek/generator`
     */
    public static function catalog(Climates $climates, string $locale = 'en', string $name = 'weather'): Catalog
    {
        try {
            return new Reader()->catalog([$name => self::document($climates, $locale, $name)]);
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidClimate::at($name, $invalidDefinition->getMessage());
        }
    }

    /**
     * The same catalog as a YAML document of `tilttek/generator`, for an application that loads its generators from
     * files.
     *
     * @throws UnknownClimate when the labels are not written in this language
     */
    public static function yaml(Climates $climates, string $locale = 'en', string $name = 'weather'): string
    {
        $comment = \sprintf("# The weather written from declared climates by tilttek/weather, in \"%s\": WeatherTable::yaml().\n", $locale);

        $document = self::document($climates, $locale, $name);

        return $comment.Yaml::dump(['lists' => $document['lists']], 3, 2).Yaml::dump(['generators' => $document['generators']], 4, 2);
    }

    /**
     * The document of the catalog, as `tilttek/generator` reads it.
     *
     * @return array{lists: array<string, list<array<string, mixed>>>, generators: array<string, array<string, mixed>>}
     *
     * @throws UnknownClimate when the labels are not written in this language
     */
    public static function document(Climates $climates, string $locale = 'en', string $name = 'weather'): array
    {
        $locale = $climates->locale($locale);
        $lists = [];

        foreach ($climates->conditions() as $condition) {
            $lists[$name.'.condition'][] = ['text' => $condition->id, 'label' => $condition->labels[$locale], 'shift' => $condition->temperature, 'tags' => ['id' => [$condition->id]]];
        }

        foreach ($climates->zones() as $zone) {
            $lists[$name.'.season.'.$zone->id] = [['text' => $zone->firstSeason()]];

            foreach ($zone->seasons as $season => $climate) {
                $suffix = '.'.$zone->id.'.'.$season;
                $lists[$name.'.climate'.$suffix] = [['text' => $season, 'low' => $climate->low, 'high' => $climate->high, 'middle' => $climate->middle(), 'change' => $zone->change]];
                $lists[$name.'.start'.$suffix] = array_map(static fn (string $condition, int $weight): array => ['text' => $condition, 'weight' => $weight], array_keys($climate->conditions), $climate->conditions);
                $lists[$name.'.transition'.$suffix] = self::transitions($climates, $zone, $climate);
            }
        }

        return ['lists' => $lists, 'generators' => [$name => self::generator($name, array_key_first($climates->zones()))]];
    }

    /**
     * The moves of every condition in one season of one zone, tagged with the condition they leave.
     *
     * @return list<array{text: string, weight: int, tags: array{from: list<string>}}>
     */
    private static function transitions(Climates $climates, Zone $zone, SeasonalClimate $climate): array
    {
        $entries = [];

        foreach (array_keys($climates->conditions()) as $from) {
            $targets = [];

            if (isset($climate->conditions[$from])) {
                $targets[$from] = $climate->conditions[$from] * $zone->persistence;
            }

            foreach ($climates->neighbours($from) as $neighbour) {
                if (isset($climate->conditions[$neighbour])) {
                    $targets[$neighbour] = $climate->conditions[$neighbour];
                }
            }

            foreach ([] === $targets ? $climate->conditions : $targets as $to => $weight) {
                $entries[] = ['text' => $to, 'weight' => $weight, 'tags' => ['from' => [$from]]];
            }
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private static function generator(string $name, string $zone): array
    {
        $climate = "{% set c = pick('".$name.".climate.' ~ state.zone ~ '.' ~ state.season) %}";
        $target = "{% set target = c.middle + pick('".$name.".condition', where: {id: state.condition}).shift %}";

        return [
            'state' => [
                'zone' => $zone,
                'season' => "{{ pick('".$name.".season.' ~ state.zone) }}",
                'condition' => [
                    'start' => "{{ pick('".$name.".start.' ~ state.zone ~ '.' ~ state.season) }}",
                    'next' => "{{ pick('".$name.".transition.' ~ state.zone ~ '.' ~ state.season, where: {from: state.condition}) }}",
                ],
                'temperature' => [
                    'start' => $climate.$target.'{{ target > c.high ? c.high : (target < c.low ? c.low : target) }}',
                    'next' => $climate.$target.'{% set before = state.temperature %}'
                        .'{% if before > c.high %}{% set t = before - '.self::RETURN.' < c.high ? c.high : before - '.self::RETURN.' %}'
                        .'{% elseif before < c.low %}{% set t = before + '.self::RETURN.' > c.low ? c.low : before + '.self::RETURN.' %}'
                        .'{% else %}{% set t = before + roll(c.change) + (target > before ? 1 : (target < before ? -1 : 0)) %}'
                        .'{% set t = t > c.high ? c.high : (t < c.low ? c.low : t) %}{% endif %}{{ t }}',
                ],
            ],
            'fields' => [
                'condition' => '{{ state.condition }}',
                'label' => "{{ pick('".$name.".condition', where: {id: state.condition}).label }}",
                'temperature' => '{{ state.temperature }}',
            ],
        ];
    }
}
