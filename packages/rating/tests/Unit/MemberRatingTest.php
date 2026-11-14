<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Rating\Elo\EloRating;
use Tilttek\Rating\Elo\EloSystem;
use Tilttek\Rating\Exception\InvalidRating;
use Tilttek\Rating\MemberRating;
use Tilttek\Rating\OpenSkill\OpenSkillSystem;
use Tilttek\Rating\Visibility;

#[CoversClass(MemberRating::class)]
final class MemberRatingTest extends TestCase
{
    public function testANewMemberStartsPrivateAtTheInitialRating(): void
    {
        $rating = MemberRating::initial('chess', 'ann', new EloSystem());

        self::assertSame('elo', $rating->system);
        self::assertEquals(new EloRating(150_000), $rating->rating);
        self::assertSame(150_000, $rating->score);
        self::assertSame(1500.0, $rating->value());
        self::assertSame(0, $rating->games);
        self::assertNull($rating->peak);
        self::assertSame(Visibility::Private, $rating->visibility);
        self::assertNull($rating->lastResult);
        self::assertSame(0, $rating->version);
    }

    public function testEachRatedResultCountsAndKeepsTheHighestScore(): void
    {
        $first = MemberRating::initial('chess', 'ann', new EloSystem())->withVersion(3)->after(new EloRating(149_000), 149_000, 'r1');

        self::assertSame(1, $first->games);
        self::assertSame(149_000, $first->peak);
        self::assertSame('r1', $first->lastResult);
        self::assertSame(3, $first->version);
        self::assertFalse(MemberRating::initial('chess', 'ann', new EloSystem())->peaksAt(200_000));
        self::assertTrue($first->peaksAt(149_001));
        self::assertFalse($first->peaksAt(149_000));

        $second = $first->after(new EloRating(150_000), 150_000, 'r2');
        $third = $second->after(new EloRating(140_000), 140_000, 'r3');
        self::assertSame(150_000, $second->peak);
        self::assertSame(150_000, $third->peak);
        self::assertSame(3, $third->games);
        self::assertSame(140_000, $third->score);
    }

    public function testVisibilityAndVersionChangeNothingElse(): void
    {
        $rating = MemberRating::initial('chess', 'ann', new EloSystem())->after(new EloRating(151_600), 151_600, 'r1');
        $public = $rating->withVisibility(Visibility::Public)->withVersion(4);

        self::assertSame(Visibility::Public, $public->visibility);
        self::assertSame(4, $public->version);
        self::assertSame([...$rating->toArray(), 'visibility' => 'public', 'version' => 4], $public->toArray());
    }

    public function testARatingIsWrittenAndReadBack(): void
    {
        $system = new OpenSkillSystem();
        $rating = MemberRating::initial('go', 'ann', $system)->after($system->initial(), 0, 'r1')->withVisibility(Visibility::Public)->withVersion(2);
        $data = $rating->toArray();

        self::assertSame(['game' => 'go', 'member' => 'ann', 'system' => 'openskill-pl', 'rating' => ['mu' => 25_000_000, 'sigma' => 8_333_333], 'score' => 0, 'games' => 1, 'peak' => 0, 'visibility' => 'public', 'last_result' => 'r1', 'version' => 2], $data);
        self::assertEquals($rating, MemberRating::fromArray($data, $system));
        self::assertEquals(MemberRating::initial('go', 'ann', $system), MemberRating::fromArray(MemberRating::initial('go', 'ann', $system)->toArray(), $system));
    }

    public function testWhatDoesNotReadAsARatingIsRefused(): void
    {
        $system = new EloSystem();
        $data = MemberRating::initial('chess', 'ann', $system)->toArray();
        $shape = 'A stored rating has a game, a member, a system, a rating, games, a visibility and a version.';
        $bad = [
            [[...$data, 'game' => 1], $shape],
            [[...$data, 'member' => 1], $shape],
            [[...$data, 'system' => 1], $shape],
            [[...$data, 'rating' => 1], $shape],
            [[...$data, 'games' => '1'], $shape],
            [[...$data, 'visibility' => 1], $shape],
            [[...$data, 'version' => '1'], $shape],
            [[...$data, 'peak' => '1'], $shape],
            [[...$data, 'last_result' => 1], $shape],
            [[...$data, 'system' => 'openskill-pl'], 'A rating of the system openskill-pl is not read by the system elo.'],
            [[...$data, 'visibility' => 'friends'], 'A rating is private or public.'],
            [[...$data, 'rating' => ['mu' => 1]], 'An Elo rating is its hundredths, a whole number.'],
            [[...$data, 'games' => -1], 'The games and the version of a rating are 0 or more.'],
            [[...$data, 'version' => -1], 'The games and the version of a rating are 0 or more.'],
            [[...$data, 'last_result' => 'r 1'], 'The identifier of a result is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter or a digit.'],
            [[...$data, 'member' => ''], 'The identifier of a member is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter or a digit.'],
            [[...$data, 'game' => ''], 'The identifier of a game is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter or a digit.'],
        ];

        foreach ($bad as [$values, $message]) {
            try {
                MemberRating::fromArray($values, $system);
                self::fail('Values were read as a rating: '.$message);
            } catch (InvalidRating $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }
    }
}
