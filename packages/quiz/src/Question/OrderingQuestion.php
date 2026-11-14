<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Question;

use Override;
use Tilttek\Quiz\Answer\OrderAnswer;
use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Contract\Question;
use Tilttek\Quiz\Grade;

/**
 * A question whose two to twelve items are put in order, given here in the right order and shuffled by the
 * application. Without partial credit, the answer is right when every item is in its place; with it, each item in its
 * place earns its share.
 */
final readonly class OrderingQuestion implements Question
{
    /** @var array<string, Choice> the items, in the right order */
    public array $items;

    /**
     * @param list<Choice> $items in the right order
     */
    public function __construct(
        private string $id,
        private string $prompt,
        array $items,
        public bool $partialCredit = false,
        private int $points = 100,
        private ?int $timeLimit = null,
    ) {
        Rules::check($id, $prompt, $points, $timeLimit);
        $this->items = Rules::choices($id, $items);
    }

    #[Override]
    public function id(): string
    {
        return $this->id;
    }

    #[Override]
    public function prompt(): string
    {
        return $this->prompt;
    }

    #[Override]
    public function points(): int
    {
        return $this->points;
    }

    #[Override]
    public function timeLimit(): ?int
    {
        return $this->timeLimit;
    }

    #[Override]
    public function grade(Answer $answer): Grade
    {
        $given = Rules::expect($this->id, $answer, OrderAnswer::class)->items;
        $placed = 0;

        foreach (array_values($this->items) as $position => $item) {
            if (($given[$position] ?? null) === $item->id) {
                ++$placed;
            }
        }

        if (!$this->partialCredit) {
            return \count($given) === $placed && \count($this->items) === $placed ? Grade::right() : Grade::wrong();
        }

        return Grade::share($placed, \count($this->items));
    }
}
