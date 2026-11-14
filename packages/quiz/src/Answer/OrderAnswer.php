<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Answer;

use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Identifier;

/**
 * The items of an ordering question, by identifier, in the order the player puts them.
 */
final readonly class OrderAnswer implements Answer
{
    /** The most items an answer holds: as many as an ordering question offers. */
    public const int MAX_ITEMS = 32;

    /** @var list<string> */
    public array $items;

    /**
     * @param list<string> $items
     */
    public function __construct(array $items)
    {
        if (\count($items) > self::MAX_ITEMS) {
            throw InvalidQuiz::because(\sprintf('An answer orders at most %d items; this one holds %d.', self::MAX_ITEMS, \count($items)));
        }

        foreach ($items as $item) {
            Identifier::check($item, 'item');
        }

        if (\count(array_unique($items)) !== \count($items)) {
            throw InvalidQuiz::because('An answer puts each item in one place only.');
        }

        $this->items = $items;
    }

    public static function of(string ...$items): self
    {
        return new self(array_values($items));
    }
}
