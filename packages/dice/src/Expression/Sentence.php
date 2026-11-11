<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * A text between double quotes, whose placeholders are replaced by results when it is rolled: `"%1 successes"`,
 * `"$1 and $2"`. A text without placeholders is a text or a translation key, as the application reads it — never HTML.
 */
final readonly class Sentence implements Node
{
    /**
     * @var list<string|Placeholder>
     */
    public array $parts;

    /**
     * @param list<string|Placeholder> $parts the texts and placeholders, two texts never next to each other
     */
    public function __construct(array $parts)
    {
        $merged = [];

        foreach ($parts as $part) {
            $last = array_key_last($merged);

            if (\is_string($part) && null !== $last && \is_string($merged[$last])) {
                $merged[$last] .= $part;
            } elseif ('' !== $part) {
                $merged[] = $part;
            }
        }

        $this->parts = $merged;
    }

    /**
     * @return list<Placeholder>
     */
    public function placeholders(): array
    {
        return array_values(array_filter($this->parts, static fn (string|Placeholder $part): bool => $part instanceof Placeholder));
    }
}
