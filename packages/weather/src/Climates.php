<?php

declare(strict_types=1);

namespace Tilttek\Weather;

use Closure;
use Tilttek\Weather\Exception\InvalidClimate;
use Tilttek\Weather\Exception\UnknownClimate;

/**
 * The declared climates of a world: the languages of their labels, the weather conditions and their neighbours, the
 * seasons, and the zones with what each season brings. Every reference is checked when they are built: a neighbour, a
 * season or a condition named but not declared, or a label missing for one of the languages, is an `InvalidClimate`.
 */
final readonly class Climates
{
    /**
     * The most conditions, seasons or zones.
     */
    public const int DEFINITIONS = 64;

    /**
     * The most languages.
     */
    public const int LOCALES = 8;

    /**
     * The most moves between conditions the weather tables of the climates hold, every zone and season together: the
     * conditions a condition may turn into, itself included, for each condition of each season of each zone.
     */
    public const int TRANSITIONS = 20_000;

    /**
     * @var non-empty-list<string>
     */
    public array $locales;

    /**
     * @var non-empty-array<string, Condition>
     */
    private array $conditions;

    /**
     * @var non-empty-array<string, Season>
     */
    private array $seasons;

    /**
     * @var non-empty-array<string, Zone>
     */
    private array $zones;

    /**
     * @var array<string, list<string>> by condition, its neighbours both ways, in the order of the conditions
     */
    private array $neighbours;

    /**
     * @param list<string>    $locales    the languages every label is written in
     * @param list<Condition> $conditions
     * @param list<Season>    $seasons
     * @param list<Zone>      $zones
     *
     * @throws InvalidClimate
     */
    public function __construct(array $locales, array $conditions, array $seasons, array $zones)
    {
        $this->locales = $this->locales($locales);
        $this->conditions = $this->index('conditions', $conditions, static fn (Condition $condition): array => [$condition->id, $condition->labels]);
        $this->seasons = $this->index('seasons', $seasons, static fn (Season $season): array => [$season->id, $season->labels]);
        $this->zones = $this->index('zones', $zones, static fn (Zone $zone): array => [$zone->id, $zone->labels]);
        $this->neighbours = $this->connect();

        foreach ($this->zones as $zone) {
            foreach ($zone->seasons as $season => $climate) {
                $place = 'zones.'.$zone->id.'.seasons.'.$season;
                $this->declared($place, 'season', $season, $this->seasons);

                foreach (array_keys($climate->conditions) as $condition) {
                    $this->declared($place.'.conditions', 'condition', $condition, $this->conditions);
                }
            }
        }

        $this->bounded();
    }

    /**
     * The climates the library ships: sixteen conditions, eight seasons, and five zones — temperate, boreal,
     * mediterranean, arid and tropical — in English and in French.
     */
    public static function default(): self
    {
        return new ClimateReader()->file(self::file());
    }

    /**
     * The file of the climates the library ships, to copy and adapt.
     */
    public static function file(): string
    {
        return \dirname(__DIR__).'/data/climates.yaml';
    }

    /**
     * @return non-empty-array<string, Condition> by identifier, in their order
     */
    public function conditions(): array
    {
        return $this->conditions;
    }

    /**
     * @return non-empty-array<string, Season> by identifier, in their order
     */
    public function seasons(): array
    {
        return $this->seasons;
    }

    /**
     * @return non-empty-array<string, Zone> by identifier, in their order
     */
    public function zones(): array
    {
        return $this->zones;
    }

    /**
     * @throws UnknownClimate
     */
    public function condition(string $id): Condition
    {
        return $this->conditions[$id] ?? throw UnknownClimate::of('condition', $id, array_keys($this->conditions));
    }

    /**
     * @throws UnknownClimate
     */
    public function season(string $id): Season
    {
        return $this->seasons[$id] ?? throw UnknownClimate::of('season', $id, array_keys($this->seasons));
    }

    /**
     * @throws UnknownClimate
     */
    public function zone(string $id): Zone
    {
        return $this->zones[$id] ?? throw UnknownClimate::of('zone', $id, array_keys($this->zones));
    }

    /**
     * What a season brings to a zone.
     *
     * @throws UnknownClimate when the zone is not declared or does not have the season
     */
    public function climate(string $zone, string $season): SeasonalClimate
    {
        $seasons = $this->zone($zone)->seasons;

        return $seasons[$season] ?? throw UnknownClimate::of('season of the zone "'.$zone.'"', $season, array_keys($seasons));
    }

    /**
     * The conditions a condition may turn into besides itself, and that may turn into it: those it names and those that
     * name it, in the order of the conditions.
     *
     * @return list<string>
     *
     * @throws UnknownClimate
     */
    public function neighbours(string $condition): array
    {
        return $this->neighbours[$this->condition($condition)->id];
    }

    /**
     * @throws UnknownClimate when the labels are not written in this language
     */
    public function locale(string $locale): string
    {
        return \in_array($locale, $this->locales, true) ? $locale : throw UnknownClimate::of('language', $locale, $this->locales);
    }

    /**
     * @param list<string> $locales
     *
     * @return non-empty-list<string>
     *
     * @throws InvalidClimate
     */
    private function locales(array $locales): array
    {
        if ([] === $locales || \count($locales) > self::LOCALES) {
            throw InvalidClimate::at('locales', \sprintf('the labels are written in 1 to %d languages', self::LOCALES));
        }

        if (\count(array_unique($locales)) !== \count($locales)) {
            throw InvalidClimate::at('locales', 'a language is named once');
        }

        Rules::labels('locales', array_fill_keys($locales, 'label'));

        return $locales;
    }

    /**
     * The definitions by identifier, each with a label in every language.
     *
     * @template T of object
     *
     * @param list<T>                                          $definitions
     * @param Closure(T): array{string, array<string, string>} $describe    the identifier and the labels of a definition
     *
     * @return non-empty-array<string, T>
     *
     * @throws InvalidClimate
     */
    private function index(string $kind, array $definitions, Closure $describe): array
    {
        if ([] === $definitions || \count($definitions) > self::DEFINITIONS) {
            throw InvalidClimate::at($kind, \sprintf('the climates declare 1 to %d %s', self::DEFINITIONS, $kind));
        }

        $byId = [];

        foreach ($definitions as $definition) {
            [$id, $labels] = $describe($definition);

            if (isset($byId[$id])) {
                throw InvalidClimate::at($kind.'.'.$id, 'an identifier is declared once');
            }

            $missing = array_diff($this->locales, array_keys($labels));

            if ([] !== $missing) {
                throw InvalidClimate::at($kind.'.'.$id.'.labels', \sprintf('no label in "%s"', implode('", "', $missing)));
            }

            $byId[$id] = $definition;
        }

        return $byId;
    }

    /**
     * The neighbours of every condition, both ways.
     *
     * @return array<string, list<string>>
     *
     * @throws InvalidClimate when a neighbour is not declared
     */
    private function connect(): array
    {
        $linked = array_fill_keys(array_keys($this->conditions), []);

        foreach ($this->conditions as $condition) {
            foreach ($condition->neighbours as $neighbour) {
                $this->declared('conditions.'.$condition->id.'.next_to', 'condition', $neighbour, $this->conditions);
                $linked[$condition->id][$neighbour] = true;
                $linked[$neighbour][$condition->id] = true;
            }
        }

        return array_map(fn (array $neighbours): array => array_values(array_filter(array_keys($this->conditions), static fn (string $id): bool => ($neighbours[$id] ?? false))), $linked);
    }

    /**
     * Refuses climates whose weather tables would hold more than `TRANSITIONS` moves, before anything builds them: each
     * bound alone allows millions of moves, which a document of a few hundred kilobytes would make a process build.
     *
     * @throws InvalidClimate
     */
    private function bounded(): void
    {
        $moves = 0;

        foreach ($this->zones as $zone) {
            foreach ($zone->seasons as $climate) {
                foreach ($this->neighbours as $from => $neighbours) {
                    $targets = \count(array_intersect_key($climate->conditions, [$from => true] + array_flip($neighbours)));
                    $moves += 0 === $targets ? \count($climate->conditions) : $targets;
                }

                if ($moves > self::TRANSITIONS) {
                    throw InvalidClimate::at('zones', \sprintf('the weather tables of the climates hold at most %d moves between conditions, every zone and season together', self::TRANSITIONS));
                }
            }
        }
    }

    /**
     * @param array<string, object> $declared
     *
     * @throws InvalidClimate
     */
    private function declared(string $place, string $kind, string $id, array $declared): void
    {
        if (!isset($declared[$id])) {
            throw InvalidClimate::at($place, \sprintf('the %s "%s" is not declared', $kind, $id));
        }
    }
}
