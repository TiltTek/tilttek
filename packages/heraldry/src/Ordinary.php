<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * The honourable ordinaries: the broad geometric pieces laid on the field.
 */
enum Ordinary: string
{
    case Chief = 'chief';
    case Fess = 'fess';
    case Pale = 'pale';
    case Bend = 'bend';
    case BendSinister = 'bend_sinister';
    case Chevron = 'chevron';
    case Cross = 'cross';
    case Saltire = 'saltire';
    case Bordure = 'bordure';

    /**
     * How many charges the ordinary may carry on itself; none for a bordure.
     *
     * @return list<int>
     */
    public function chargesOn(): array
    {
        return match ($this) {
            self::Chief, self::Fess, self::Pale, self::Bend, self::BendSinister => [1, 2, 3],
            self::Chevron => [1, 3],
            self::Cross, self::Saltire => [1],
            self::Bordure => [],
        };
    }

    /**
     * How many charges may lie on the field beside the ordinary: any number in the rest of the field for a chief or a
     * bordure, a fixed number around the others, none around a cross or a saltire.
     *
     * @return list<int>
     */
    public function chargesBeside(): array
    {
        return match ($this) {
            self::Chief, self::Bordure => [1, 2, 3],
            self::Fess => [2, 3],
            self::Pale, self::Bend, self::BendSinister => [2],
            self::Chevron => [3],
            self::Cross, self::Saltire => [],
        };
    }

    /**
     * Whether the charges beside it lie around it, written "between" in English and "accompanied" or "flanked" in
     * French, rather than in the rest of the field.
     */
    public function surroundsCharges(): bool
    {
        return !\in_array($this, [self::Chief, self::Bordure], true);
    }
}
