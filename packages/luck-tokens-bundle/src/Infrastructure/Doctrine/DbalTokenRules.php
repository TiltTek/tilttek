<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Contract\TokenRules;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\TableRules;

/**
 * The rules of the tables in `tilttek_luck_tokens_rules`, one row per table, as the JSON `TableRules::fromJson()` reads:
 * the form of a table's settings saves them, and every read checks them again.
 */
final readonly class DbalTokenRules implements TokenRules
{
    /**
     * How many tables `removeGameMaster()` reads at a time.
     */
    private const int PAGE = 500;

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws InvalidTokenRule when the row holds rules the library no longer takes
     */
    #[Override]
    public function rulesFor(ResourceReference $table): ?TableRules
    {
        $json = $this->connection->fetchOne(\sprintf('SELECT rules FROM %s WHERE table_ref = ?', LuckTokensTables::RULES), [$table->toString()]);

        return false === $json ? null : TableRules::fromJson($table, Rows::text($json));
    }

    /**
     * Keeps the rules of their table, in place of those it had.
     */
    public function save(TableRules $rules): void
    {
        $table = $rules->table->toString();
        $json = json_encode($rules->toArray(), \JSON_THROW_ON_ERROR);

        $this->connection->transactional(static function (Connection $connection) use ($table, $json): void {
            $connection->delete(LuckTokensTables::RULES, ['table_ref' => $table]);
            $connection->insert(LuckTokensTables::RULES, ['table_ref' => $table, 'rules' => $json]);
        });
    }

    /**
     * Takes the member off the game masters of every table, the rest of each table's rules left as they were. The
     * tables are read by pages of 500, in the order of their reference.
     *
     * @return int the tables changed; 0 when no table names the member, as once they are erased
     *
     * @throws InvalidTokenRule when a row holds rules the library no longer takes
     */
    public function removeGameMaster(ResourceReference $member): int
    {
        $changed = 0;
        $after = null;

        do {
            $query = $this->connection->createQueryBuilder()
                ->select('table_ref', 'rules')
                ->from(LuckTokensTables::RULES)
                ->orderBy('table_ref')
                ->setMaxResults(self::PAGE);

            if (null !== $after) {
                $query->where('table_ref > :after')->setParameter('after', $after);
            }

            $rows = $query->fetchAllAssociative();

            foreach ($rows as $row) {
                $after = Rows::text($row['table_ref']);
                $rules = TableRules::fromJson(ResourceReference::fromString($after), Rows::text($row['rules']));

                if ($rules->isGameMaster($member)) {
                    $masters = array_values(array_filter($rules->gameMasters, static fn (ResourceReference $master): bool => !$master->equals($member)));
                    $json = json_encode(new TableRules($rules->table, $rules->kinds(), $masters)->toArray(), \JSON_THROW_ON_ERROR);
                    $this->connection->update(LuckTokensTables::RULES, ['rules' => $json], ['table_ref' => $after]);
                    ++$changed;
                }
            }
        } while (self::PAGE === \count($rows));

        return $changed;
    }

    /**
     * Forgets the rules of a table, which then hands out no tokens; its ledger stays.
     *
     * @return bool whether the table had rules
     */
    public function remove(ResourceReference $table): bool
    {
        return 0 < $this->connection->delete(LuckTokensTables::RULES, ['table_ref' => $table->toString()]);
    }
}
