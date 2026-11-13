<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Benchmark;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Application\GameTables;
use Tilttek\GameEngineBundle\Infrastructure\Cache\CacheStateCache;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\DbalLogStore;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineTables;

/**
 * What a long table costs: 8 000 moves of 16 000 bytes each, on SQLite in memory and an array pool. A read of the page
 * of a seat — its state, its view and the commitment, as `GET /{table}` asks for them — and a move, both on a cached
 * state, then the log replayed from the setup, window by window, as a read does when the cache has forgotten it.
 */
final readonly class LargeTableBench
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    private const int MOVES = 8_000;

    private DbalLogStore $store;

    private ArrayAdapter $pool;

    private Games $games;

    public function __construct()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schemaManager = $connection->createSchemaManager();
        $current = $schemaManager->introspectSchema();

        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($schemaManager->createComparator()->compareSchemas($current, GameEngineTables::configure($current))) as $sql) {
            $connection->executeStatement($sql);
        }

        $this->store = new DbalLogStore($connection);
        $this->pool = new ArrayAdapter();
        $this->games = new Games(new RoleplayingScene());
        new Tables($this->games, $this->store)->open(RoleplayingScene::NAME, 'scene:long', ['gm', 'ann', 'bob'], [], self::SEED);
        $this->fill($connection);
        $this->tables()->state('scene:long');
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchReadTheViewOfASeat(): void
    {
        $tables = $this->tables();
        $tables->state('scene:long');
        $tables->view('scene:long', 'ann');
        $tables->commitment('scene:long');
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchPlayAMove(): void
    {
        $this->tables()->play('scene:long', new Move('scene.note', 'ann', ['text' => 'A note.']));
    }

    /**
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchReplayTheLog(): void
    {
        new Tables($this->games, $this->store)->state('scene:long');
    }

    private function tables(): GameTables
    {
        return new GameTables(new Tables($this->games, $this->store, new CacheStateCache($this->pool)));
    }

    private function fill(Connection $connection): void
    {
        $payload = (string) json_encode(['text' => str_repeat('x', 16_000)]);
        $connection->beginTransaction();

        for ($number = 1; $number <= self::MOVES; ++$number) {
            $connection->insert(GameEngineTables::MOVES, ['game_id' => 'scene:long', 'number' => $number, 'type' => 'scene.note', 'seat' => 'ann', 'payload' => $payload, 'drew' => 0]);
        }

        $connection->executeStatement(\sprintf('UPDATE %s SET moves = ?, revision = ?', GameEngineTables::TABLES), [self::MOVES, self::MOVES]);
        $connection->commit();
    }
}
