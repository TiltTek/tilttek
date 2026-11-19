<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Benchmark;

use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenLedger;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Database;

/**
 * What the tokens cost on a long campaign kept in the database, beside the memory ledger of the library's own
 * benchmark: a token spent and granted back after 10 000 entries on SQLite in memory — each balance read from the
 * index of the holder's last entry, each operation one transaction —, and the start of a session for twelve players.
 */
final class DbalLuckTokensBench
{
    private readonly LuckTokens $tokens;

    private readonly ResourceReference $table;

    private readonly ResourceReference $gm;

    /** @var list<ResourceReference> */
    private readonly array $players;

    private int $operation = 0;

    public function __construct()
    {
        $this->table = ResourceReference::of('table', '1');
        $this->gm = ResourceReference::of('member', 'gm');
        $rules = new InMemoryTokenRules();
        $rules->save(TableRules::fromArray($this->table, [
            'kinds' => [
                ['key' => 'fate', 'name' => 'Fate points', 'cap' => 1000, 'refresh' => 'each-session', 'refresh_to' => 3],
                ['key' => 'bennies', 'name' => 'Bennies', 'cap' => 5, 'refresh' => 'each-session', 'refresh_to' => 3, 'carry_over' => 0],
            ],
            'game_masters' => ['member:gm'],
        ]));
        $this->tokens = new LuckTokens($rules, new DbalTokenLedger(Database::connection()));
        $this->players = array_map(static fn (int $index): ResourceReference => ResourceReference::of('member', (string) $index), range(1, 12));

        for ($entry = 0; $entry < 10_000; ++$entry) {
            $this->tokens->grant($this->table, 'fate', $this->players[$entry % 12], 1, $this->gm, 'seed-'.$entry);
        }
    }

    public function benchSpendAndGrantAfter10000Entries(): void
    {
        $this->tokens->spend($this->table, 'fate', $this->players[0], 1, $this->players[0], 'spend-'.++$this->operation);
        $this->tokens->grant($this->table, 'fate', $this->players[0], 1, $this->gm, 'grant-'.$this->operation);
    }

    public function benchStartASessionForTwelvePlayers(): void
    {
        $this->tokens->startSession($this->table, 'session-'.++$this->operation, $this->players, $this->gm, 'start-'.$this->operation);
    }
}
