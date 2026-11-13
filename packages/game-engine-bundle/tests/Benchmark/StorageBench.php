<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Benchmark;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\LogHeader;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Snapshot;
use Tilttek\GameEngineBundle\Infrastructure\Cache\CacheStateCache;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\DbalLogStore;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineTables;

/**
 * What the storage of a table costs on every move and every read: a move appended to the log, a log of 100 moves read
 * back, and a state put in the cache and read back from it — on SQLite in memory and an array pool, the cost of the
 * bundle's own work rather than of a server.
 */
final class StorageBench
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    private readonly Connection $connection;

    private readonly DbalLogStore $store;

    private readonly CacheStateCache $cache;

    private readonly Snapshot $state;

    private LogHeader $appended;

    public function __construct()
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schemaManager = $this->connection->createSchemaManager();
        $current = $schemaManager->introspectSchema();

        foreach ($this->connection->getDatabasePlatform()->getAlterSchemaSQL($schemaManager->createComparator()->compareSchemas($current, GameEngineTables::configure($current))) as $sql) {
            $this->connection->executeStatement($sql);
        }

        $this->store = new DbalLogStore($this->connection);
        $scene = new RoleplayingScene();
        $engine = new Engine();
        $table = $engine->start($scene, 'scene:100', ['gm', 'ann', 'bob'], [], self::SEED);

        for ($move = 0; $move < 100; ++$move) {
            $table = $engine->play($scene, $table, new Move('scene.note', 'ann', ['text' => 'A note of the scene.']));
        }

        $this->store->create($table->log);
        $appended = new GameLog('scene:appended', RoleplayingScene::NAME, '1', self::SEED, ['gm', 'ann']);
        $this->store->create($appended);
        $this->appended = $appended->header();

        $this->state = Snapshot::of($table->state, $table->log->header());
        $this->cache = new CacheStateCache(new ArrayAdapter());
        $this->cache->put($this->state);
    }

    /**
     * @Revs(200)
     *
     * @Iterations(3)
     */
    public function benchAppendAMove(): void
    {
        $this->store->append($this->appended, new LoggedMove($this->appended->sequence + 1, new Move('scene.note', 'ann', ['text' => 'A note.'])));
        $this->appended = $this->appended->appended();
    }

    /**
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchLoadALogOf100Moves(): void
    {
        $this->store->load('scene:100');
    }

    public function benchPutAState(): void
    {
        $this->cache->put($this->state);
    }

    public function benchGetAState(): void
    {
        $this->cache->get('scene:100');
    }
}
