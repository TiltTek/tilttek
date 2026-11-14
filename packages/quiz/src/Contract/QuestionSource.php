<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Contract;

/**
 * Where the questions of a quiz come from when they are kept in a bank: an assessment library, a file of questions
 * under a free licence, the application's editor. `Quizzes::fromSource()` composes a quiz from it.
 */
interface QuestionSource
{
    /**
     * Up to `$count` questions of the bank, in the order they are asked.
     *
     * @return list<Question>
     */
    public function questions(string $bank, int $count): array;
}
