<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Mercure;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Tilttek\Quiz\Event\LiveQuizFinished;
use Tilttek\Quiz\Event\PlayerJoined;
use Tilttek\Quiz\Event\QuestionClosed;
use Tilttek\Quiz\Event\QuestionShown;
use Tilttek\Quiz\Exception\InvalidQuiz;

/**
 * Pushes the moves of a live session to the screens of its host and players through Mercure, as private updates on
 * the session's topic: the topic prefix followed by the session's identifier. An update says what happened and names
 * the session and the position of the question, never a player nor an answer: the screen that hears it reads the
 * rest from the application, with its own rights. Register each method as a listener of its event; it needs
 * `symfony/mercure`.
 */
final readonly class LiveSessionPublisher
{
    public function __construct(
        private HubInterface $publisher,
        private string $topicPrefix,
    ) {
        if ('' === $topicPrefix) {
            throw InvalidQuiz::because('The topic prefix of the live sessions is not empty.');
        }
    }

    /** The topic of a session's updates, to which the application subscribes its screens. */
    public function topic(string $session): string
    {
        return $this->topicPrefix.rawurlencode($session);
    }

    public function playerJoined(PlayerJoined $event): void
    {
        $this->push($event->session, ['type' => 'player-joined', 'session' => $event->session]);
    }

    public function questionShown(QuestionShown $event): void
    {
        $this->push($event->session, ['type' => 'question-shown', 'session' => $event->session, 'position' => $event->position]);
    }

    public function questionClosed(QuestionClosed $event): void
    {
        $this->push($event->session, ['type' => 'question-closed', 'session' => $event->session, 'position' => $event->position]);
    }

    public function liveQuizFinished(LiveQuizFinished $event): void
    {
        $this->push($event->session, ['type' => 'finished', 'session' => $event->session]);
    }

    /**
     * @param array<string, int|string> $data
     */
    private function push(string $session, array $data): void
    {
        $this->publisher->publish(new Update($this->topic($session), json_encode($data, \JSON_THROW_ON_ERROR), true));
    }
}
