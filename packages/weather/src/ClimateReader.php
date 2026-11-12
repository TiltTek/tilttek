<?php

declare(strict_types=1);

namespace Tilttek\Weather;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Tilttek\Weather\Exception\InvalidClimate;

/**
 * Reads climates from a YAML document — the format of `data/climates.yaml` — or from the array a JSON or YAML document
 * decodes to. Every problem names its place: `zones.arid.seasons.hot.high: …`.
 *
 * ```yaml
 * locales: [en, fr]
 * conditions:
 *   clear: { labels: { en: clear skies, fr: ciel dégagé }, temperature: 2, next_to: [fair] }
 *   fair: { labels: { en: fair, fr: beau temps } }
 * seasons:
 *   summer: { labels: { en: summer, fr: été } }
 * zones:
 *   temperate:
 *     labels: { en: temperate, fr: tempéré }
 *     persistence: 3
 *     change: 1d5-3
 *     seasons:
 *       summer: { low: 14, high: 30, conditions: { clear: 5, fair: 5 } }
 * ```
 */
final readonly class ClimateReader
{
    /**
     * The longest document, in bytes.
     */
    public const int LENGTH = 1_048_576;

    /**
     * @throws InvalidClimate
     */
    public function file(string $path): Climates
    {
        $contents = is_file($path) && is_readable($path) ? file_get_contents($path, length: self::LENGTH + 1) : false;

        if (false === $contents) {
            throw InvalidClimate::at($path, 'the file cannot be read');
        }

        return $this->read($contents, $path);
    }

    /**
     * @throws InvalidClimate
     */
    public function read(string $yaml, string $source = 'climates'): Climates
    {
        if (\strlen($yaml) > self::LENGTH) {
            throw InvalidClimate::at($source, \sprintf('a document holds at most %d bytes', self::LENGTH));
        }

        try {
            $document = Yaml::parse($yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $parseException) {
            throw InvalidClimate::at($source, $parseException->getMessage());
        }

        return $this->climates($this->map('document', $document));
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @throws InvalidClimate
     */
    public function climates(array $document): Climates
    {
        $unknown = array_diff(array_keys($document), ['locales', 'conditions', 'seasons', 'zones']);

        if ([] !== $unknown) {
            throw InvalidClimate::at(implode(', ', $unknown), 'not a key of climates: "locales", "conditions", "seasons" or "zones"');
        }

        $conditions = [];

        foreach ($this->map('conditions', $document['conditions'] ?? null) as $id => $condition) {
            $place = 'conditions.'.$id;
            $condition = $this->map($place, $condition);
            $this->keys($place, $condition, ['labels', 'temperature', 'next_to']);
            $conditions[] = new Condition((string) $id, $this->map($place.'.labels', $condition['labels'] ?? null), $this->integer($place.'.temperature', $condition['temperature'] ?? 0), $this->texts($place.'.next_to', $condition['next_to'] ?? []));
        }

        $seasons = [];

        foreach ($this->map('seasons', $document['seasons'] ?? null) as $id => $season) {
            $season = $this->map('seasons.'.$id, $season);
            $this->keys('seasons.'.$id, $season, ['labels']);
            $seasons[] = new Season((string) $id, $this->map('seasons.'.$id.'.labels', $season['labels'] ?? null));
        }

        $zones = [];

        foreach ($this->map('zones', $document['zones'] ?? null) as $id => $zone) {
            $zones[] = $this->zone('zones.'.$id, (string) $id, $this->map('zones.'.$id, $zone));
        }

        return new Climates($this->texts('locales', $document['locales'] ?? null), $conditions, $seasons, $zones);
    }

    /**
     * @param array<array-key, mixed> $zone
     *
     * @throws InvalidClimate
     */
    private function zone(string $place, string $id, array $zone): Zone
    {
        $this->keys($place, $zone, ['labels', 'persistence', 'change', 'seasons']);
        $seasons = [];

        foreach ($this->map($place.'.seasons', $zone['seasons'] ?? null) as $season => $climate) {
            $at = $place.'.seasons.'.$season;
            $climate = $this->map($at, $climate);
            $this->keys($at, $climate, ['low', 'high', 'conditions']);
            $seasons[] = new SeasonalClimate((string) $season, $this->integer($at.'.low', $climate['low'] ?? null), $this->integer($at.'.high', $climate['high'] ?? null), $this->map($at.'.conditions', $climate['conditions'] ?? null), $at);
        }

        $change = $zone['change'] ?? '1d5-3';

        if (!\is_string($change)) {
            throw InvalidClimate::at($place.'.change', 'the change of the temperature is a roll of tilttek/dice, such as "1d5-3"');
        }

        return new Zone($id, $this->map($place.'.labels', $zone['labels'] ?? null), $seasons, $this->integer($place.'.persistence', $zone['persistence'] ?? 3), $change);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidClimate
     */
    private function map(string $place, mixed $value): array
    {
        if (!\is_array($value) || [] === $value || array_is_list($value)) {
            throw InvalidClimate::at($place, 'a map of names to values is expected');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $map
     * @param list<string>            $keys
     *
     * @throws InvalidClimate
     */
    private function keys(string $place, array $map, array $keys): void
    {
        $unknown = array_diff(array_keys($map), $keys);

        if ([] !== $unknown) {
            throw InvalidClimate::at($place.'.'.reset($unknown), \sprintf('not a key here: "%s"', implode('", "', $keys)));
        }
    }

    /**
     * @throws InvalidClimate
     */
    private function integer(string $place, mixed $value): int
    {
        return \is_int($value) ? $value : throw InvalidClimate::at($place, 'a whole number is expected');
    }

    /**
     * @return list<string>
     *
     * @throws InvalidClimate
     */
    private function texts(string $place, mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value) || [] !== array_filter($value, static fn (mixed $text): bool => !\is_string($text))) {
            throw InvalidClimate::at($place, 'a list of texts is expected');
        }

        /** @var list<string> $value */
        return $value;
    }
}
