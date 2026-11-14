<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * Where the text of a list, an entry or a generator comes from: its source, its licence, and the attribution its
 * licence asks for, shown with the results that used it.
 */
final readonly class Attribution
{
    /**
     * The longest of the three texts, in characters.
     */
    public const int LENGTH = 1024;

    public function __construct(public ?string $source = null, public ?string $licence = null, public ?string $attribution = null)
    {
        if (null === $source && null === $licence && null === $attribution) {
            throw InvalidDefinition::at('attribution', 'an attribution has a source, a licence or an attribution');
        }

        foreach ($this->toArray() as $name => $text) {
            if ('' === trim($text) || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > self::LENGTH) {
                throw InvalidDefinition::at('attribution', \sprintf('the %s is a text in UTF-8 of 1 to %d characters', $name, self::LENGTH));
            }
        }
    }

    /**
     * The texts it has, by name.
     *
     * @return array{source?: string, licence?: string, attribution?: string}
     */
    public function toArray(): array
    {
        return array_filter(['source' => $this->source, 'licence' => $this->licence, 'attribution' => $this->attribution], static fn (?string $text): bool => null !== $text);
    }
}
