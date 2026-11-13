<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tilttek\GameEngine\Exception\GameOver;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Application\Exception\IdentifyingSeat;
use Tilttek\GameEngineBundle\Application\GameTables;
use Tilttek\GameEngineBundle\Tests\Functional\App\HubRecorder;
use Tilttek\GameEngineBundle\Tests\Functional\App\TestKernel;
use Tilttek\GameEngineBundle\TilttekGameEngineBundle;
use Twig\Environment;

/**
 * The bundle in an application: the tables of the logs created by the command, a table opened and played under its
 * lock on SQLite, every change published on its Mercure topic, the view of a seat and of the audience read from the
 * endpoint, the log kept secret until the game is over, the template of the table rendered with the kit, and the log
 * verified by the command.
 */
#[CoversClass(TilttekGameEngineBundle::class)]
final class GameEngineBundleTest extends KernelTestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testATableIsPlayedPublishedReadAndVerified(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $setup = new CommandTester($application->find('tilttek:game-engine:setup'));
        self::assertSame(0, $setup->execute([]));

        $games = self::getContainer()->get(Games::class);
        self::assertInstanceOf(Games::class, $games);
        self::assertSame(['roll-race', 'roleplaying-scene'], $games->names());

        $tables = self::getContainer()->get(GameTables::class);
        self::assertInstanceOf(GameTables::class, $tables);
        $tables->open('roll-race', 'table:1', ['ann', 'bob'], ['target' => 3], self::SEED);
        $tables->play('table:1', new Move('roll.wait', 'ann'), 0);
        self::assertSame(0, $tables->undo('table:1', 'ann')->sequence);
        $tables->play('table:1', new Move('roll.wait', 'ann'), 0);

        try {
            $tables->open('roll-race', 'table:2', ['seat:1', 'member:44'], [], self::SEED);
            self::fail('A seat that names a member was accepted.');
        } catch (IdentifyingSeat $identifyingSeat) {
            self::assertStringContainsString('"member:44"', $identifyingSeat->getMessage());
        }

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        self::assertEquals([1, 3], array_map(static fn (mixed $count): int => is_numeric($count) ? (int) $count : -1, array_values((array) $connection->fetchAssociative('SELECT moves, revision FROM tilttek_game_engine_table'))));

        $hub = self::getContainer()->get(HubRecorder::class);
        self::assertInstanceOf(HubRecorder::class, $hub);
        self::assertCount(4, $hub->updates);
        self::assertSame(['urn:tilttek-game-engine:table:table:1'], $hub->updates[3]->getTopics());
        self::assertSame('{"table":"table:1","sequence":1,"phase":"race","round":1,"to_act":["bob"],"over":false}', $hub->updates[3]->getData());
        self::assertTrue($hub->updates[3]->isPrivate());

        $seat = $this->get('/game-tables/table%3A1', ['HTTP_X_SEAT' => 'bob']);
        self::assertSame(200, $seat['status']);
        self::assertSame('bob', $seat['body']['seat']);
        self::assertSame(['bob'], $seat['body']['to_act']);
        self::assertSame('urn:tilttek-game-engine:table:table:1', $seat['body']['topic']);
        self::assertIsArray($seat['body']['view']);
        self::assertIsInt($seat['body']['view']['secret']);

        $audience = $this->get('/game-tables/table%3A1');
        self::assertIsArray($audience['body']['view']);
        self::assertNull($audience['body']['view']['secret']);
        self::assertSame(403, $this->get('/game-tables/table%3A1', ['HTTP_X_SEAT' => 'cy'])['status']);
        self::assertSame(404, $this->get('/game-tables/table%3A9')['status']);
        self::assertSame(409, $this->get('/game-tables/table%3A1/log')['status']);

        $html = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $html);
        $page = $html->render('page.html.twig', ['game_id' => 'table:1']);
        self::assertStringContainsString('data-controller="tilttek--game-engine-bundle--game-table"', $page);
        self::assertStringContainsString('data-tilttek--game-engine-bundle--game-table-view-url-value="/game-tables/table:1"', $page);
        self::assertStringContainsString('data-tilttek--game-engine-bundle--game-table-sequence-value="1"', $page);
        self::assertStringContainsString(hash('sha256', self::SEED), $page);
        self::assertStringContainsString('data-seat="bob"', $page);

        if (null !== TestKernel::kit()) {
            self::assertStringContainsString('data-slot="card"', $page);
            self::assertStringContainsString('data-slot="badge"', $page);
        }

        while (!$tables->state('table:1')->isOver()) {
            $tables->play('table:1', new Move('roll.roll', $tables->state('table:1')->turn->toAct()[0]));
        }

        $log = $this->get('/game-tables/table%3A1/log', ['HTTP_X_SEAT' => 'ann']);
        self::assertSame(200, $log['status']);
        self::assertSame(self::SEED, $log['body']['seed']);

        // Once the log has shown the seed, which tells every draw to come, no move is taken back.
        $moves = $tables->state('table:1')->sequence;

        try {
            $tables->undo('table:1');
            self::fail('A move of a finished game was taken back.');
        } catch (GameOver $gameOver) {
            self::assertSame('The game at the table "table:1" is over: its seed may be shown, so no move is taken back.', $gameOver->getMessage());
        }

        $library = self::getContainer()->get(Tables::class);
        self::assertInstanceOf(Tables::class, $library);
        $library->reset();
        self::assertSame($moves, $tables->state('table:1')->sequence);
        self::assertSame($moves, $tables->log('table:1')->sequence());
        self::assertStringContainsString('isBadge', $html->render('page.html.twig', ['game_id' => 'table:1']));

        $verify = new CommandTester($application->find('tilttek:game-engine:verify'));
        self::assertSame(0, $verify->execute(['table' => 'table:1', '--commitment' => hash('sha256', self::SEED)]));
        self::assertStringContainsString('The log replays from the seed committed to.', $verify->getDisplay());
    }

    /**
     * @param array<string, string> $server
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function get(string $path, array $server = []): array
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $response = $kernel->handle(Request::create($path, server: $server));
        $body = json_decode((string) $response->getContent(), true, 32, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        /** @var array<string, mixed> $body */
        return ['status' => $response->getStatusCode(), 'body' => $body];
    }
}
