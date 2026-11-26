<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Operation;

use Tilttek\ScoreSheets\Arithmetic;
use Tilttek\ScoreSheets\Exception\InvalidSheet;
use Tilttek\ScoreSheets\Scoring\Step;

/**
 * The sum of 1 to 16 categories.
 */
final readonly class Sum implements Operation
{
    /** @var non-empty-list<string> */
    public array $of;

    /**
     * @param list<string> $of
     *
     * @throws InvalidSheet when it reads no category or more than 16
     */
    public function __construct(array $of)
    {
        $this->of = Operations::categories($of, 'A sum');
    }

    public function reads(): array
    {
        return $this->of;
    }

    public function apply(string $category, array $table): array
    {
        $steps = [];

        foreach ($table as $participant => $values) {
            $value = 0;
            $operands = [];

            foreach ($this->of as $key) {
                $operands[$key] = $values[$key];
                $value = Arithmetic::add($value, $values[$key], $category);
            }

            $steps[$participant] = new Step($category, 'sum', $operands, $value);
        }

        return $steps;
    }

    public function toArray(): array
    {
        return ['type' => 'sum', 'of' => $this->of];
    }
}
