<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Contract;

use Tilttek\Quiz\Quiz;

/**
 * Where the quizzes are read: the application's own storage, a list in memory, or questions drawn from a bank through
 * a `QuestionSource`.
 */
interface QuizCatalog
{
    public function find(string $quiz): ?Quiz;
}
