<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit\Fixture;

use LogicException;
use Override;
use Random\Engine;
use Random\Randomizer;

/**
 * An engine that gives the numbers it was told, four bytes each: `Randomizer::getInt($min, $max)` returns `$min` plus
 * each number, for numbers within the range. It fails when it runs out, so that a test sees a die it did not expect.
 */
final class ScriptedEngine implements Engine
{
    /**
     * @var list<int>
     */
    private array $numbers;

    public function __construct(int ...$numbers)
    {
        $this->numbers = array_values($numbers);
    }

    /**
     * A randomizer whose `getInt()` gives these offsets from the lowest face.
     */
    public static function randomizer(int ...$offsets): Randomizer
    {
        return new Randomizer(new self(...$offsets));
    }

    #[Override]
    public function generate(): string
    {
        if ([] === $this->numbers) {
            throw new LogicException('The scripted engine has no number left.');
        }

        return pack('V', array_shift($this->numbers));
    }
}
