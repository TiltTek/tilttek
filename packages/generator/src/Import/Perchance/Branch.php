<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Perchance;

/**
 * An entry of a section or a part of a choice: its segments and its weight, as written.
 *
 * @internal
 */
final readonly class Branch
{
    /**
     * @param list<Text|Dice|Range|Reference|Choice> $segments
     * @param string                                 $weight   a positive number, at most three digits after the point kept
     */
    public function __construct(public array $segments, public string $weight = '1')
    {
    }

    /**
     * Whether the entry is plain text.
     */
    public function isPlain(): bool
    {
        return array_all($this->segments, static fn (Text|Dice|Range|Reference|Choice $segment): bool => $segment instanceof Text);
    }

    /**
     * The text of a plain entry.
     */
    public function text(): string
    {
        return implode('', array_map(static fn (Text|Dice|Range|Reference|Choice $segment): string => $segment instanceof Text ? $segment->text : '', $this->segments));
    }
}
