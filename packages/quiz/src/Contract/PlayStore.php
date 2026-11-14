<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Contract;

use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\SelfPaced\Play;

/**
 * Where the plays at one's own pace are kept. Every change of a play stores its next version, and is refused when the
 * version stored is not the one it was made from: two answers sent at once never both count. A member holds one play
 * per attempt at a quiz: two plays started at once never both count as the first.
 */
interface PlayStore
{
    public function find(string $play): ?Play;

    /**
     * How many plays of the quiz the player started, over or not.
     */
    public function countOf(string $quiz, string $player): int;

    /**
     * Keeps a new play, at version 1.
     *
     * @throws QuizConflict when a play has its identifier already, or when the player has a play of the quiz at its
     *                      attempt already
     */
    public function add(Play $play): void;

    /**
     * Keeps the play's next version.
     *
     * @throws QuizConflict when the version stored is not `$play->version() - 1`
     */
    public function save(Play $play): void;

    /**
     * Deletes every play of the player, and returns how many there were.
     */
    public function erasePlayer(string $player): int;
}
