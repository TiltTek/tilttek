<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\GameInProgress;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Exception\UnknownTable;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngineBundle\Application\Contract\ViewerResolver;
use Tilttek\GameEngineBundle\Application\GameTables;
use Tilttek\GameEngineBundle\Application\Viewer;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\TableTopic;

/**
 * The endpoints a player's page reads a table through, in JSON, never cached:
 *
 * - `GET /{table}`: the view of the seat the application's `ViewerResolver` gives — or the audience's —, with the
 *   number of moves, the phase, the round, the seats that may act, the outcome and its statistics once the game is over,
 *   the commitment to the seed and the Mercure topic of the table;
 * - `GET /{table}/log`: the log of a table whose game is over, seed included, which anyone replays to verify every draw
 *   against the commitment, checked to be the log of the finished state; 409 while the game goes on, since the seed
 *   would tell the draws to come, and when the table changed between the two reads.
 *
 * Both answer 403 when the resolver refuses the reader or names a seat not at the table, 404 for a table that does not
 * exist, and 500 for a table whose log cannot be replayed.
 */
final readonly class TableController
{
    public function __construct(
        private GameTables $tables,
        private ViewerResolver $viewers,
        private ?TableTopic $topic = null,
    ) {
    }

    public function view(string $table): JsonResponse
    {
        return $this->answer($table, function (Viewer $viewer, GameState $state) use ($table): JsonResponse {
            $view = $this->tables->view($table, $viewer->seat, $viewer->preferences);

            return $this->json([
                'table' => $state->gameId,
                'game' => $state->game,
                'sequence' => $state->sequence,
                'phase' => $state->phase,
                'round' => $state->turn->round,
                'to_act' => $state->isOver() ? [] : $state->turn->toAct(),
                'over' => $state->isOver(),
                'outcome' => $state->outcome?->toArray(),
                'seat' => $viewer->seat,
                'commitment' => $this->tables->commitment($table),
                'topic' => $this->topic?->of($table),
                'view' => $view,
            ]);
        });
    }

    public function log(string $table): JsonResponse
    {
        return $this->answer($table, function (Viewer $viewer, GameState $state) use ($table): JsonResponse {
            try {
                $log = $this->tables->finishedLog($table);
            } catch (GameInProgress) {
                return $this->problem(Response::HTTP_CONFLICT, 'The game at this table is not over: its log, which holds the seed, stays secret until it is.');
            } catch (ConcurrentMove) {
                return $this->problem(Response::HTTP_CONFLICT, 'The table changed while its log was read: read it again.');
            }

            return $this->json(['commitment' => $log->commitment()] + $log->toArray());
        });
    }

    /**
     * @param callable(Viewer, GameState): JsonResponse $answer
     */
    private function answer(string $table, callable $answer): JsonResponse
    {
        try {
            $viewer = $this->viewers->resolve($table);

            if (!$viewer->allowed) {
                return $this->problem(Response::HTTP_FORBIDDEN, 'This table is not yours to read.');
            }

            $state = $this->tables->state($table);

            if (null !== $viewer->seat && !\in_array($viewer->seat, $state->seats, true)) {
                return $this->problem(Response::HTTP_FORBIDDEN, 'This table is not yours to read.');
            }

            return $answer($viewer, $state);
        } catch (UnknownTable) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'No table has this identifier.');
        } catch (InvalidGame) {
            return $this->problem(Response::HTTP_INTERNAL_SERVER_ERROR, 'This table cannot be read.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): JsonResponse
    {
        return new JsonResponse($data, Response::HTTP_OK, ['Cache-Control' => 'no-store, private']);
    }

    private function problem(int $status, string $detail): JsonResponse
    {
        return new JsonResponse(['status' => $status, 'detail' => $detail], $status, ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store, private']);
    }
}
