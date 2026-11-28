<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables;

use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Expression\Pool;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Exception\LookupRefused;
use Tilttek\RpgSystems\Translations;

/**
 * One value of a row: a text in one or more languages, an integer, or a dice expression kept in the canonical notation
 * of `tilttek/dice`.
 */
final readonly class Cell
{
    /** The largest integer a cell holds, either way. */
    public const int MAX_NUMBER = 1_000_000_000;

    /**
     * @param Translations|int|string $value the texts of a text cell, the integer of a number cell, the canonical
     *                                       notation of a dice cell
     */
    private function __construct(
        public ColumnKind $kind,
        private Translations|int|string $value,
    ) {
    }

    /**
     * @throws InvalidTable when the text is empty
     */
    public static function text(Translations $text): self
    {
        if ($text->isEmpty()) {
            throw InvalidTable::because('A text cell holds no text.');
        }

        return new self(ColumnKind::Text, $text);
    }

    /**
     * @throws InvalidTable when the integer is beyond `MAX_NUMBER`
     */
    public static function number(int $number): self
    {
        if ($number > self::MAX_NUMBER || $number < -self::MAX_NUMBER) {
            throw InvalidTable::because(\sprintf('A number cell holds a value from %d to %d.', -self::MAX_NUMBER, self::MAX_NUMBER));
        }

        return new self(ColumnKind::Number, $number);
    }

    /**
     * @param string $notation a dice expression `tilttek/dice` reads, kept in its canonical notation: `d4+1` becomes `1d4+1`
     *
     * @throws InvalidTable when `tilttek/dice` cannot read it
     */
    public static function dice(string $notation): self
    {
        $dice = self::roller();

        try {
            $canonical = $dice->write($dice->parse($notation));
        } catch (DiceException $diceException) {
            throw InvalidTable::because(\sprintf('The dice cell "%s" cannot be read: %s', $notation, $diceException->getMessage()));
        }

        return new self(ColumnKind::Dice, $canonical);
    }

    /**
     * The text in a locale, with the fallbacks of `Translations::in()`; the integer or the notation as written for the
     * other kinds.
     */
    public function display(string $locale = 'en'): string
    {
        return $this->value instanceof Translations ? $this->value->in($locale) ?? '' : (string) $this->value;
    }

    /**
     * The texts of a text cell, by locale.
     *
     * @throws LookupRefused when the cell holds no text
     */
    public function translations(): Translations
    {
        return $this->value instanceof Translations ? $this->value : throw LookupRefused::because(\sprintf('The cell holds a %s, not a text.', $this->kind->value));
    }

    /**
     * @throws LookupRefused when the cell holds no integer
     */
    public function toInt(): int
    {
        return \is_int($this->value) ? $this->value : throw LookupRefused::because(\sprintf('The cell holds a %s, not a number.', $this->kind->value));
    }

    /**
     * The canonical notation of a dice cell, `1d4+1`.
     *
     * @throws LookupRefused when the cell holds no dice
     */
    public function notation(): string
    {
        return \is_string($this->value) ? $this->value : throw LookupRefused::because(\sprintf('The cell holds a %s, not dice.', $this->kind->value));
    }

    /**
     * The dice of a dice cell, read by `tilttek/dice` and ready to roll.
     *
     * @throws LookupRefused when the cell holds no dice
     */
    public function pool(): Pool
    {
        return self::roller()->parse($this->notation());
    }

    private static function roller(): DiceRoller
    {
        // A roller with the default limits keeps nothing between two calls: one serves every cell.
        static $dice = new DiceRoller();

        return $dice;
    }
}
