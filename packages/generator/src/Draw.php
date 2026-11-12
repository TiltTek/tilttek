<?php

declare(strict_types=1);

namespace Tilttek\Generator;

use Stringable;
use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * What a draw asks for: a number of results, unique or not, a seed that makes the same draw come again, and the state
 * a generator with a memory left at its last draw.
 */
final readonly class Draw
{
    /**
     * The most values a state holds.
     */
    public const int STATE_VALUES = 32;

    /**
     * The longest value of a state, in characters.
     */
    public const int STATE_LENGTH = 1024;

    /**
     * @var array<string, string>|null the previous state, its values as texts, or null for a first draw
     */
    public ?array $state;

    /**
     * @param array<array-key, mixed>|null $state the state the last draw returned, read as texts
     */
    public function __construct(public int $count = 1, public bool $unique = false, public ?string $seed = null, ?array $state = null)
    {
        if ($count < 1) {
            throw InvalidDefinition::at('draw', 'a draw asks for at least one result');
        }

        if ('' === $seed) {
            throw InvalidDefinition::at('draw', 'a seed is not empty');
        }

        if (null !== $seed && !mb_check_encoding($seed, 'UTF-8')) {
            throw InvalidDefinition::at('draw', 'a seed is written in UTF-8');
        }

        $this->state = null === $state ? null : $this->state($state);
    }

    /**
     * @param array<array-key, mixed> $state
     *
     * @return array<string, string>
     */
    private function state(array $state): array
    {
        if (\count($state) > self::STATE_VALUES) {
            throw InvalidDefinition::at('draw', \sprintf('a state holds at most %d values', self::STATE_VALUES));
        }

        $texts = [];

        foreach ($state as $name => $value) {
            if (!\is_string($name) || !\is_scalar($value) && !$value instanceof Stringable || mb_strlen($text = (string) $value) > self::STATE_LENGTH) {
                throw InvalidDefinition::at('draw', \sprintf('a state is a map of names to texts of at most %d characters', self::STATE_LENGTH));
            }

            if (!mb_check_encoding($name, 'UTF-8') || !mb_check_encoding($text, 'UTF-8')) {
                throw InvalidDefinition::at('draw', 'a state is written in UTF-8');
            }

            $texts[$name] = $text;
        }

        return $texts;
    }
}
