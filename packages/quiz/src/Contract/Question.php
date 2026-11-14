<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Contract;

use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Grade;

/**
 * A question of a quiz: what is asked, what it is worth, how long it lasts, and how right an answer is. An application
 * adds a kind of its own by implementing it; the grade must depend on the answer alone.
 */
interface Question
{
    /** The identifier of the question, unique in its quiz. */
    public function id(): string;

    /** What is asked, as the application shows it. */
    public function prompt(): string;

    /** The points a fully right answer earns, before any speed bonus. */
    public function points(): int;

    /** The seconds the question lasts, or null for the time limit of the quiz. */
    public function timeLimit(): ?int;

    /**
     * How right the answer is.
     *
     * @throws InvalidQuiz when the answer is not of the kind the question takes
     */
    public function grade(Answer $answer): Grade;
}
