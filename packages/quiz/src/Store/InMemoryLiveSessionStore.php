<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Store;

use Override;
use Tilttek\Quiz\Contract\LiveSessionStore;
use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Live\LiveState;

/**
 * The live sessions in memory, for tests and for a game that lives in one process. It keeps copies, so that a session
 * changed and not saved leaves the stored one as it was.
 */
final class InMemoryLiveSessionStore implements LiveSessionStore
{
    /** @var array<string, LiveSession> */
    private array $sessions = [];

    #[Override]
    public function find(string $session): ?LiveSession
    {
        return isset($this->sessions[$session]) ? clone $this->sessions[$session] : null;
    }

    #[Override]
    public function findByPin(string $pin): ?LiveSession
    {
        foreach ($this->sessions as $session) {
            if ($session->pin === $pin && LiveState::Finished !== $session->state()) {
                return clone $session;
            }
        }

        return null;
    }

    #[Override]
    public function add(LiveSession $session): void
    {
        if (isset($this->sessions[$session->id])) {
            throw QuizConflict::at('live session', $session->id, 0, $this->sessions[$session->id]->version());
        }

        $this->sessions[$session->id] = clone $session;
    }

    #[Override]
    public function save(LiveSession $session): void
    {
        $stored = $this->sessions[$session->id] ?? null;

        if ($stored?->version() !== $session->version() - 1) {
            throw QuizConflict::at('live session', $session->id, $session->version() - 1, $stored?->version());
        }

        $this->sessions[$session->id] = clone $session;
    }
}
