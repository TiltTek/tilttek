<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Store;

use Override;
use Tilttek\Quiz\Contract\QuizCatalog;
use Tilttek\Quiz\Quiz;

/**
 * The quizzes in memory: those given, by identifier.
 */
final readonly class InMemoryQuizCatalog implements QuizCatalog
{
    /** @var array<string, Quiz> */
    private array $quizzes;

    public function __construct(Quiz ...$quizzes)
    {
        $byId = [];

        foreach ($quizzes as $quiz) {
            $byId[$quiz->id] = $quiz;
        }

        $this->quizzes = $byId;
    }

    #[Override]
    public function find(string $quiz): ?Quiz
    {
        return $this->quizzes[$quiz] ?? null;
    }
}
