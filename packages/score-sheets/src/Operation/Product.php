<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Operation;

use Tilttek\ScoreSheets\Arithmetic;
use Tilttek\ScoreSheets\Exception\InvalidSheet;
use Tilttek\ScoreSheets\Scoring\Step;

/**
 * The product of 1 to 16 categories and a constant factor: the sets a player made times the points of a set, say.
 */
final readonly class Product implements Operation
{
    /** @var non-empty-list<string> */
    public array $of;

    /**
     * @param list<string> $of
     *
     * @throws InvalidSheet when it reads no category or more than 16, or the factor is out of bounds
     */
    public function __construct(
        array $of,
        public int $factor = 1,
    ) {
        $this->of = Operations::categories($of, 'A product');
        Operations::constant($factor, 'The factor of a product');
    }

    public function reads(): array
    {
        return $this->of;
    }

    public function apply(string $category, array $table): array
    {
        $steps = [];

        foreach ($table as $participant => $values) {
            $value = $this->factor;
            $operands = [];

            foreach ($this->of as $key) {
                $operands[$key] = $values[$key];
                $value = Arithmetic::multiply($value, $values[$key], $category);
            }

            $steps[$participant] = new Step($category, 'product', [...$operands, '#factor' => $this->factor], $value);
        }

        return $steps;
    }

    public function toArray(): array
    {
        return ['type' => 'product', 'of' => $this->of, 'factor' => $this->factor];
    }
}
