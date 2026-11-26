<?php

declare(strict_types=1);

namespace Tilttek\FanAwards;

use DateTimeImmutable;
use Tilttek\FanAwards\Exception\InvalidAward;
use Tilttek\FanAwards\Exception\UnknownAward;

/**
 * An edition of the awards — the awards of one year, of one convention —: its key, its name, its 1 to 50 categories,
 * whether it holds a semifinal between the nominations and the final, its phase and the moment it reached it.
 *
 * The key is 1 to 100 ASCII letters, digits, "_", ".", ":" or "-"; the name 1 to 100 characters of UTF-8 without
 * control characters nor surrounding spaces; the categories' keys are unique.
 */
final readonly class Edition
{
    public const int MAX_CATEGORIES = 50;

    /**
     * @var array<string, Category> the categories by key, in their declared order
     */
    public array $categories;

    /**
     * @param array<Category> $categories
     */
    public function __construct(
        public string $key,
        public string $name,
        array $categories,
        public bool $semifinal,
        public Phase $phase,
        public DateTimeImmutable $since,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9_.:-]{1,100}$/D', $key)) {
            throw InvalidAward::because(\sprintf('An edition key is 1 to 100 ASCII letters, digits, "_", ".", ":" or "-"; "%s" is not.', $key));
        }

        if (1 !== preg_match('/^\P{Cc}{1,100}\z/u', $name) || trim($name) !== $name) {
            throw InvalidAward::because('An edition name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.');
        }

        if ([] === $categories || \count($categories) > self::MAX_CATEGORIES) {
            throw InvalidAward::because(\sprintf('An edition holds 1 to %d categories, not %d.', self::MAX_CATEGORIES, \count($categories)));
        }

        $byKey = [];

        foreach ($categories as $category) {
            if (isset($byKey[$category->key])) {
                throw InvalidAward::because(\sprintf('The category "%s" is declared twice.', $category->key));
            }

            $byKey[$category->key] = $category;
        }

        $this->categories = $byKey;
    }

    /**
     * @throws UnknownAward
     */
    public function category(string $key): Category
    {
        return $this->categories[$key] ?? throw UnknownAward::category($this->key, $key);
    }

    /**
     * The edition moved to the phase at the moment.
     */
    public function in(Phase $phase, DateTimeImmutable $at): self
    {
        return new self($this->key, $this->name, $this->categories, $this->semifinal, $phase, $at);
    }
}
