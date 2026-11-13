<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Unit\Fixture;

use Tilttek\Bingo\Square;
use Tilttek\Bingo\SquarePool;
use Tilttek\Bingo\Trigger;

/**
 * The pools and secrets of the tests.
 */
final class Games
{
    public const string SECRET = 'a secret of the tests, 32 bytes!';

    /**
     * Squares `s1` to `s{count}`; each ticked by the verb `booth.visited` on the object `booth:{n}`.
     */
    public static function pool(int $count): SquarePool
    {
        return new SquarePool(array_map(
            static fn (int $n): Square => new Square('s'.$n, 'Square '.$n, new Trigger('booth.visited', 'booth:'.$n)),
            range(1, $count),
        ));
    }
}
