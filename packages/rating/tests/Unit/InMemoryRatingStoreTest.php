<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Rating\Elo\EloRating;
use Tilttek\Rating\Elo\EloSystem;
use Tilttek\Rating\Exception\RatingConflict;
use Tilttek\Rating\Exception\ResultAlreadyRecorded;
use Tilttek\Rating\MemberRating;
use Tilttek\Rating\Store\InMemoryRatingStore;
use Tilttek\Rating\Visibility;

#[CoversClass(InMemoryRatingStore::class)]
#[CoversClass(RatingConflict::class)]
#[CoversClass(ResultAlreadyRecorded::class)]
final class InMemoryRatingStoreTest extends TestCase
{
    public function testRatingsAreKeptAtTheNextVersion(): void
    {
        $store = new InMemoryRatingStore();
        $store->save([$this->rating('ann', 150_000), $this->rating('bob', 150_000)]);

        $found = $store->find('chess', ['ann', 'cy']);
        self::assertSame(['ann'], array_keys($found));
        self::assertSame(1, $found['ann']->version);
        self::assertSame([], $store->find('go', ['ann']));

        $store->save([$found['ann']->after(new EloRating(151_000), 151_000, 'r1')]);
        self::assertSame(2, $store->find('chess', ['ann'])['ann']->version);
        self::assertSame(151_000, $store->find('chess', ['ann'])['ann']->score);
    }

    public function testAStaleOrRacingRatingKeepsNothing(): void
    {
        $store = new InMemoryRatingStore();
        $store->save([$this->rating('ann', 150_000)]);

        foreach ([[$this->rating('cy', 1), $this->rating('ann', 2)], [$this->rating('ann', 3)->withVersion(2)]] as $ratings) {
            try {
                $store->save($ratings);
                self::fail('A stale rating was kept.');
            } catch (RatingConflict $conflict) {
                self::assertSame('The rating of the member ann for the game chess changed since it was read.', $conflict->getMessage());
            }
        }

        self::assertSame([], $store->find('chess', ['cy']));
        self::assertSame(150_000, $store->find('chess', ['ann'])['ann']->score);
    }

    public function testTheStandingsListPublicRatingsWithAGameByScoreThenMember(): void
    {
        $store = new InMemoryRatingStore();
        $store->save([
            $this->rating('dee', 160_000, Visibility::Public),
            $this->rating('bob', 170_000, Visibility::Public),
            $this->rating('ann', 170_000, Visibility::Public),
            $this->rating('cy', 190_000),
            MemberRating::initial('chess', 'eve', new EloSystem())->withVisibility(Visibility::Public),
            $this->rating('fay', 100_000, Visibility::Public),
        ]);

        self::assertSame(['ann', 'bob', 'dee', 'fay'], array_map(static fn (MemberRating $rating): string => $rating->member, $store->standings('chess', 10, 1)));
        self::assertSame(['ann', 'bob', 'dee', 'fay'], array_map(static fn (MemberRating $rating): string => $rating->member, $store->standings('chess', 10, 0)));
        self::assertSame(['ann', 'bob'], array_map(static fn (MemberRating $rating): string => $rating->member, $store->standings('chess', 2, 1)));
        self::assertSame([], $store->standings('go', 10, 1));
    }

    public function testTheStandingsMayAskForMoreGames(): void
    {
        $store = new InMemoryRatingStore();
        $store->save([
            $this->rating('ann', 170_000, Visibility::Public, 9),
            $this->rating('bob', 160_000, Visibility::Public, 10),
            $this->rating('cy', 150_000, Visibility::Public, 11),
        ]);

        self::assertSame(['bob', 'cy'], array_map(static fn (MemberRating $rating): string => $rating->member, $store->standings('chess', 10, 10)));
        self::assertSame(['ann', 'bob', 'cy'], array_map(static fn (MemberRating $rating): string => $rating->member, $store->standings('chess', 10, 9)));
    }

    public function testAResultIsCountedOnceWithItsRatings(): void
    {
        $store = new InMemoryRatingStore();
        self::assertFalse($store->hasRecorded('chess', 'r1'));

        $store->save([$this->rating('ann', 150_000)], 'r1');
        self::assertTrue($store->hasRecorded('chess', 'r1'));
        self::assertFalse($store->hasRecorded('go', 'r1'));
        self::assertFalse($store->hasRecorded('chess', 'r2'));

        try {
            $store->save([$store->find('chess', ['ann'])['ann']->after(new EloRating(1), 1, 'r1')], 'r1');
            self::fail('A result was counted twice.');
        } catch (ResultAlreadyRecorded $resultAlreadyRecorded) {
            self::assertSame('The result r1 of the game chess is recorded already.', $resultAlreadyRecorded->getMessage());
        }

        self::assertSame(150_000, $store->find('chess', ['ann'])['ann']->score);

        try {
            $store->save([$this->rating('ann', 2)], 'r2');
            self::fail('A stale rating was kept.');
        } catch (RatingConflict) {
            self::assertFalse($store->hasRecorded('chess', 'r2'));
        }

        $store->save([], 'r3');
        self::assertFalse($store->hasRecorded('chess', 'r3'));
        $store->save([$store->find('chess', ['ann'])['ann']]);
        self::assertSame(2, $store->find('chess', ['ann'])['ann']->version);
    }

    public function testAGameIsReplacedWhole(): void
    {
        $store = new InMemoryRatingStore();
        $store->save([$this->rating('ann', 150_000), $this->rating('bob', 150_000)]);
        $store->save([$store->find('chess', ['ann'])['ann']]);
        $store->save([$this->rating('dee', 1)], 'r1');
        $store->save([new MemberRating('go', 'ann', 'elo', new EloRating(1), 1)], 'g1');
        $store->replace('chess', [$this->rating('ann', 140_000), $this->rating('cy', 130_000)], ['r7', 'r8']);

        self::assertFalse($store->hasRecorded('chess', 'r1'));
        self::assertTrue($store->hasRecorded('chess', 'r7'));
        self::assertTrue($store->hasRecorded('chess', 'r8'));
        self::assertTrue($store->hasRecorded('go', 'g1'));

        self::assertSame(['ann' => 3, 'cy' => 1], array_map(static fn (MemberRating $rating): int => $rating->version, $store->find('chess', ['ann', 'bob', 'cy'])));
        self::assertSame(['ann', 'cy'], array_map(static fn (MemberRating $rating): string => $rating->member, $store->ofGame('chess')));
        self::assertSame([], $store->ofGame('shogi'));
    }

    public function testAMemberIsExportedAndForgotten(): void
    {
        $store = new InMemoryRatingStore();
        $store->save([$this->rating('ann', 150_000), $this->rating('bob', 150_000)]);
        $store->save([new MemberRating('go', 'ann', 'elo', new EloRating(1), 1)]);

        self::assertSame(['chess', 'go'], array_map(static fn (MemberRating $rating): string => $rating->game, $store->ofMember('ann')));
        $store->forget('ann');
        self::assertSame([], $store->ofMember('ann'));
        self::assertCount(1, $store->ofMember('bob'));
    }

    private function rating(string $member, int $score, Visibility $visibility = Visibility::Private, int $games = 1): MemberRating
    {
        return new MemberRating('chess', $member, 'elo', new EloRating($score), $score, $games, $score, $visibility);
    }
}
