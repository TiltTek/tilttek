<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use JsonException;
use Tilttek\ScoreSheets\Exception\InvalidSheet;
use Tilttek\ScoreSheets\Operation\Operations;

/**
 * The sheet of one game, declared as data: its categories in order, each entered or computed from those before it,
 * the tie-breakers in order, and how the scores of members make the score of a team. The total of a participant is
 * the sum of the categories that count.
 */
final readonly class SheetDefinition
{
    public const int MAX_CATEGORIES = 64;

    public const int MAX_TIEBREAKERS = 8;

    /** The longest JSON declaration, in bytes. */
    public const int MAX_JSON = 65_536;

    /** @var non-empty-array<string, Category> by key, in the declared order */
    private array $categories;

    /** @var list<Tiebreaker> */
    public array $tiebreakers;

    /**
     * @param list<Category>   $categories
     * @param list<Tiebreaker> $tiebreakers
     *
     * @throws InvalidSheet when the declaration does not hold together
     */
    public function __construct(
        public string $game,
        array $categories,
        array $tiebreakers = [],
        public TeamCombine $teams = TeamCombine::Sum,
    ) {
        Name::key($game, 'A game key');

        if ([] === $categories || \count($categories) > self::MAX_CATEGORIES) {
            throw InvalidSheet::because(\sprintf('A sheet declares 1 to %d categories.', self::MAX_CATEGORIES));
        }

        $byKey = [];

        foreach ($categories as $category) {
            if (isset($byKey[$category->key])) {
                throw InvalidSheet::because(\sprintf('The category "%s" is declared twice.', $category->key));
            }

            foreach ($category->operation?->reads() ?? [] as $read) {
                if (!isset($byKey[$read])) {
                    throw InvalidSheet::because(\sprintf('The category "%s" reads "%s", which is not declared before it.', $category->key, $read));
                }
            }

            $byKey[$category->key] = $category;
        }

        if (\count($tiebreakers) > self::MAX_TIEBREAKERS) {
            throw InvalidSheet::because(\sprintf('A sheet declares at most %d tie-breakers.', self::MAX_TIEBREAKERS));
        }

        foreach ($tiebreakers as $tiebreaker) {
            if (!isset($byKey[$tiebreaker->category])) {
                throw InvalidSheet::because(\sprintf('The tie-breaker reads "%s", which the sheet does not declare.', $tiebreaker->category));
            }
        }

        $this->categories = $byKey;
        $this->tiebreakers = $tiebreakers;
    }

    /**
     * @return non-empty-list<Category>
     */
    public function categories(): array
    {
        return array_values($this->categories);
    }

    public function category(string $key): ?Category
    {
        return $this->categories[$key] ?? null;
    }

    /**
     * @return array{game: string, categories: list<array<string, mixed>>, tiebreakers: list<array{category: string, prefer: string}>, teams: string}
     */
    public function toArray(): array
    {
        return [
            'game' => $this->game,
            'categories' => array_map(static fn (Category $category): array => $category->toArray(), $this->categories()),
            'tiebreakers' => array_map(static fn (Tiebreaker $tiebreaker): array => $tiebreaker->toArray(), $this->tiebreakers),
            'teams' => $this->teams->value,
        ];
    }

    /**
     * @throws InvalidSheet when the text is too long, is not JSON, or is not a declaration
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_JSON) {
            throw InvalidSheet::because(\sprintf('A declaration is at most %d bytes of JSON.', self::MAX_JSON));
        }

        try {
            return self::fromArray(json_decode($json, true, 16, \JSON_THROW_ON_ERROR));
        } catch (JsonException $jsonException) {
            throw InvalidSheet::because('The declaration is not valid JSON: '.$jsonException->getMessage().'.');
        }
    }

    /**
     * @throws InvalidSheet when the data is not a declaration `toArray()` could have written
     */
    public static function fromArray(mixed $data): self
    {
        if (!\is_array($data) || !\is_string($data['game'] ?? null) || !\is_array($data['categories'] ?? null) || !array_is_list($data['categories'])) {
            throw InvalidSheet::because('A declaration has a game and a list of categories.');
        }

        $tiebreakers = $data['tiebreakers'] ?? [];

        if (!\is_array($tiebreakers) || !array_is_list($tiebreakers)) {
            throw InvalidSheet::because('The tie-breakers are a list.');
        }

        return new self(
            $data['game'],
            array_map(self::readCategory(...), $data['categories']),
            array_map(self::readTiebreaker(...), $tiebreakers),
            TeamCombine::tryFrom(\is_string($data['teams'] ?? null) ? $data['teams'] : 'sum') ?? throw InvalidSheet::because('The teams combine by sum or best.'),
        );
    }

    private static function readCategory(mixed $data): Category
    {
        if (!\is_array($data) || !\is_string($data['key'] ?? null)) {
            throw InvalidSheet::because('A category has a key.');
        }

        $label = $data['label'] ?? null;
        $counted = $data['counted'] ?? true;
        $min = $data['min'] ?? null;
        $max = $data['max'] ?? null;

        if ((null !== $label && !\is_string($label)) || !\is_bool($counted) || (null !== $min && !\is_int($min)) || (null !== $max && !\is_int($max))) {
            throw InvalidSheet::because(\sprintf('The label, counted, min or max of the category "%s" is of the wrong type.', $data['key']));
        }

        return new Category($data['key'], $label, null === ($data['operation'] ?? null) ? null : Operations::fromArray($data['operation']), $counted, $min, $max);
    }

    private static function readTiebreaker(mixed $data): Tiebreaker
    {
        if (!\is_array($data) || !\is_string($data['category'] ?? null)) {
            throw InvalidSheet::because('A tie-breaker has a category.');
        }

        return new Tiebreaker($data['category'], Direction::tryFrom(\is_string($data['prefer'] ?? null) ? $data['prefer'] : 'most') ?? throw InvalidSheet::because('A tie-breaker prefers the most or the fewest.'));
    }
}
