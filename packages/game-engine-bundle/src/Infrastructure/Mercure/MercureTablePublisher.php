<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\Mercure;

use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Exception\ExceptionInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Tilttek\GameEngine\Contract\TablePublisher;
use Tilttek\GameEngine\TableUpdate;

/**
 * Publishes every change of a table on its Mercure topic: the table's identifier, the number of moves, the phase, the
 * round, the seats that may act and whether the game is over — identifiers and counters, never the game's data, which
 * each player reads through the view of their seat. The update is private by default, so that only the subscribers the
 * hub authorized for the topic receive it.
 *
 * The move has landed in the log when the update is published: a hub that fails is logged, not thrown, and the players
 * read the table again on their own.
 */
final readonly class MercureTablePublisher implements TablePublisher
{
    public function __construct(
        private HubInterface $hub,
        private TableTopic $topic = new TableTopic(),
        private bool $private = true,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function publish(TableUpdate $update): void
    {
        try {
            $this->hub->publish(new Update($this->topic->of($update->gameId), self::data($update), $this->private));
        } catch (ExceptionInterface $exception) {
            $this->logger?->warning('The change of the table {table} was not published on the Mercure hub: {message}', ['table' => $update->gameId, 'sequence' => $update->sequence, 'message' => $exception->getMessage(), 'exception' => $exception]);
        }
    }

    /**
     * The update as the JSON text the subscribers receive.
     */
    public static function data(TableUpdate $update): string
    {
        return json_encode([
            'table' => $update->gameId,
            'sequence' => $update->sequence,
            'phase' => $update->phase,
            'round' => $update->round,
            'to_act' => $update->toAct,
            'over' => $update->over,
        ], \JSON_THROW_ON_ERROR);
    }
}
