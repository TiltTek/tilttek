<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Mercure;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Tilttek\Bingo\Event\BingoWon;
use Tilttek\Bingo\Event\SquareCalled;
use Tilttek\Bingo\Event\SquareTicked;
use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * Pushes the moves of a bingo game to the players' screens through Mercure, as private updates: a square called and
 * a card won on the game's topic, the topic prefix followed by the game's identifier; a square ticked on the topic of
 * its card, the game's topic followed by `/` and the serial. An update names the game, the card's serial, the square
 * and the pattern, never a player: the screen that hears it reads the rest from the application, with its own rights.
 * Register each method as a listener of its event; it needs `symfony/mercure`.
 */
final readonly class BingoPublisher
{
    public function __construct(
        private HubInterface $publisher,
        private string $topicPrefix,
    ) {
        if ('' === $topicPrefix) {
            throw InvalidBingo::because('The topic prefix of the bingo games is not empty.');
        }
    }

    /** The topic of a game's updates. */
    public function topic(string $game): string
    {
        return $this->topicPrefix.rawurlencode($game);
    }

    /** The topic of a card's updates. */
    public function cardTopic(string $game, int $serial): string
    {
        return $this->topic($game).'/'.$serial;
    }

    public function squareCalled(SquareCalled $event): void
    {
        $this->push($this->topic($event->game), ['type' => 'square-called', 'game' => $event->game, 'square' => $event->square]);
    }

    public function bingoWon(BingoWon $event): void
    {
        $this->push($this->topic($event->game), ['type' => 'bingo-won', 'game' => $event->game, 'serial' => $event->serial, 'pattern' => $event->pattern]);
    }

    public function squareTicked(SquareTicked $event): void
    {
        $this->push($this->cardTopic($event->game, $event->serial), ['type' => 'square-ticked', 'game' => $event->game, 'serial' => $event->serial, 'square' => $event->square]);
    }

    /**
     * @param array<string, int|string> $data
     */
    private function push(string $topic, array $data): void
    {
        $this->publisher->publish(new Update($topic, json_encode($data, \JSON_THROW_ON_ERROR), true));
    }
}
