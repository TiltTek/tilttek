<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Contract;

use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\Live\LiveSession;

/**
 * Where the live sessions are kept. Every change of a session stores its next version, and is refused when the
 * version stored is not the one it was made from: two answers that come at once are both kept, one after the other,
 * by the retries of `LiveQuizzes`.
 */
interface LiveSessionStore
{
    public function find(string $session): ?LiveSession;

    /**
     * The session that is not over and has this PIN, if any.
     */
    public function findByPin(string $pin): ?LiveSession;

    /**
     * Keeps a new session, at version 1.
     *
     * @throws QuizConflict when a session has its identifier already
     */
    public function add(LiveSession $session): void;

    /**
     * Keeps the session's next version.
     *
     * @throws QuizConflict when the version stored is not `$session->version() - 1`
     */
    public function save(LiveSession $session): void;
}
