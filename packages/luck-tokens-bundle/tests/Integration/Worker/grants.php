<?php

declare(strict_types=1);

/*
 * One process of the concurrency test: grants a token to the same holder, again and again, each grant an operation of
 * its own, retried after a `LedgerConflict` as an application would, after a wait that grows with each conflict in a
 * row; prints how many conflicts reached it, as JSON.
 *
 * php grants.php <autoload> <database: a URL, or the path of a SQLite file> <worker> <grants> [<lock directory>]
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Phil\Resource\ResourceReference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Tilttek\LuckTokens\Exception\LedgerConflict;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenLedger;

$arguments = array_map(static fn (mixed $argument): string => is_string($argument) ? $argument : '', array_values((array) ($_SERVER['argv'] ?? [])));
[, $autoload, $database, $worker, $grants] = $arguments + ['', '', '', '', '0'];
$lockDirectory = $arguments[5] ?? '';

require $autoload;

$connection = DriverManager::getConnection(str_contains($database, '://')
    ? new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql'])->parse($database)
    : ['driver' => 'pdo_sqlite', 'path' => $database]);
$table = ResourceReference::of('table', '1');
$gm = ResourceReference::of('member', 'gm');
$ana = ResourceReference::of('member', 'ana');
$rules = new InMemoryTokenRules();
$rules->save(TableRules::fromArray($table, ['kinds' => [['key' => 'fate', 'name' => 'Fate points', 'cap' => 1000]], 'game_masters' => ['member:gm']]));
$tokens = new LuckTokens($rules, new DbalTokenLedger($connection));
$locked = '' === $lockDirectory ? null : new LockedLuckTokens($tokens, new LockFactory(new FlockStore($lockDirectory)));
$conflicts = 0;
// The attempts at one grant, past which the worker gives up: a livelock fails the test rather than hang it.
$attempts = 100;

for ($grant = 0; $grant < (int) $grants; ++$grant) {
    for ($attempt = 0;; ++$attempt) {
        try {
            ($locked ?? $tokens)->grant($table, 'fate', $ana, 1, $gm, sprintf('w%s-%d', $worker, $grant));

            break;
        } catch (LedgerConflict $conflict) {
            ++$conflicts;

            if ($attempt >= $attempts) {
                throw $conflict;
            }

            // A ledger can be busy without having moved: on SQLite, a writer that reads first and writes next is told
            // at once that another one holds the write lock, and that one may be paused by a busy machine before it
            // commits. The wait doubles at each conflict in a row, up to 64 ms, chosen at random below that: the
            // attempts stay bounded, whatever the speed of the machine.
            usleep(random_int(100, 2_000 << min($attempt, 5)));
        }
    }
}

echo json_encode(['conflicts' => $conflicts]);
