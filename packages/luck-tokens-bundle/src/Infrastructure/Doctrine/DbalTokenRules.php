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
     * Forgets the rules of a table, which then hands out no tokens; its ledger stays.
     *
     * @return bool whether the table had rules
     */
    public function remove(ResourceReference $table): bool
    {
        return 0 < $this->connection->delete(LuckTokensTables::RULES, ['table_ref' => $table->toString()]);
    }
}
