<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Mercure;

use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenPublisher;
use Tilttek\EscapeRoomBundle\Application\EscapeRoomTopics;

/**
 * Tells the screens of a game that it moved, by a private update on the game's topic whose data is `{"move": "hint"}`:
 * no hint, no message, no team. A hub that cannot be reached is logged and fails nothing: the screens read the game
 * again at their next interval.
 */
final readonly class MercureScreenPublisher implements ScreenPublisher
{
    public function __construct(
        private HubInterface $hub,
        private EscapeRoomTopics $topics,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function moved(string $game, string $move): void
    {
        try {
            $this->hub->publish(new Update($this->topics->topic($game), json_encode(['move' => $move], \JSON_THROW_ON_ERROR), true));
        } catch (RuntimeException $runtimeException) {
            $this->logger?->warning('The move {move} of the game "{game}" could not be pushed to Mercure: {message}', [
                'move' => $move,
                'game' => $game,
                'message' => $runtimeException->getMessage(),
                'exception' => $runtimeException,
            ]);
        }
    }
}
