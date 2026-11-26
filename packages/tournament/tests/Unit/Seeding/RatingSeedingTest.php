<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit\Seeding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Tilttek\Rating\Contract\RatingStore;
use Tilttek\Rating\Elo\EloRating;
use Tilttek\Rating\MemberRating;
use Tilttek\Rating\Store\InMemoryRatingStore;
use Tilttek\Tournament\Seeding\InitialOrder;
use Tilttek\Tournament\Seeding\RatingSeeding;

/**
 * Runs where `tilttek/rating` is installed: the package on its own, with its development requirements.
 */
#[CoversClass(RatingSeeding::class)]
#[RequiresMethod(RatingStore::class, 'find')]
final class RatingSeedingTest extends TestCase
{
    public function testTheStrengthsAreTheScoresOfTheRatingsOfTheGame(): void
    {
        $store = new InMemoryRatingStore();
        $store->save([
            new MemberRating('chess', 'a', 'elo', new EloRating(150000), 150000),
            new MemberRating('chess', 'b', 'elo', new EloRating(182000), 182000),
            new MemberRating('go', 'c', 'elo', new EloRating(199000), 199000),
        ]);
        $seeding = new RatingSeeding($store, 'chess');

        self::assertSame(['a' => 150000, 'b' => 182000], $seeding->strengths(['a', 'b', 'c']));
        self::assertSame(['b', 'a', 'c'], InitialOrder::of(['a', 'b', 'c'], 'seed', $seeding));
    }
}
