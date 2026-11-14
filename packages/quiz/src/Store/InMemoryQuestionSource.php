<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Store;

use Override;
use Tilttek\Quiz\Contract\Question;
use Tilttek\Quiz\Contract\QuestionSource;

/**
 * Banks of questions in memory, each a list in the order its questions are asked.
 */
final readonly class InMemoryQuestionSource implements QuestionSource
{
    /**
     * @param array<string, list<Question>> $banks
     */
    public function __construct(
        private array $banks,
    ) {
    }

    #[Override]
    public function questions(string $bank, int $count): array
    {
        return \array_slice($this->banks[$bank] ?? [], 0, max(0, $count));
    }
}
