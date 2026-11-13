<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit\Infrastructure;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Tilttek\GameEngine\Contract\LogStore;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\LogHeader;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Application\GameTables;
use Tilttek\GameEngineBundle\Application\Viewer;
use Tilttek\GameEngineBundle\Infrastructure\Http\TableController;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\TableTopic;
use Tilttek\GameEngineBundle\Tests\Fixture\RollRace;
use Tilttek\GameEngineBundle\Tests\Fixture\Seats;

#[CoversClass(TableController::class)]
final class TableControllerTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    private GameTables $tables;

    protected function setUp(): void
    {
        $logs = new InMemoryLogStore();
        $this->tables = new GameTables(new Tables(new Games(new RollRace()), $logs));
        $this->tables->open('roll-race', 'table:1', ['ann', 'bob'], ['target' => 2], self::SEED);
        $this->tables->play('table:1', new Move('roll.wait', 'ann'));
    }

    public function testASeatReadsItsViewAndWhatAnyoneAtTheTableMayKnow(): void
    {
        $seats = new Seats(Viewer::seat('bob', ['leader-first' => true]));
        $response = new TableController($this->tables, $seats, new TableTopic())->view('table:1');
        $state = $this->tables->state('table:1');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame([
            'table' => 'table:1',
            'game' => 'roll-race',
            'sequence' => 1,
            'phase' => 'race',
            'round' => 1,
            'to_act' => ['bob'],
            'over' => false,
            'outcome' => null,
            'seat' => 'bob',
            'commitment' => hash('sha256', self::SEED),
            'topic' => 'urn:tilttek-game-engine:table:table:1',
            'view' => ['totals' => ['ann' => 0, 'bob' => 0], 'secret' => $this->secret($state, 'bob')],
        ], $this->body($response));
        self::assertSame(['table:1'], $seats->asked);

        $audience = $this->body(new TableController($this->tables, new Seats(Viewer::audience()))->view('table:1'));
        self::assertNull($audience['topic']);
        self::assertNull($audience['seat']);
        self::assertSame(['totals' => ['ann' => 0, 'bob' => 0], 'secret' => null], $audience['view']);
    }

    public function testAFinishedTableShowsItsOutcomeAndItsLog(): void
    {
        $this->finish();
        $controller = new TableController($this->tables, new Seats(Viewer::seat('ann')));
        $view = $this->body($controller->view('table:1'));

        self::assertTrue($view['over']);
        self::assertSame([], $view['to_act']);
        self::assertIsArray($view['outcome']);
        self::assertSame('target-reached', $view['outcome']['reason']);

        $response = $controller->log('table:1');
        $log = $this->body($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(hash('sha256', self::SEED), $log['commitment']);
        self::assertSame(['commitment' => hash('sha256', self::SEED)] + $this->tables->log('table:1')->toArray(), $log);
    }

    public function testTheLogOfATableOpenedAgainMeanwhileIsNotShown(): void
    {
        $inner = new InMemoryLogStore();
        $logs = new class($inner) implements LogStore {
            public ?Closure $beforeLoading = null;

            public function __construct(
                private readonly InMemoryLogStore $inner,
            ) {
            }

            public function create(GameLog $log): void
            {
                $this->inner->create($log);
            }

            public function load(string $gameId): ?GameLog
            {
                if ($this->beforeLoading instanceof Closure) {
                    ($this->beforeLoading)();
                }

                return $this->inner->load($gameId);
            }

            public function header(string $gameId): ?LogHeader
            {
                return $this->inner->header($gameId);
            }

            public function moves(string $gameId, int $from, int $to): iterable
            {
                return $this->inner->moves($gameId, $from, $to);
            }

            public function append(LogHeader $read, LoggedMove $move): void
            {
                $this->inner->append($read, $move);
            }

            public function removeLast(LogHeader $read): void
            {
                $this->inner->removeLast($read);
            }
        };
        $this->tables = new GameTables(new Tables(new Games(new RollRace()), $logs));
        $this->tables->open('roll-race', 'table:1', ['ann', 'bob'], ['target' => 2], self::SEED);
        $this->finish();
        $logs->beforeLoading = static function () use ($inner): void {
            $inner->reset();
            new Tables(new Games(new RollRace()), $inner)->open('roll-race', 'table:1', ['ann', 'bob'], ['target' => 2], str_repeat('ab', 32));
        };

        $response = new TableController($this->tables, new Seats(Viewer::seat('ann')))->log('table:1');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(['status' => 409, 'detail' => 'The table changed while its log was read: read it again.'], $this->body($response));
        self::assertStringNotContainsString(str_repeat('ab', 32), (string) $response->getContent());
    }

    public function testTheLogOfAGameThatGoesOnStaysSecret(): void
    {
        $response = new TableController($this->tables, new Seats(Viewer::seat('ann')))->log('table:1');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(['status' => 409, 'detail' => 'The game at this table is not over: its log, which holds the seed, stays secret until it is.'], $this->body($response));
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertStringNotContainsString(self::SEED, (string) $response->getContent());
    }

    public function testAReaderTheApplicationRefusesReadsNothing(): void
    {
        foreach ([Viewer::denied(), Viewer::seat('cy')] as $viewer) {
            $controller = new TableController($this->tables, new Seats($viewer));

            foreach ([$controller->view('table:1'), $controller->log('table:1')] as $response) {
                self::assertSame(403, $response->getStatusCode());
                self::assertSame(['status' => 403, 'detail' => 'This table is not yours to read.'], $this->body($response));
            }
        }

        $response = new TableController($this->tables, new Seats(Viewer::audience()))->view('table:9');
        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['status' => 404, 'detail' => 'No table has this identifier.'], $this->body($response));
    }

    public function testATableThatCannotBeReadIsAServerError(): void
    {
        $response = new TableController($this->tables, new Seats(Viewer::seat('ann', ['leader-first' => 'yes'])))->view('table:1');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['status' => 500, 'detail' => 'This table cannot be read.'], $this->body($response));
    }

    private function finish(): void
    {
        while (!$this->tables->state('table:1')->isOver()) {
            $this->tables->play('table:1', new Move('roll.roll', $this->tables->state('table:1')->turn->toAct()[0]));
        }
    }

    private function secret(GameState $state, string $seat): mixed
    {
        $secrets = $state->data['secrets'] ?? [];

        return \is_array($secrets) ? $secrets[$seat] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(JsonResponse $response): array
    {
        $body = json_decode((string) $response->getContent(), true, 32, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        /** @var array<string, mixed> $body */
        return $body;
    }
}
