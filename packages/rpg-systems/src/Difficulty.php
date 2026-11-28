<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * The difficulty numbers a system declares for one purpose — the classes of a check, the budget of an encounter —: its
 * bands from the easiest, each with its names and its own number when it has one, and tables of whole numbers by label,
 * which the tool reading the record interprets by the record's method. The library reads no meaning into the numbers:
 * it keeps them in their order and within their bounds.
 *
 * ```php
 * new Difficulty('check', Translations::of(['en' => 'Typical difficulty classes']), $source, method: 'check', bands: [
 *     new DifficultyBand('easy', Translations::of(['en' => 'Easy']), 10),
 *     new DifficultyBand('hard', Translations::of(['en' => 'Hard']), 20),
 * ]);
 * new Difficulty('encounter', $names, $source, method: 'budget', bands: $bands, numbers: [
 *     'budgets' => ['1' => ['low' => 50, 'high' => 100]],
 *     'costs' => ['1/2' => 100, '1' => 200],
 * ]);
 * ```
 */
final readonly class Difficulty
{
    /** The most bands of one record. */
    public const int MAX_BANDS = 24;

    /** The most whole numbers the tables of one record hold, all levels together. */
    public const int MAX_NUMBERS = 10_000;

    /** The furthest a number lies from zero, either way. */
    public const int MAX_NUMBER = 1_000_000_000;

    /** The deepest a table nests: a table of rows of numbers, `budgets.1.low`. */
    public const int MAX_DEPTH = 3;

    /** A label of the tables: a level `1`, a challenge `1/8`, a difference `-4`, a name `party-size`. */
    private const string LABEL = '/^[a-z0-9+\/._-]{1,32}$/D';

    /** @var list<DifficultyBand> */
    public array $bands;

    /** @var array<array-key, mixed> the tables, every leaf a whole number */
    public array $numbers;

    /**
     * @param string                  $key     the record's key in its system, `encounter`
     * @param ?string                 $method  how the numbers are read, a key: `check`, `budget`, `level`
     * @param list<DifficultyBand>    $bands   from the easiest, distinct keys
     * @param array<array-key, mixed> $numbers tables by label, each a whole number or a table, three levels at most
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $key,
        public Translations $names,
        public Provenance $provenance,
        public Translations $descriptions = new Translations(),
        public ?string $method = null,
        array $bands = [],
        array $numbers = [],
    ) {
        Text::key($key, 'difficulty numbers');

        if ($names->isEmpty()) {
            throw InvalidRecord::because(\sprintf('The difficulty numbers "%s" have no name.', $key));
        }

        if (null !== $method) {
            Text::key($method, \sprintf('the method of the difficulty numbers "%s"', $key));
        }

        if (\count($bands) > self::MAX_BANDS) {
            throw InvalidRecord::because(\sprintf('The difficulty numbers "%s" hold at most %d bands.', $key, self::MAX_BANDS));
        }

        $keys = [];

        foreach ($bands as $band) {
            if (isset($keys[$band->key])) {
                throw InvalidRecord::because(\sprintf('The difficulty numbers "%s" hold the band "%s" twice.', $key, $band->key));
            }

            $keys[$band->key] = true;
        }

        $count = 0;
        $this->check($numbers, 1, $count);
        $this->bands = $bands;
        $this->numbers = $numbers;
    }

    public function hasBand(string $key): bool
    {
        return array_any($this->bands, static fn (DifficultyBand $band): bool => $band->key === $key);
    }

    /**
     * The band of a key, or null when the record has none of that key.
     */
    public function band(string $key): ?DifficultyBand
    {
        return array_find($this->bands, static fn (DifficultyBand $band): bool => $band->key === $key);
    }

    /**
     * @return list<string> the keys of the bands, from the easiest
     */
    public function bandKeys(): array
    {
        return array_map(static fn (DifficultyBand $band): string => $band->key, $this->bands);
    }

    /**
     * A number or a table found by its labels, `number('budgets', '1', 'low')`; null when there is none.
     *
     * @return int|array<array-key, mixed>|null
     */
    public function number(string|int ...$labels): int|array|null
    {
        $value = $this->numbers;

        foreach ($labels as $label) {
            if (!\is_array($value) || !\array_key_exists($label, $value)) {
                return null;
            }

            $value = $value[$label];
        }

        /** @var int|array<array-key, mixed> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $table
     *
     * @throws InvalidRecord
     */
    private function check(array $table, int $depth, int &$count): void
    {
        foreach ($table as $label => $value) {
            if (1 !== preg_match(self::LABEL, (string) $label)) {
                throw InvalidRecord::because(\sprintf('A label of the difficulty numbers "%s" is 1 to 32 lowercase letters, digits, "+", "/", ".", "_" or "-"; "%s" is not.', $this->key, $label));
            }

            if (\is_array($value) && $depth < self::MAX_DEPTH) {
                $this->check($value, $depth + 1, $count);

                continue;
            }

            if (!\is_int($value) || abs($value) > self::MAX_NUMBER) {
                throw InvalidRecord::because(\sprintf('The difficulty numbers "%s" hold whole numbers within ±%d, in tables %d levels deep at most; "%s" is not one.', $this->key, self::MAX_NUMBER, self::MAX_DEPTH, $label));
            }

            if (++$count > self::MAX_NUMBERS) {
                throw InvalidRecord::because(\sprintf('The difficulty numbers "%s" hold at most %d numbers.', $this->key, self::MAX_NUMBERS));
            }
        }
    }
}
