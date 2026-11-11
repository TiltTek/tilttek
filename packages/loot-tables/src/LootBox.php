<?php

declare(strict_types=1);

namespace Tilttek\LootTables;

use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Pool;
use Tilttek\LootTables\Exception\InvalidLoot;

/**
 * What a product rolls: its tables, in order, each rolled once with its own die. Six tables of six entries roll 6d6;
 * a d20 table and two d8 tables roll 1d20, 1d8 and 1d8. A table may come back more than once.
 */
final readonly class LootBox
{
    /** The most tables: the expressions `tilttek/dice` rolls together by default. */
    public const int MAX_TABLES = 10;

    /**
     * @var non-empty-list<LootTable>
     */
    public array $tables;

    /**
     * @param string          $key    1 to 64 lowercase letters, digits, "_" or "-", starting with a letter or a digit
     * @param string          $name   1 to 200 characters of UTF-8, not blank
     * @param list<LootTable> $tables 1 to 10, in the order they are rolled
     *
     * @throws InvalidLoot
     */
    public function __construct(
        public string $key,
        public string $name,
        array $tables,
    ) {
        if (1 !== preg_match(LootEntry::KEY_PATTERN, $key)) {
            throw InvalidLoot::because(\sprintf('"%s" is not the key of a box: 1 to 64 lowercase letters, digits, "_" or "-".', $key));
        }

        LootEntry::checkName($name, \sprintf('The name of the box %s', $key), LootTable::MAX_NAME);
        if ([] === $tables || \count($tables) > self::MAX_TABLES) {
            throw InvalidLoot::because(\sprintf('The box %s has %d tables; a box has 1 to %d.', $key, \count($tables), self::MAX_TABLES));
        }

        $this->tables = $tables;
    }

    /**
     * A box of one table: a text drawn from a list.
     */
    public static function single(LootTable $table): self
    {
        return new self($table->key, $table->name, [$table]);
    }

    /**
     * The sides of each die, in the order of the tables.
     *
     * @return non-empty-list<int>
     */
    public function dice(): array
    {
        return array_map(static fn (LootTable $table): int => $table->sides(), $this->tables);
    }

    /**
     * The dice of the box as `tilttek/dice` rolls them: one instruction per table, `1d6, 1d6, 1d20`.
     */
    public function pool(): Pool
    {
        return new Pool(...array_map(static fn (int $sides): Dice => new Dice(1, new StandardDie($sides)), $this->dice()));
    }
}
