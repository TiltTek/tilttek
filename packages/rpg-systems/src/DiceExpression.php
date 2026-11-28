<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Expression\Pool;
use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * A roll a system names — the check, advantage, a damage die —, written in the canonical notation of `tilttek/dice`,
 * checked when the record is made and kept in its normalised form.
 */
final readonly class DiceExpression
{
    /** The canonical notation, as `tilttek/dice` writes it back: `2d20kh1`. */
    public string $notation;

    /**
     * @param string $key      the expression's key in its system, `check`
     * @param string $notation the roll in the canonical notation of `tilttek/dice`, `1d20` or `2d20kh1`
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $key,
        string $notation,
        public Translations $names,
        public Provenance $provenance,
    ) {
        Text::key($key, 'a dice expression');

        if ($names->isEmpty()) {
            throw InvalidRecord::because(\sprintf('The dice expression "%s" has no name.', $key));
        }

        $dice = $this->dice();

        try {
            $this->notation = $dice->write($dice->parse(Text::check($notation, \sprintf('The notation of the dice expression "%s"', $key))));
        } catch (DiceException $diceException) {
            throw InvalidRecord::because(\sprintf('The notation "%s" of the dice expression "%s" cannot be read: %s', $notation, $key, $diceException->getMessage()));
        }
    }

    /**
     * The expression read by `tilttek/dice`, ready to roll.
     */
    public function pool(): Pool
    {
        return $this->dice()->parse($this->notation);
    }

    /**
     * The expression in the notation of a language, `2W20kh1` in German.
     */
    public function notationIn(string $locale): string
    {
        $dice = $this->dice();

        return $dice->write($dice->parse($this->notation), $locale);
    }

    private function dice(): DiceRoller
    {
        // A roller with the default limits keeps nothing between two calls: one serves every record.
        static $dice = new DiceRoller();

        return $dice;
    }
}
