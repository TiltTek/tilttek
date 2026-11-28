<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\Result;
use Tilttek\PlayLog\Seat;

#[CoversClass(Play::class)]
#[CoversClass(Seat::class)]
#[CoversClass(Result::class)]
#[CoversClass(InvalidPlay::class)]
final class ValuesTest extends TestCase
{
    public function testAPlayKeepsWhatItIsGiven(): void
    {
        $at = new DateTimeImmutable('2027-03-01 20:00 UTC');
        $play = new Play('p1', 'game@site', 'ana', [new Seat('ana', 12, true), new Seat(null, -3)], Result::Competitive, $at, 90, 'club', '  The back room  ', 4);

        self::assertSame(['p1', 'game@site', 'ana', Result::Competitive, $at, 90, 'club', 'The back room', 4], [$play->id, $play->game, $play->recordedBy, $play->result, $play->playedAt, $play->minutes, $play->group, $play->place, $play->version]);
        self::assertSame([12, -3], array_map(static fn (Seat $seat): ?int => $seat->score, $play->seats));
    }

    public function testAPlayHasDefaults(): void
    {
        $play = new Play('p1', 'game', 'ana', [new Seat()]);

        self::assertSame([Result::Competitive, null, null, null, null, 0], [$play->result, $play->playedAt, $play->minutes, $play->group, $play->place, $play->version]);
    }

    /**
     * @return iterable<string, array{0: callable(): mixed, 1: string}>
     */
    public static function invalidPlays(): iterable
    {
        yield 'no seat' => [static fn (): Play => new Play('p1', 'game', 'ana', []), 'A play has 1 to 100 seats.'];
        yield 'too many seats' => [static fn (): Play => new Play('p1', 'game', 'ana', [...Seat::guests(100), new Seat()]), 'A play has 1 to 100 seats.'];
        yield 'a member twice' => [static fn (): Play => new Play('p1', 'game', 'ana', [new Seat('bo'), new Seat(), new Seat('bo')]), 'The member bo holds one seat of a play.'];
        yield 'a cooperative winner' => [static fn (): Play => new Play('p1', 'game', 'ana', [new Seat(), new Seat('bo', null, true)], Result::CooperativeWin), 'Only the seats of a competitive play are marked as winners.'];
        yield 'an unfinished winner' => [static fn (): Play => new Play('p1', 'game', 'ana', [new Seat(null, null, true)], Result::Unfinished), 'Only the seats of a competitive play are marked as winners.'];
        yield 'no minute' => [static fn (): Play => new Play('p1', 'game', 'ana', [new Seat()], minutes: 0), 'A play lasts 1 to 1440 minutes.'];
        yield 'over a day' => [static fn (): Play => new Play('p1', 'game', 'ana', [new Seat()], minutes: 1441), 'A play lasts 1 to 1440 minutes.'];
        yield 'a negative version' => [static fn (): Play => new Play('p1', 'game', 'ana', [new Seat()], version: -1), 'The version of a play is 0 or more.'];
        yield 'a play as an address' => [static fn (): Play => new Play('p@1', 'game', 'ana', [new Seat()]), 'The identifier of a play is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'];
        yield 'a game out of shape' => [static fn (): Play => new Play('p1', '1game', 'ana', [new Seat()]), 'The identifier of a game is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.'];
        yield 'a recorder as an address' => [static fn (): Play => new Play('p1', 'game', 'ana@example.org', [new Seat()]), 'The identifier of a member is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'];
        yield 'a group out of shape' => [static fn (): Play => new Play('p1', 'game', 'ana', [new Seat()], group: 'a group'), 'The identifier of a group is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'];
        yield 'a long place' => [static fn (): Play => new Play('p1', 'game', 'ana', [new Seat()], place: str_repeat('é', 121)), 'The place of a play is 1 to 120 characters.'];
        yield 'a seat as an address' => [static fn (): Seat => new Seat('bo@example.org'), 'The identifier of a member is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.'];
        yield 'a confirmed anonymous seat' => [static fn (): Seat => new Seat(null, null, false, true), 'An anonymous seat is never confirmed.'];
        yield 'a score too low' => [static fn (): Seat => new Seat(null, -1_000_000_001), 'A score is from -1000000000 to 1000000000.'];
        yield 'a score too high' => [static fn (): Seat => new Seat(null, 1_000_000_001), 'A score is from -1000000000 to 1000000000.'];
        yield 'negative guests' => [static fn (): array => Seat::guests(-1), 'A play has 0 to 100 guests.'];
        yield 'too many guests' => [static fn (): array => Seat::guests(101), 'A play has 0 to 100 guests.'];
    }

    /**
     * @param callable(): mixed $make
     */
    #[DataProvider('invalidPlays')]
    public function testAnInvalidValueIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidPlay::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testTheBoundsAreAllowed(): void
    {
        $play = new Play('p1', 'game', 'ana', Seat::guests(100), minutes: 1440, place: str_repeat('é', 120));
        $short = new Play('p2', 'game', 'ana', [new Seat(null, -1_000_000_000), new Seat(null, 1_000_000_000)], minutes: 1);

        self::assertSame([100, 1440, 120, 1], [\count($play->seats), $play->minutes, mb_strlen((string) $play->place), $short->minutes]);
    }

    public function testAnonymousSeatsMayShareAPlay(): void
    {
        self::assertCount(3, new Play('p1', 'game', 'ana', [new Seat(), new Seat(), new Seat('ana')])->seats);
    }

    public function testSeatsAreMadeFromMembersAndGuests(): void
    {
        self::assertEquals([new Seat('ana'), new Seat('bo')], Seat::members(['ana', 'bo']));
        self::assertEquals([new Seat(), new Seat()], Seat::guests(2));
        self::assertSame([], Seat::guests(0));
        self::assertCount(100, Seat::guests(100));
    }

    public function testASeatKnowsWhomItCountsFor(): void
    {
        $pending = new Seat('ana', 5, true);
        $confirmed = $pending->withConfirmed(true);

        self::assertFalse($pending->isLinkedTo('ana'));
        self::assertTrue($confirmed->isLinkedTo('ana'));
        self::assertFalse($confirmed->isLinkedTo('bo'));
        self::assertFalse($confirmed->isAnonymous());
        self::assertSame([null, 5, true, false, true], [$confirmed->anonymised()->member, $confirmed->anonymised()->score, $confirmed->anonymised()->winner, $confirmed->anonymised()->confirmed, $confirmed->anonymised()->isAnonymous()]);
        self::assertSame([5, true], [$confirmed->score, $confirmed->winner]);
        self::assertFalse($confirmed->withConfirmed(false)->confirmed);
    }

    public function testWhoWonFollowsTheResult(): void
    {
        $seats = [new Seat('ana', 10, true), new Seat('bo', 4)];
        $competitive = new Play('p1', 'game', 'ana', $seats);
        $plain = [new Seat('ana', 10), new Seat('bo', 4)];

        self::assertSame(['ana'], array_map(static fn (Seat $seat): ?string => $seat->member, $competitive->winners()));
        self::assertCount(2, new Play('p1', 'game', 'ana', $plain, Result::CooperativeWin)->winners());
        self::assertSame([], new Play('p1', 'game', 'ana', $plain, Result::CooperativeLoss)->winners());
        self::assertSame([], new Play('p1', 'game', 'ana', $plain, Result::Unfinished)->winners());
        self::assertSame([0 => 'bo'], array_map(static fn (Seat $seat): ?string => $seat->member, new Play('p1', 'game', 'ana', [new Seat('ana'), new Seat('bo', null, true)])->winners()));
    }

    public function testASeatWonByTheResult(): void
    {
        $winner = new Seat('ana', null, true);
        $other = new Seat('bo');

        self::assertSame([true, false], [new Play('p1', 'game', 'ana', [$winner, $other])->won($winner), new Play('p1', 'game', 'ana', [$winner, $other])->won($other)]);
        self::assertTrue(new Play('p1', 'game', 'ana', [$other], Result::CooperativeWin)->won($other));
        self::assertFalse(new Play('p1', 'game', 'ana', [$other], Result::CooperativeLoss)->won($other));
        self::assertFalse(new Play('p1', 'game', 'ana', [$other], Result::Unfinished)->won($other));
    }

    public function testCooperationIsTold(): void
    {
        self::assertSame([false, true, true, false], array_map(static fn (Result $result): bool => $result->isCooperative(), Result::cases()));
    }

    public function testASeatIsFoundByItsMember(): void
    {
        $play = new Play('p1', 'game', 'ana', [new Seat(), new Seat('bo', 3), new Seat('ana', 7)]);

        self::assertSame(3, $play->seat('bo')?->score);
        self::assertSame(7, $play->seat('ana')?->score);
        self::assertNull($play->seat('cy'));
    }

    public function testTheLinkedMembersAreTheConfirmedSeats(): void
    {
        $play = new Play('p1', 'game', 'ana', [new Seat('ana', confirmed: true), new Seat(), new Seat('bo'), new Seat('cy', confirmed: true)]);

        self::assertSame(['ana', 'cy'], $play->linkedMembers());
    }

    public function testEachReaderSeesTheSeatsTheyMay(): void
    {
        $play = new Play('p1', 'game', 'ana', [new Seat('ana', 1, confirmed: true), new Seat('bo', 2), new Seat('cy', 3, confirmed: true), new Seat(null, 4)], version: 2);

        self::assertSame($play, $play->seenBy('ana'));
        self::assertSame(['ana', 'bo', 'cy', null], $this->members($play->seenBy('bo')));
        self::assertSame(['ana', null, 'cy', null], $this->members($play->seenBy('cy')));
        self::assertSame(['ana', null, 'cy', null], $this->members($play->seenBy(null)));
        self::assertSame([1, 2, 3, 4], array_map(static fn (Seat $seat): ?int => $seat->score, $play->seenBy(null)->seats));
        self::assertSame(2, $play->seenBy(null)->version);
    }

    public function testACopyChangesOnlyWhatItSays(): void
    {
        $at = new DateTimeImmutable('2027-03-01 20:00 UTC');
        $play = new Play('p1', 'game', 'ana', [new Seat('ana')], Result::Unfinished, $at, 30, 'club', 'Home', 1);
        $seats = $play->withSeats([new Seat('bo')]);
        $versioned = $play->withVersion(5);

        foreach ([$seats, $versioned] as $copy) {
            self::assertSame(['p1', 'game', 'ana', Result::Unfinished, $at, 30, 'club', 'Home'], [$copy->id, $copy->game, $copy->recordedBy, $copy->result, $copy->playedAt, $copy->minutes, $copy->group, $copy->place]);
        }

        self::assertSame([['bo'], 1], [$this->members($seats), $seats->version]);
        self::assertSame([['ana'], 5], [$this->members($versioned), $versioned->version]);
    }

    /**
     * @return list<?string>
     */
    private function members(Play $play): array
    {
        return array_map(static fn (Seat $seat): ?string => $seat->member, $play->seats);
    }
}
