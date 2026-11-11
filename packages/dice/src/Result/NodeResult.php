<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Tilttek\Dice\Exception\CannotRoll;

/**
 * What a node of an expression gave: a number, a text or a list.
 */
interface NodeResult
{
    /**
     * The number the node gave.
     *
     * @throws CannotRoll when it gave a text or a list
     */
    public function value(): int;

    /**
     * What the node gave: a number, a text — plain or a translation key, never HTML — or a list of numbers and texts.
     *
     * @return int|string|list<int|string>
     */
    public function output(): int|string|array;

    /**
     * The sets of dice of the result, in the order they were rolled or listed.
     *
     * @return list<DiceResult>
     */
    public function sets(): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
