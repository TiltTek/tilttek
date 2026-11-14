<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit\Fixture;

use Tilttek\Quiz\Question\Choice;
use Tilttek\Quiz\Question\NumericQuestion;
use Tilttek\Quiz\Question\SingleChoiceQuestion;
use Tilttek\Quiz\Quiz;
use Tilttek\Quiz\Scoring;

/**
 * The quizzes of the tests.
 */
final class Quizzes
{
    /**
     * Two questions: a single choice worth 100 points, right answer `b`, and a year worth 200, 1974 within 1.
     */
    public static function trivia(?Scoring $scoring = null, ?int $timeLimit = 20): Quiz
    {
        return new Quiz('trivia', [
            new SingleChoiceQuestion('dice', 'How many faces has a d20?', [new Choice('a', 'Twelve'), new Choice('b', 'Twenty')], 'b'),
            new NumericQuestion('year', 'In which year was the first role-playing game published?', 1974, 1, points: 200),
        ], $scoring ?? Scoring::accuracy(), $timeLimit);
    }
}
