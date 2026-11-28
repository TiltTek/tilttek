<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Internal;

use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Expression\Pool;
use Tilttek\RandomCharacter\Exception\InvalidRules;

/**
 * The checks the rules share: keys, field keys and dice notations.
 *
 * @internal
 */
final class Check
{
    /** The key of rules, a system or an attribute, as `tilttek/rpg-systems` writes them. */
    private const string KEY = '/^[a-z0-9](?:[a-z0-9_-]|\.(?=[a-z0-9])){0,95}$/D';

    /** The key of a field: a lowercase letter, then lowercase letters, digits or `_`; no dot, which reads a sub-value. */
    private const string FIELD = '/^[a-z][a-z0-9_]{0,63}$/D';

    /**
     * A key: lowercase ASCII letters, digits, `_`, `-` and single dots between them, 96 characters at most.
     *
     * @throws InvalidRules
     */
    public static function key(string $key, string $what): string
    {
        if (1 !== preg_match(self::KEY, $key)) {
            throw InvalidRules::because(\sprintf('The key of %s "%s" must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them.', $what, $key));
        }

        return $key;
    }

    /**
     * The key of a field: a lowercase ASCII letter, then lowercase letters, digits and `_`, 64 characters at most.
     *
     * @throws InvalidRules
     */
    public static function field(string $key): string
    {
        if (1 !== preg_match(self::FIELD, $key)) {
            throw InvalidRules::because(\sprintf('The key of the field "%s" must be a lowercase letter, then up to 63 lowercase letters, digits or "_".', $key));
        }

        return $key;
    }

    /**
     * A notation read by `tilttek/dice` with its default limits: its canonical form and its pool.
     *
     * @return array{string, Pool}
     *
     * @throws InvalidRules
     */
    public static function dice(string $notation, string $what): array
    {
        // A roller with the default limits keeps nothing between two calls: one serves every rule.
        static $dice = new DiceRoller();

        try {
            $pool = $dice->parse($notation);

            return [$dice->write($pool), $pool];
        } catch (DiceException $diceException) {
            throw InvalidRules::because(\sprintf('The roll "%s" of %s cannot be read: %s', $notation, $what, $diceException->getMessage()));
        }
    }
}
