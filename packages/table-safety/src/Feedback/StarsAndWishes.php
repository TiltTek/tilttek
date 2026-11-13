<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Feedback;

use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * A participant's stars and wishes after a session: what they enjoyed, and what they would like to see. Each is a
 * trimmed text of 1 to `MAX_LENGTH` characters, at most `MAX_ITEMS` of each; empty texts are left out.
 */
final readonly class StarsAndWishes
{
    public const int MAX_ITEMS = 10;

    public const int MAX_LENGTH = 500;

    /** @var list<string> */
    public array $stars;

    /** @var list<string> */
    public array $wishes;

    /**
     * @param list<string> $stars
     * @param list<string> $wishes
     */
    public function __construct(array $stars = [], array $wishes = [])
    {
        $this->stars = $this->texts($stars, 'stars');
        $this->wishes = $this->texts($wishes, 'wishes');
    }

    public function isEmpty(): bool
    {
        return [] === $this->stars && [] === $this->wishes;
    }

    /**
     * @return array{stars: list<string>, wishes: list<string>}
     */
    public function toArray(): array
    {
        return ['stars' => $this->stars, 'wishes' => $this->wishes];
    }

    /**
     * @param array<array-key, mixed> $stored what `toArray()` returned
     */
    public static function fromArray(array $stored): self
    {
        $list = static fn (mixed $texts): array => \is_array($texts) ? array_values(array_filter($texts, is_string(...))) : [];

        return new self($list($stored['stars'] ?? null), $list($stored['wishes'] ?? null));
    }

    /**
     * @param list<string> $texts
     *
     * @return list<string>
     */
    private function texts(array $texts, string $what): array
    {
        $kept = [];

        foreach ($texts as $text) {
            $text = trim($text);

            if ('' === $text) {
                continue;
            }

            if (mb_strlen($text) > self::MAX_LENGTH) {
                throw InvalidAnswer::because(\sprintf('Each of the %s has at most %d characters.', $what, self::MAX_LENGTH));
            }

            $kept[] = $text;
        }

        if (\count($kept) > self::MAX_ITEMS) {
            throw InvalidAnswer::because(\sprintf('At most %d %s are given.', self::MAX_ITEMS, $what));
        }

        return $kept;
    }
}
