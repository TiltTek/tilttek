<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit\Choice;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameNight\Choice\Criteria;
use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\GameNight;
use Tilttek\GameNight\NightStatus;
use Tilttek\GameNight\Table;

#[CoversClass(Criteria::class)]
final class CriteriaTest extends TestCase
{
    private function night(): GameNight
    {
        return new GameNight(
            'n-1',
            new DateTimeImmutable('2027-03-05 19:00'),
            new DateTimeImmutable('2027-03-05 22:30'),
            'v',
            'h',
            [new Table('t-1', 4), new Table('t-2', 5), new Table('t-3', 3)],
            NightStatus::Published,
        )->registered('m-1', true)->registered('m-2')->registered('m-3')->seated('m-1', 't-1')->seated('m-3', 't-3');
    }

    public function testTheCriteriaKeepTheirValues(): void
    {
        $criteria = new Criteria(4, 90, ['m-1'], ['g@1'], 5);

        self::assertSame(4, $criteria->players);
        self::assertSame(90, $criteria->minutes);
        self::assertSame(['m-1'], $criteria->owners);
        self::assertSame(['g@1'], $criteria->library);
        self::assertSame(5, $criteria->limit);
        self::assertSame(10, new Criteria(1, 1)->limit);
        self::assertSame(1, new Criteria(1, 1, limit: 1)->limit);
        self::assertSame(GameNight::MAX_PLACES, new Criteria(GameNight::MAX_PLACES, GameNight::MAX_MINUTES, limit: Criteria::MAX_SUGGESTIONS)->players);
    }

    public function testTheWholeNightIsItsConfirmedPlacesAndMinutes(): void
    {
        $criteria = Criteria::forNight($this->night(), ['g-1'], 3);

        self::assertSame(4, $criteria->players);
        self::assertSame(210, $criteria->minutes);
        self::assertSame(['m-1', 'm-2', 'm-3'], $criteria->owners);
        self::assertSame(['g-1'], $criteria->library);
        self::assertSame(3, $criteria->limit);
        self::assertSame(10, Criteria::forNight($this->night())->limit);
    }

    public function testATableIsThePlacesTakenAtItOrItsSeats(): void
    {
        $seated = Criteria::forTable($this->night(), 't-1', ['g-1'], 4);

        self::assertSame(2, $seated->players);
        self::assertSame(['m-1'], $seated->owners);
        self::assertSame(210, $seated->minutes);
        self::assertSame(['g-1'], $seated->library);
        self::assertSame(4, $seated->limit);

        self::assertSame(['m-3'], Criteria::forTable($this->night(), 't-3')->owners);

        $empty = Criteria::forTable($this->night(), 't-2');

        self::assertSame(5, $empty->players);
        self::assertSame([], $empty->owners);
        self::assertSame(10, $empty->limit);
    }

    public function testAnUnknownTableHasNoCriteria(): void
    {
        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIs('The table t-9 is not one of the night.');

        Criteria::forTable($this->night(), 't-9');
    }

    /**
     * @return iterable<string, array{Closure(): Criteria, string}>
     */
    public static function invalidCriteria(): iterable
    {
        yield 'no player' => [static fn (): Criteria => new Criteria(0, 60), 'A game is chosen for 1 to 1500 players.'];
        yield 'too many players' => [static fn (): Criteria => new Criteria(1501, 60), 'A game is chosen for 1 to 1500 players.'];
        yield 'no minute' => [static fn (): Criteria => new Criteria(2, 0), 'A game is chosen for 1 to 1440 minutes.'];
        yield 'too many minutes' => [static fn (): Criteria => new Criteria(2, 1441), 'A game is chosen for 1 to 1440 minutes.'];
        yield 'no suggestion' => [static fn (): Criteria => new Criteria(2, 60, limit: 0), 'A choice gives 1 to 50 suggestions.'];
        yield 'too many suggestions' => [static fn (): Criteria => new Criteria(2, 60, limit: 51), 'A choice gives 1 to 50 suggestions.'];
        yield 'too many owners' => [static fn (): Criteria => new Criteria(2, 60, array_map(static fn (int $i): string => 'm'.$i, range(0, Criteria::MAX_OWNERS))), 'A choice reads at most 1500 members whose games can be brought.'];
        yield 'owner twice' => [static fn (): Criteria => new Criteria(2, 60, ['m', 'm']), 'A choice reads each of the members whose games can be brought once.'];
        yield 'too many games' => [static fn (): Criteria => new Criteria(2, 60, [], array_map(static fn (int $i): string => 'g'.$i, range(0, Criteria::MAX_LIBRARY))), 'A choice reads at most 500 games of the library.'];
        yield 'game twice' => [static fn (): Criteria => new Criteria(2, 60, [], ['g', 'g']), 'A choice reads each of the games of the library once.'];
        yield 'owner shape' => [static fn (): Criteria => new Criteria(2, 60, ['m@x']), 'The identifier of a member'];
        yield 'game shape' => [static fn (): Criteria => new Criteria(2, 60, [], ['1']), 'The identifier of a game'];
        yield 'empty night' => [static fn (): Criteria => Criteria::forNight(new GameNight('n', new DateTimeImmutable('2027-01-01 10:00'), new DateTimeImmutable('2027-01-01 11:00'), 'v', 'h')), 'A game is chosen for 1 to 1500 players.'];
    }

    /**
     * @param Closure(): Criteria $build
     */
    #[DataProvider('invalidCriteria')]
    public function testInvalidCriteriaAreRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTheListsHoldAtTheirBounds(): void
    {
        $owners = array_map(static fn (int $i): string => 'm'.$i, range(1, Criteria::MAX_OWNERS));
        $library = array_map(static fn (int $i): string => 'g'.$i, range(1, Criteria::MAX_LIBRARY));
        $criteria = new Criteria(2, 60, $owners, $library);

        self::assertCount(1500, $criteria->owners);
        self::assertCount(500, $criteria->library);
    }
}
