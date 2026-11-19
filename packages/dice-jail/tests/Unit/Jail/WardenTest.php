<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Tests\Unit\Jail;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\DiceJail\Exception\InvalidJailEntry;
use Tilttek\DiceJail\Exception\JailConflict;
use Tilttek\DiceJail\Exception\JailRefused;
use Tilttek\DiceJail\Jail\EventKind;
use Tilttek\DiceJail\Jail\Evidence;
use Tilttek\DiceJail\Jail\Inmate;
use Tilttek\DiceJail\Jail\JailEvent;
use Tilttek\DiceJail\Jail\Warden;
use Tilttek\DiceJail\Statistics\ExpectedFaces;
use Tilttek\DiceJail\Statistics\GoodnessOfFit;
use Tilttek\DiceJail\Statistics\Tally;
use Tilttek\DiceJail\Statistics\Verdict;
use Tilttek\DiceJail\Store\InMemoryJailStore;

#[CoversClass(Warden::class)]
#[CoversClass(Inmate::class)]
#[CoversClass(JailEvent::class)]
#[CoversClass(Evidence::class)]
#[CoversClass(InMemoryJailStore::class)]
#[CoversClass(InvalidJailEntry::class)]
#[CoversClass(JailRefused::class)]
#[CoversClass(JailConflict::class)]
final class WardenTest extends TestCase
{
    private MockClock $clock;

    private InMemoryJailStore $store;

    private Warden $warden;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-13 20:00:00');
        $this->store = new InMemoryJailStore();
        $this->warden = new Warden($this->store, $this->clock);
    }

    public function testADieGoesToJailAndComesOut(): void
    {
        $inmate = $this->warden->jail('member-7', 'd20:blue', 'Three natural ones in a row.');

        self::assertSame('d20:blue', $inmate->die());
        self::assertEquals(new DateTimeImmutable('2026-11-13 20:00:00'), $inmate->since());
        self::assertSame(EventKind::Jailed, $inmate->jailing->kind);
        self::assertSame('member-7', $inmate->jailing->keeper);
        self::assertSame(1, $inmate->jailing->position);
        self::assertSame('Three natural ones in a row.', $inmate->jailing->reason);
        self::assertNull($inmate->jailing->until);
        self::assertNull($inmate->jailing->evidence);
        self::assertEquals($inmate, $this->warden->inmate('member-7', 'd20:blue'));
        self::assertNull($this->warden->inmate('member-8', 'd20:blue'));

        $this->clock->sleep(3600);
        $release = $this->warden->release('member-7', 'd20:blue', 'It has learned its lesson.');

        self::assertSame(EventKind::Released, $release->kind);
        self::assertSame(2, $release->position);
        self::assertSame('It has learned its lesson.', $release->reason);
        self::assertEquals(new DateTimeImmutable('2026-11-13 21:00:00'), $release->at);
        self::assertNull($this->warden->inmate('member-7', 'd20:blue'));
        self::assertSame([$inmate->jailing, $release], $this->warden->history('member-7', 'd20:blue'));
    }

    public function testASentenceEndsByItself(): void
    {
        $until = new DateTimeImmutable('2026-11-14 20:00:00');
        $inmate = $this->warden->jail('member-7', 'd20:blue', until: $until);

        self::assertSame($until, $inmate->jailing->until);
        self::assertTrue($inmate->isServing(new DateTimeImmutable('2026-11-14 19:59:59')));
        self::assertFalse($inmate->isServing($until));

        $this->clock->modify('2026-11-14 19:59:59');
        self::assertInstanceOf(Inmate::class, $this->warden->inmate('member-7', 'd20:blue'));

        $this->clock->modify('2026-11-14 20:00:00');
        self::assertNull($this->warden->inmate('member-7', 'd20:blue'));
        self::assertSame([], $this->warden->inmates('member-7'));

        // Served: it cannot be released, and may go back to jail.
        try {
            $this->warden->release('member-7', 'd20:blue');
            self::fail('A die that served its sentence was released.');
        } catch (JailRefused $jailRefused) {
            self::assertSame('The die d20:blue is not in jail.', $jailRefused->getMessage());
        }

        self::assertSame(2, $this->warden->jail('member-7', 'd20:blue')->jailing->position);
    }

    public function testASentenceWithoutEndLastsUntilRelease(): void
    {
        $inmate = $this->warden->jail('member-7', 'd6:green');

        self::assertTrue($inmate->isServing(new DateTimeImmutable('2126-01-01')));
    }

    public function testTheEvidenceShownToTheTableIsKept(): void
    {
        $result = new GoodnessOfFit()->test(Tally::of([1, 1, 1, 2, 3]), ExpectedFaces::uniform(3));
        $evidence = Evidence::of($result);
        $inmate = $this->warden->jail('member-7', 'd3', evidence: $evidence);

        self::assertSame($evidence, $inmate->jailing->evidence);
        self::assertSame(Verdict::NotEnoughRolls, $evidence->verdict);
        self::assertSame(5, $evidence->rolls);
        self::assertNull($evidence->pValue);
        self::assertEqualsWithDelta(1.6, $evidence->observedMean, 1.0E-12);
        self::assertEqualsWithDelta(2.0, $evidence->expectedMean, 1.0E-12);
    }

    public function testTheKeepersDiceInJailAreListedInTheOrderTheyWentIn(): void
    {
        $this->warden->jail('member-7', 'd20:blue');
        $this->warden->jail('member-7', 'd6:red', until: new DateTimeImmutable('2026-11-13 20:30:00'));
        $this->warden->jail('member-7', 'd8:black');
        $this->warden->jail('member-9', 'd4');
        $this->warden->release('member-7', 'd8:black');

        self::assertSame(['d20:blue', 'd6:red'], array_map(static fn (Inmate $inmate): string => $inmate->die(), $this->warden->inmates('member-7')));
        self::assertSame(['d20:blue', 'd6:red', 'd8:black'], $this->store->dice('member-7'));

        $this->clock->modify('2026-11-13 20:30:00');
        self::assertSame(['d20:blue'], array_map(static fn (Inmate $inmate): string => $inmate->die(), $this->warden->inmates('member-7')));
        self::assertSame([], $this->warden->inmates('member-1'));
    }

    public function testADieIsJailedOnceAtATime(): void
    {
        $this->warden->jail('member-7', 'd20:blue');

        try {
            $this->warden->jail('member-7', 'd20:blue');
            self::fail('No exception was thrown.');
        } catch (JailRefused $jailRefused) {
            self::assertSame('The die d20:blue is in jail already.', $jailRefused->getMessage());
        }
    }

    public function testAFreeDieIsNotReleased(): void
    {
        try {
            $this->warden->release('member-7', 'd20:blue');
            self::fail('No exception was thrown.');
        } catch (JailRefused $jailRefused) {
            self::assertSame('The die d20:blue is not in jail.', $jailRefused->getMessage());
        }
    }

    public function testAReleasedDieIsNotReleasedAgain(): void
    {
        $this->warden->jail('member-7', 'd20:blue');
        $this->warden->release('member-7', 'd20:blue');

        $this->expectException(JailRefused::class);

        $this->warden->release('member-7', 'd20:blue');
    }

    public function testASentenceEndsAfterItStarts(): void
    {
        try {
            $this->warden->jail('member-7', 'd20:blue', until: new DateTimeImmutable('2026-11-13 20:00:00'));
            self::fail('A sentence that ends as it starts was taken.');
        } catch (JailRefused $jailRefused) {
            self::assertSame('A sentence ends after the moment the die goes to jail.', $jailRefused->getMessage());
        }

        self::assertSame([], $this->warden->history('member-7', 'd20:blue'));
        self::assertInstanceOf(Inmate::class, $this->warden->jail('member-7', 'd20:blue', until: new DateTimeImmutable('2026-11-13 20:00:01')));
    }

    public function testAKeeperHoldsAtMostAThousandDice(): void
    {
        for ($i = 1; $i < Warden::MAX_INMATES; ++$i) {
            $this->warden->jail('member-7', 'die-'.$i);
        }

        $this->warden->release('member-7', 'die-1');
        $this->warden->jail('member-7', 'die-1');
        $this->warden->jail('member-7', 'die-1000');
        self::assertCount(Warden::MAX_INMATES, $this->warden->inmates('member-7'));

        try {
            $this->warden->jail('member-7', 'die-1001');
            self::fail('No exception was thrown.');
        } catch (JailRefused $jailRefused) {
            self::assertSame('A keeper holds at most 1000 dice in jail at once.', $jailRefused->getMessage());
        }
    }

    public function testAnEventOutOfPlaceIsAConflict(): void
    {
        try {
            $this->store->append(new JailEvent(EventKind::Jailed, 'member-7', 'd6', 2, new DateTimeImmutable()));
            self::fail('No exception was thrown.');
        } catch (JailConflict $jailConflict) {
            self::assertSame('The record of the die d6 expects position 1; an event came at position 2.', $jailConflict->getMessage());
        }
    }

    public function testTheStoreIsEmptiedBetweenRequests(): void
    {
        $this->warden->jail('member-7', 'd6');
        $this->store->reset();

        self::assertSame([], $this->store->dice('member-7'));
        self::assertSame([], $this->warden->history('member-7', 'd6'));
    }

    public function testNumericDiceKeepTheirNameAsText(): void
    {
        $this->warden->jail('member-7', '20');

        self::assertSame(['20'], $this->store->dice('member-7'));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function refusedEntries(): iterable
    {
        $keeper = 'The keeper is a text of 1 to 128 bytes, in UTF-8, without control characters.';
        $die = 'The die is a text of 1 to 128 bytes, in UTF-8, without control characters.';
        $reason = 'A reason holds at most 500 characters, in UTF-8, without control characters but line breaks.';

        yield 'empty keeper' => ['', 'd6', '', $keeper];
        yield 'keeper too long' => [str_repeat('k', 129), 'd6', '', $keeper];
        yield 'keeper not UTF-8' => ["\xff", 'd6', '', $keeper];
        yield 'keeper with a control character' => ["member\n7", 'd6', '', $keeper];
        yield 'keeper as an email address' => ['ann@example.com', 'd6', '', 'The keeper is a stable identifier of the member, never an email address.'];
        yield 'empty die' => ['member-7', '', '', $die];
        yield 'die too long' => ['member-7', str_repeat('d', 129), '', $die];
        yield 'die not UTF-8' => ['member-7', "d\xc3", '', $die];
        yield 'die with a tab' => ['member-7', "d\t6", '', $die];
        yield 'reason too long' => ['member-7', 'd6', str_repeat('é', 501), $reason];
        yield 'reason not UTF-8' => ['member-7', 'd6', "\xe9", $reason];
        yield 'reason with a tab' => ['member-7', 'd6', "a\tb", $reason];
        yield 'reason with a null byte' => ['member-7', 'd6', "a\0b", $reason];
    }

    #[DataProvider('refusedEntries')]
    public function testItRefusesEntriesItCannotKeep(string $keeper, string $die, string $reason, string $message): void
    {
        try {
            $this->warden->jail($keeper, $die, $reason);
            self::fail('The jailing was taken.');
        } catch (InvalidJailEntry $invalidJailEntry) {
            self::assertSame($message, $invalidJailEntry->getMessage());
        }

        try {
            $this->warden->release($keeper, $die, $reason);
            self::fail('No exception was thrown.');
        } catch (InvalidJailEntry $invalidJailEntry) {
            self::assertSame($message, $invalidJailEntry->getMessage());
        }
    }

    public function testItTakesLettersWhoseEncodingContainsBytesOfControlCharacters(): void
    {
        // "Ā" is C4 80 in UTF-8: read byte by byte, 80 would look like a control character.
        $inmate = $this->warden->jail('keeper-Ā', 'dĀ6', 'Ā a été pipé');

        self::assertSame('dĀ6', $inmate->die());
        self::assertSame(EventKind::Released, $this->warden->release('keeper-Ā', 'dĀ6', 'ā')->kind);
    }

    public function testItTakesEntriesAtTheirLimits(): void
    {
        $keeper = str_repeat('k', 128);
        $die = str_repeat('é', 64);
        $inmate = $this->warden->jail($keeper, $die, str_repeat('é', 499)."\n");

        self::assertSame($die, $inmate->die());
        self::assertSame(EventKind::Released, $this->warden->release($keeper, $die, "One line.\nAnother.")->kind);
    }
}
