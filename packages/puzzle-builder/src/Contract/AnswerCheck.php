<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Contract;

use Tilttek\PuzzleBuilder\Answer\Verdict;
use Tilttek\PuzzleBuilder\Exception\InvalidAnswer;

/**
 * How the guesses of one puzzle are checked, on the server. `Answer\AnswerKey` checks typed answers; a check of its
 * own — an accusation naming a suspect and a weapon, a code read from a lock — implements this interface.
 *
 * An implementation keeps its answer to itself: nothing it holds is ever shown to the players, and the library never
 * asks it for the answer.
 */
interface AnswerCheck
{
    /**
     * @throws InvalidAnswer when the guess is not one the check reads; it is then not counted as an attempt
     */
    public function check(string $guess): Verdict;
}
