<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Contract;

use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\Live\LiveSession;

/**
 * Where the live sessions are kept. Every change of a session stores its next version, and is refused when the
 * version stored is not the one it was made from: two answers that come at once are both kept, one after the other,
 * by the retries of `LiveQuizzes`.
 *
 * A PIN is held by one session that is not over at most: the store checks it and keeps the new session in one atomic
 * step, so that two sessions opened at once never answer to the same PIN. A session that is over frees its PIN.
 */
interface LiveSessionStore
{
    public function find(string $session): ?LiveSession;

    /**
     * The session that is not over and has this PIN, if any.
     */
    public function findByPin(string $pin): ?LiveSession;

    /**
     * Keeps a new session, at version 1, unless another session that is not over holds its PIN: the check and the
     * keeping are one atomic step — a unique index in a database —, never a lookup followed by a write.
     *
     * @return bool false, and nothing kept, when another session that is not over holds the PIN
     *
     * @throws QuizConflict when a session has its identifier already
     */
    public function add(LiveSession $session): bool;

    /**
     * Keeps the session's next version; a session that is over frees its PIN.
     *
     * @throws QuizConflict when the version stored is not `$session->version() - 1`
     */
    public function save(LiveSession $session): void;
}
