<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Difficulty;

use Tilttek\Encounter\Contract\DifficultyRules;
use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Exception\UnknownSystem;
use Tilttek\RpgSystems\Difficulty;
use Tilttek\RpgSystems\GameSystem;

/**
 * Reads the rules of difficulty a game system declares into `BudgetRules` or `LevelRules`: from the difficulty numbers
 * of a `tilttek/rpg-systems` system, or from plain data — decoded from JSON or YAML — for an application that brings
 * its own. Every key is required but `multipliers`; a key it does not know, a value of another type or out of its
 * bounds is refused.
 *
 *     Declaration::fromSystem($system);   // the difficulty numbers `encounter` of the system
 *
 *     ['method' => 'budget', 'system' => 'my-system', 'bands' => ['low', 'high'],
 *      'budgets' => ['1' => ['low' => 50, 'high' => 100]], 'costs' => ['1/2' => 25], 'multipliers' => [['from' => 2, 'percent' => 150]]]
 *
 *     ['method' => 'level', 'system' => 'my-system', 'partySize' => 4, 'budgets' => ['low' => 60, 'high' => 120],
 *      'adjustments' => ['low' => 15, 'high' => 30], 'costs' => ['-2' => 20, '-1' => 30, '0' => 40, '1' => 60]]
 */
final class Declaration
{
    private const array BUDGET_KEYS = ['method', 'system', 'bands', 'budgets', 'costs', 'multipliers'];

    private const array LEVEL_KEYS = ['method', 'system', 'partySize', 'budgets', 'adjustments', 'costs'];

    private const string DIFFERENCE = '/^-?[0-9]{1,2}$/D';

    /** The key of the difficulty numbers of an encounter in a system. */
    public const string RECORD = 'encounter';

    /** The tables the difficulty numbers of each method hold, and those of them that may be left out. */
    private const array TABLES = [
        'budget' => [['budgets', 'costs', 'multipliers'], ['multipliers']],
        'level' => [['party-size', 'budgets', 'adjustments', 'costs'], []],
    ];

    private function __construct()
    {
    }

    /**
     * The rules of difficulty a system's difficulty numbers declare: their method, `budget` or `level`, their bands
     * from the easiest, and their tables — `budgets`, `costs` and the optional `multipliers` (a percent by the number
     * of adversaries it holds from) for a budget; `party-size`, `budgets`, `adjustments` and `costs` for a level.
     *
     * @param string $record the key of the difficulty numbers, `encounter` by default
     *
     * @throws UnknownSystem    when the system declares no difficulty numbers of the key
     * @throws InvalidEncounter when the numbers are not rules of difficulty
     */
    public static function fromSystem(GameSystem $system, string $record = self::RECORD): DifficultyRules
    {
        if (!$system->hasDifficulty($record)) {
            throw UnknownSystem::of($system->key);
        }

        $numbers = $system->difficulty($record);
        $method = $numbers->method ?? '';
        [$tables, $optional] = self::TABLES[$method] ?? throw InvalidEncounter::because(\sprintf('The difficulty numbers %s of %s declare the method "budget" or "level".', $record, $system->key));
        foreach (array_keys($numbers->numbers) as $table) {
            if (!\in_array($table, $tables, true)) {
                throw InvalidEncounter::because(\sprintf('The difficulty numbers %s of %s have no table %s.', $record, $system->key, $table));
            }
        }

        foreach (array_diff($tables, $optional) as $table) {
            if (!\array_key_exists($table, $numbers->numbers)) {
                throw InvalidEncounter::because(\sprintf('The difficulty numbers %s of %s declare their %s.', $record, $system->key, $table));
            }
        }

        return self::read('budget' === $method ? self::budgetData($system->key, $numbers) : self::levelData($system->key, $numbers));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidEncounter when the data is not rules of difficulty
     */
    public static function read(array $data): DifficultyRules
    {
        return match ($data['method'] ?? null) {
            'budget' => self::budget($data),
            'level' => self::level($data),
            default => throw InvalidEncounter::because('Rules of difficulty declare their method: "budget" or "level".'),
        };
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidEncounter
     */
    private static function budgetData(string $system, Difficulty $numbers): array
    {
        $multipliers = [];
        foreach (self::map($numbers->numbers['multipliers'] ?? [], 'multipliers', BudgetRules::MAX_MULTIPLIERS) as $from => $percent) {
            $multipliers[] = ['from' => (int) $from, 'percent' => $percent];
        }

        return [
            'method' => 'budget',
            'system' => $system,
            'bands' => $numbers->bandKeys(),
            'budgets' => $numbers->numbers['budgets'],
            'costs' => $numbers->numbers['costs'],
            'multipliers' => $multipliers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function levelData(string $system, Difficulty $numbers): array
    {
        return [
            'method' => 'level',
            'system' => $system,
            'partySize' => $numbers->numbers['party-size'],
            'budgets' => self::byBand($numbers, $numbers->numbers['budgets']),
            'adjustments' => self::byBand($numbers, $numbers->numbers['adjustments']),
            'costs' => $numbers->numbers['costs'],
        ];
    }

    /**
     * A table by band, in the order of the bands, so that the rules read the bands the record declares.
     *
     * @throws InvalidEncounter when the table does not give exactly the bands of the record
     */
    private static function byBand(Difficulty $numbers, mixed $table): mixed
    {
        if (!\is_array($table)) {
            return $table;
        }

        if (\count($table) !== \count($numbers->bands)) {
            throw InvalidEncounter::because(\sprintf('The tables of the difficulty numbers %s give each of their bands, %s, and no other.', $numbers->key, implode(', ', $numbers->bandKeys())));
        }

        $ordered = [];
        foreach ($numbers->bandKeys() as $band) {
            $ordered[$band] = $table[$band] ?? throw InvalidEncounter::because(\sprintf('The difficulty numbers %s give nothing to the band %s.', $numbers->key, $band));
        }

        return $ordered;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidEncounter
     */
    private static function budget(array $data): BudgetRules
    {
        self::keys($data, self::BUDGET_KEYS, ['multipliers']);
        $bands = self::strings($data['bands'], 'bands', Assessment::MAX_BANDS);
        $budgets = [];
        foreach (self::map($data['budgets'], 'budgets', BudgetRules::MAX_RATINGS) as $rating => $row) {
            $budgets[$rating] = self::integers($row, 'a budget', Assessment::MAX_BANDS);
        }

        $multipliers = [];
        foreach (self::map($data['multipliers'] ?? [], 'multipliers', BudgetRules::MAX_MULTIPLIERS, true) as $multiplier) {
            $multiplier = self::map($multiplier, 'a multiplier', 2);
            self::keys($multiplier, ['from', 'percent']);
            $multipliers[] = new Multiplier(self::integer($multiplier['from'], 'from'), self::integer($multiplier['percent'], 'percent'));
        }

        return new BudgetRules(
            self::string($data['system'], 'system'),
            $bands,
            $budgets,
            self::integers($data['costs'], 'costs', BudgetRules::MAX_RATINGS),
            $multipliers,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidEncounter
     */
    private static function level(array $data): LevelRules
    {
        self::keys($data, self::LEVEL_KEYS);
        $costs = [];
        foreach (self::integers($data['costs'], 'costs', 2 * LevelRules::MAX_DIFFERENCE + 1) as $difference => $cost) {
            if (1 !== preg_match(self::DIFFERENCE, (string) $difference)) {
                throw InvalidEncounter::because('The costs are by a whole number of levels.');
            }

            $costs[(int) $difference] = $cost;
        }

        $budgets = self::integers($data['budgets'], 'budgets', Assessment::MAX_BANDS);
        $adjustments = self::integers($data['adjustments'], 'adjustments', Assessment::MAX_BANDS);

        return new LevelRules(self::string($data['system'], 'system'), $budgets, self::integer($data['partySize'], 'partySize'), $adjustments, $costs);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $known
     * @param list<string>            $optional
     *
     * @throws InvalidEncounter
     */
    private static function keys(array $data, array $known, array $optional = []): void
    {
        foreach ($data as $key => $value) {
            if (!\in_array($key, $known, true)) {
                throw InvalidEncounter::because(\sprintf('Rules of difficulty have no key %s.', $key));
            }
        }

        foreach (array_diff($known, $optional) as $key) {
            if (!\array_key_exists($key, $data)) {
                throw InvalidEncounter::because(\sprintf('Rules of difficulty declare their %s.', $key));
            }
        }
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidEncounter
     */
    private static function map(mixed $value, string $what, int $max, bool $list = false): array
    {
        if (!\is_array($value) || \count($value) > $max || ($list && !array_is_list($value))) {
            throw InvalidEncounter::because(\sprintf('The %s of rules of difficulty are %s of %d entries at most.', $what, $list ? 'a list' : 'a map', $max));
        }

        return $value;
    }

    /**
     * @return array<array-key, int>
     *
     * @throws InvalidEncounter
     */
    private static function integers(mixed $value, string $what, int $max): array
    {
        return array_map(static fn (mixed $entry): int => self::integer($entry, $what), self::map($value, $what, $max));
    }

    /**
     * @return list<string>
     *
     * @throws InvalidEncounter
     */
    private static function strings(mixed $value, string $what, int $max): array
    {
        return array_map(static fn (mixed $entry): string => self::string($entry, $what), array_values(self::map($value, $what, $max, true)));
    }

    /**
     * @throws InvalidEncounter
     */
    private static function integer(mixed $value, string $what): int
    {
        return \is_int($value) ? $value : throw InvalidEncounter::because(\sprintf('The %s of rules of difficulty are whole numbers.', $what));
    }

    /**
     * @throws InvalidEncounter
     */
    private static function string(mixed $value, string $what): string
    {
        return \is_string($value) ? $value : throw InvalidEncounter::because(\sprintf('The %s of rules of difficulty is text.', $what));
    }
}
