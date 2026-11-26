<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Exception\InvalidSheet;
use Tilttek\ScoreSheets\Operation\Operation;

/**
 * One line of a sheet: a category the scorer enters, between its bounds, or one an operation computes from the
 * categories declared before it. A category counts in the total unless it is declared otherwise — a count of sets
 * whose points another category computes, say.
 */
final readonly class Category
{
    /** The widest bounds of an entered value, positive or negative. */
    public const int MAX_ENTRY = 1_000_000;

    public string $label;

    /** The bounds of an entered value; the widest for a computed category, which takes none. */
    public int $min;

    public int $max;

    /**
     * @param ?string $label what the sheet prints, the key when none is given: up to 100 characters, no control
     *                       character
     *
     * @throws InvalidSheet when the key, the label or the bounds are refused
     */
    public function __construct(
        public string $key,
        ?string $label = null,
        public ?Operation $operation = null,
        public bool $counted = true,
        ?int $min = null,
        ?int $max = null,
    ) {
        Name::key($key, 'A category key');
        $this->label = $label ?? $key;

        if (1 !== preg_match('/^[^\p{C}]{1,100}$/uD', $this->label)) {
            throw InvalidSheet::because(\sprintf('The label of the category "%s" is 1 to 100 characters, none of them a control character.', $key));
        }

        if ($operation instanceof Operation) {
            if (null !== $min || null !== $max) {
                throw InvalidSheet::because(\sprintf('The computed category "%s" takes no bounds.', $key));
            }

            $this->min = -self::MAX_ENTRY;
            $this->max = self::MAX_ENTRY;

            return;
        }

        $this->min = $min ?? -self::MAX_ENTRY;
        $this->max = $max ?? self::MAX_ENTRY;

        if ($this->min < -self::MAX_ENTRY || $this->max > self::MAX_ENTRY || $this->min > $this->max) {
            throw InvalidSheet::because(\sprintf('The bounds of the category "%s" lie between -%2$d and %2$d, the lower first.', $key, self::MAX_ENTRY));
        }
    }

    public function isEntered(): bool
    {
        return !$this->operation instanceof Operation;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'counted' => $this->counted,
            ...($this->operation instanceof Operation ? ['operation' => $this->operation->toArray()] : ['min' => $this->min, 'max' => $this->max]),
        ];
    }
}
