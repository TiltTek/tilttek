<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Erasure;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Exception\PeriodNotOver;
use Tilttek\Leaderboards\Exception\UnknownPeriod;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Period\PeriodKind;
use Tilttek\Leaderboards\Period\Schedule;
use Tilttek\Leaderboards\Placing;
use Tilttek\Leaderboards\Row;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Standing;
use Tilttek\Leaderboards\Visibility;

#[CoversClass(Score::class)]
#[CoversClass(Appearance::class)]
#[CoversClass(Circle::class)]
#[CoversClass(Row::class)]
#[CoversClass(Standing::class)]
#[CoversClass(Placing::class)]
#[CoversClass(Erasure::class)]
#[CoversClass(InvalidLeaderboard::class)]
#[CoversClass(UnknownPeriod::class)]
#[CoversClass(PeriodNotOver::class)]
final class ValuesTest extends TestCase
{
    public function testAScore(): void
    {
        $at = new DateTimeImmutable('2026-11-07 10:00', new DateTimeZone('America/Toronto'));

        self::assertSame(['board' => 'games', 'period' => '2026-11', 'value' => 0, 'tie_breaks' => [2, 0], 'reached_at' => '2026-11-07T10:00:00-05:00'], new Score('games', '2026-11', self::member('member:1'), 0, $at, [2, 0])->toArray());
        self::assertSame(Score::MAX, new Score('games', '2026-11', self::member('member:1'), Score::MAX, $at)->value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedScores(): iterable
    {
        yield 'below zero' => [-1];
        yield 'above the most' => [Score::MAX + 1];
    }

    #[DataProvider('refusedScores')]
    public function testAScoreIsRefused(int $value): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A score and its tie-breaks are 0 to 1000000000000000; %d is not one.', $value));

        new Score('games', '2026-11', self::member('member:1'), $value, new DateTimeImmutable());
    }

    public function testAppearances(): void
    {
        self::assertEquals(new Appearance(Visibility::Hidden), Appearance::hidden());
        self::assertNull(Appearance::hidden()->pseudonym);
        self::assertSame(Visibility::Named, Appearance::named()->visibility);
        self::assertSame([Visibility::Pseudonym, 'Le Nain Gris'], [Appearance::pseudonym('Le Nain Gris')->visibility, Appearance::pseudonym('Le Nain Gris')->pseudonym]);
        self::assertSame('é', Appearance::pseudonym('é')->pseudonym);
        self::assertSame(str_repeat('é', 40), Appearance::pseudonym(str_repeat('é', 40))->pseudonym);
    }

    /**
     * @return iterable<string, array{Visibility, ?string, string}>
     */
    public static function refusedAppearances(): iterable
    {
        yield 'a pseudonym without one' => [Visibility::Pseudonym, null, 'A member who appears under a pseudonym chooses one, and only then.'];
        yield 'a name with a pseudonym' => [Visibility::Named, 'Gris', 'A member who appears under a pseudonym chooses one, and only then.'];
        yield 'hidden with a pseudonym' => [Visibility::Hidden, 'Gris', 'A member who appears under a pseudonym chooses one, and only then.'];
        yield 'an empty pseudonym' => [Visibility::Pseudonym, '', '"" is not a pseudonym: 1 to 40 characters, no control character, no space at either end.'];
        yield 'a pseudonym too long' => [Visibility::Pseudonym, str_repeat('é', 41), 'is not a pseudonym'];
        yield 'a leading space' => [Visibility::Pseudonym, ' Gris', 'is not a pseudonym'];
        yield 'a trailing space' => [Visibility::Pseudonym, 'Gris ', 'is not a pseudonym'];
        yield 'a single space' => [Visibility::Pseudonym, ' ', 'is not a pseudonym'];
        yield 'a control character' => [Visibility::Pseudonym, "Gr\u{0007}is", 'is not a pseudonym'];
        yield 'a line break at the end' => [Visibility::Pseudonym, "Gris\n", 'is not a pseudonym'];
        yield 'a prefix before a line' => [Visibility::Pseudonym, "x\nGris", 'is not a pseudonym'];
        yield 'not UTF-8' => [Visibility::Pseudonym, "Gr\xFFis", 'is not a pseudonym'];
    }

    #[DataProvider('refusedAppearances')]
    public function testAnAppearanceIsRefused(Visibility $visibility, ?string $pseudonym, string $message): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Appearance($visibility, $pseudonym);
    }

    public function testACircle(): void
    {
        $circle = Circle::of('member:2', 'member:1', 'member:2');

        self::assertSame(['member:1', 'member:2'], $circle->members);
        self::assertTrue($circle->has(self::member('member:1')));
        self::assertFalse($circle->has(self::member('member:3')));
        self::assertCount(1000, new Circle(...array_map(static fn (int $i): ResourceReference => self::member('member:'.$i), range(1, 1000)))->members);
        self::assertSame(['member:1'], Circle::of(...['a' => 'member:1'])->members);
    }

    /**
     * @return iterable<string, array{list<string>, int}>
     */
    public static function refusedCircles(): iterable
    {
        yield 'an empty circle' => [[], 0];
        yield 'a circle too large' => [array_map(static fn (int $i): string => 'member:'.$i, range(1, 1001)), 1001];
    }

    /**
     * @param list<string> $members
     */
    #[DataProvider('refusedCircles')]
    public function testACircleIsRefused(array $members, int $count): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A circle holds 1 to 1000 members; this one holds %d.', $count));

        Circle::of(...$members);
    }

    public function testRowsAndStandings(): void
    {
        $hidden = new Row(1, 10);
        $named = new Row(2, 9, self::member('member:2'));
        $pseudonym = new Row(3, 8, pseudonym: 'Gris');
        $own = new Row(4, 7, self::member('member:4'), self: true);
        $board = new Board('games', Measure::Count, new Schedule(PeriodKind::Ever), ['game.played']);

        self::assertSame([true, false, false, false], [$hidden->isHidden(), $named->isHidden(), $pseudonym->isHidden(), $own->isHidden()]);
        self::assertSame([null, 'member:2', 'Gris', 'member:4', 'Gris'], [$hidden->shownAs(), $named->shownAs(), $pseudonym->shownAs(), $own->shownAs(), new Row(4, 7, self::member('member:4'), 'Gris', true)->shownAs()]);
        self::assertFalse($hidden->self);
        self::assertFalse(new Standing($board, Period::ever(), [$hidden, $named], $own, 4)->ownIsListed());
        self::assertTrue(new Standing($board, Period::ever(), [$hidden, $own], $own, 4)->ownIsListed());

        $empty = new Standing($board, Period::ever(), []);
        self::assertSame([null, 0, null], [$empty->own, $empty->participants, $empty->frozenAt]);
    }

    public function testAPlacing(): void
    {
        self::assertSame(
            ['board' => 'games', 'period' => '2026-11', 'rank' => 2, 'value' => 9, 'reached_at' => '2026-11-07T10:00:00+00:00'],
            new Placing('games', '2026-11', self::member('member:1'), 2, 9, new DateTimeImmutable('2026-11-07 10:00 UTC'))->toArray(),
        );
    }

    public function testErasureCountsNothingByDefault(): void
    {
        $erasure = new Erasure();

        self::assertSame([0, 0, 0, 0], [$erasure->scores, $erasure->placings, $erasure->appearances, $erasure->leagues]);
    }

    public function testTheMessages(): void
    {
        self::assertSame('The board "games" has no period coded "2026-13".', UnknownPeriod::named('games', '2026-13')->getMessage());
        self::assertSame('The board "games" is between two seasons: no period is under way.', UnknownPeriod::now('games')->getMessage());
        self::assertSame('The period "2026-11" of the board "games" is not over: it cannot be frozen yet.', PeriodNotOver::of('games', '2026-11')->getMessage());
    }

    private static function member(string $member): ResourceReference
    {
        return ResourceReference::fromString($member);
    }
}
