<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\PairingReason;
use Tilttek\Tournament\ReasonKind;
use Tilttek\Tournament\Round;

#[CoversClass(Pairing::class)]
#[CoversClass(PairingReason::class)]
#[CoversClass(Round::class)]
final class RoundTest extends TestCase
{
    public function testAPairingKnowsItsParticipants(): void
    {
        $pairing = new Pairing(3, 'alice', 'bob', [new PairingReason(ReasonKind::Floated, 'alice', 'bob'), new PairingReason(ReasonKind::Floated, 'bob', 'alice', 2)], 'A');

        self::assertFalse($pairing->isBye());
        self::assertTrue($pairing->involves('alice'));
        self::assertTrue($pairing->involves('bob'));
        self::assertFalse($pairing->involves('carol'));
        self::assertSame('bob', $pairing->opponentOf('alice'));
        self::assertSame('alice', $pairing->opponentOf('bob'));
        self::assertNull($pairing->opponentOf('carol'));
        self::assertSame('bob', $pairing->reasonsOf('alice')[0]->other);
        self::assertSame([], $pairing->reasonsOf('carol'));
        self::assertSame(2, $pairing->reasonsOf('bob')[0]->round);
        self::assertSame('A', $pairing->pool);
        self::assertFalse($pairing->rest);
    }

    public function testAPairingMovesToAnotherTableWithAllItsParts(): void
    {
        $pairing = new Pairing(1, 'alice', null, [new PairingReason(ReasonKind::Rest, 'alice')], 'B', true);
        $moved = $pairing->at(4);

        self::assertSame(4, $moved->table);
        self::assertSame('alice', $moved->first);
        self::assertNull($moved->second);
        self::assertSame($pairing->reasons, $moved->reasons);
        self::assertSame('B', $moved->pool);
        self::assertTrue($moved->rest);
        self::assertTrue($moved->isBye());
        self::assertFalse($moved->involves(''));
    }

    public function testATableIsNumberedFromOne(): void
    {
        self::assertSame(1, new Pairing(1, 'alice')->table);
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('A table is numbered from 1.');
        new Pairing(0, 'alice', 'bob');
    }

    public function testAParticipantNeverMeetsThemself(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('A participant never meets themself.');
        new Pairing(1, 'alice', 'alice');
    }

    public function testARoundFindsItsTablesAndKeys(): void
    {
        $round = new Round('swiss', 2, [new Pairing(1, 'alice', 'bob'), new Pairing(2, 'carol')]);

        self::assertSame('swiss#2#1', $round->key(1));
        self::assertSame('bob', $round->pairing(1)?->second);
        self::assertSame('carol', $round->pairing(2)?->first);
        self::assertNull($round->pairing(3));
        self::assertSame(1, $round->pairingOf('bob')?->table);
        self::assertSame(2, $round->pairingOf('carol')?->table);
        self::assertNull($round->pairingOf('dave'));
        self::assertNull($round->startsAt);
    }

    public function testARoundKeepsItsNumberWhenItsDateMoves(): void
    {
        $round = new Round('swiss', 1, [new Pairing(1, 'alice', 'bob')], new DateTimeImmutable('2026-12-05 10:00'));
        $moved = $round->at(new DateTimeImmutable('2026-12-06 13:00'));

        self::assertSame(1, $moved->number);
        self::assertSame('swiss', $moved->stage);
        self::assertSame($round->pairings, $moved->pairings);
        self::assertSame('2026-12-06 13:00', $moved->startsAt?->format('Y-m-d H:i'));
        self::assertNull($round->at(null)->startsAt);
    }

    public function testARoundIsNumberedFromOne(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('A round is numbered from 1.');
        new Round('swiss', 0, []);
    }

    public function testATableNumberIsGivenOnce(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The table 1 of a round is given twice.');
        new Round('swiss', 1, [new Pairing(1, 'alice', 'bob'), new Pairing(1, 'carol', 'dave')]);
    }

    public function testAParticipantSitsAtOneTable(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The participant bob sits at two tables of a round.');
        new Round('swiss', 1, [new Pairing(1, 'alice', 'bob'), new Pairing(2, 'bob', 'carol')]);
    }

    public function testAFirstParticipantSitsAtOneTable(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The participant alice sits at two tables of a round.');
        new Round('swiss', 1, [new Pairing(1, 'alice', 'bob'), new Pairing(2, 'alice')]);
    }

    public function testTheStageOfARoundIsAnIdentifier(): void
    {
        $this->expectException(InvalidTournament::class);
        new Round('', 1, []);
    }
}
