<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTablesBundle\Domain\Exception\LootDrawRefused;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(LootDraw::class)]
#[CoversClass(LootDrawRefused::class)]
final class LootDrawTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testTheIdentifierOfADrawIsThatOfItsUnit(): void
    {
        $id = LootDraw::idOf('1042', 'product:shirt', 'loot', 1);

        self::assertSame(substr(hash('sha256', '["1042","product:shirt","loot",1]'), 0, 32), $id);
        self::assertNotSame($id, LootDraw::idOf('1042', 'product:shirt', 'loot', 2));
        self::assertNotSame($id, LootDraw::idOf('1042', 'product:shirt', 'other', 1));
        self::assertNotSame($id, LootDraw::idOf('1042', 'product:mug', 'loot', 1));
        self::assertNotSame($id, LootDraw::idOf('1043', 'product:shirt', 'loot', 1));
    }

    public function testACommittedDrawKeepsItsSeedAndShowsItsCommitment(): void
    {
        $at = new DateTimeImmutable('2026-11-08 10:00:00');
        $id = LootDraw::idOf('1042', 'product:shirt', 'loot', 2);
        $draw = LootDraw::commit('1042', 'product:shirt', 'loot', 2, 'loot-box:starter', PendingDraw::restore(self::SEED), ['a', 'b'], $at);

        self::assertSame($id, $draw->id());
        self::assertSame('1042', $draw->orderNumber());
        self::assertSame('product:shirt', $draw->line());
        self::assertSame('loot', $draw->option());
        self::assertSame(2, $draw->unit());
        self::assertSame('loot-box:starter', $draw->drawer());
        self::assertSame(Seed::fromHex(self::SEED)->commitment($draw->pending()->nonce)->hex, $draw->commitment());
        self::assertSame(self::SEED, $draw->pending()->seed->hex());
        self::assertSame($id, $draw->clientSeedOfReveal());
        self::assertNull($draw->revealedSeed());
        self::assertSame(0, $draw->pending()->nonce);
        self::assertSame(['a', 'b'], $draw->texts());
        self::assertSame($at, $draw->createdAt());
        self::assertFalse($draw->isRevealed());
        self::assertNull($draw->revealedAt());
        self::assertNull($draw->drawn());
        self::assertNull($draw->proof());
        self::assertNull($draw->result(Shop::catalog()->box('starter')));
    }

    public function testTheRevealIsRecordedOnce(): void
    {
        $box = Shop::catalog()->box('starter');
        $id = LootDraw::idOf('1042', 'product:shirt', 'loot', 1);
        $draw = LootDraw::commit('1042', 'product:shirt', 'loot', 1, 'loot-box:starter', PendingDraw::restore(self::SEED), null, new DateTimeImmutable('2026-11-08 10:00:00'));
        $revealed = new LootDrawer()->reveal($box, $draw->pending(), $id);
        $at = new DateTimeImmutable('2026-11-08 11:00:00');
        $draw->reveal($revealed, 'drawn text', $at);

        self::assertTrue($draw->isRevealed());
        self::assertSame($at, $draw->revealedAt());
        self::assertSame('drawn text', $draw->drawn());
        self::assertSame($revealed->proof(), $draw->proof());
        self::assertSame(self::SEED, $draw->revealedSeed());
        self::assertSame($id, $draw->proof()['client_seed']);
        self::assertNull($draw->texts());
        $result = $draw->result($box);
        self::assertInstanceOf(LootResult::class, $result);
        self::assertSame($revealed->result->faces(), $result->faces());
        self::assertSame(DrawSource::Verifiable, $result->source);

        $this->expectExceptionObject(LootDrawRefused::alreadyRevealed($id));

        $draw->reveal($revealed, 'again', $at);
    }

    public function testARevealWithAnotherClientSeedIsRefused(): void
    {
        $box = Shop::catalog()->box('starter');
        $draw = LootDraw::commit('1042', 'product:shirt', 'loot', 1, 'loot-box:starter', PendingDraw::restore(self::SEED), null, new DateTimeImmutable());

        $this->expectExceptionObject(LootDrawRefused::notCommitted('1042', 'product:shirt', 'loot', 1));

        $draw->reveal(new LootDrawer()->reveal($box, $draw->pending(), 'words of a browser'), 'x', new DateTimeImmutable());
    }

    public function testARevealOfAnotherSeedIsRefused(): void
    {
        $box = Shop::catalog()->box('starter');
        $draw = LootDraw::commit('1042', 'product:shirt', 'loot', 1, 'loot-box:starter', PendingDraw::restore(self::SEED), null, new DateTimeImmutable());

        $this->expectExceptionObject(LootDrawRefused::notCommitted('1042', 'product:shirt', 'loot', 1));

        $draw->reveal(new LootDrawer()->reveal($box, PendingDraw::restore(str_repeat('ab', 32)), $draw->id()), 'x', new DateTimeImmutable());
    }

    public function testPhysicalDiceAreEnteredWithoutACommitment(): void
    {
        $box = Shop::catalog()->box('starter');
        $at = new DateTimeImmutable('2026-11-08 12:00:00');
        $draw = LootDraw::physical('1042', 'product:shirt', 'loot', 1, 'loot-box:starter', LootResult::ofFaces($box, [3, 6], DrawSource::Physical), '3-6: Gold ring, Bare head', $at);

        self::assertNull($draw->commitment());
        self::assertNull($draw->proof());
        self::assertNull($draw->revealedSeed());
        self::assertTrue($draw->isRevealed());
        self::assertSame($at, $draw->createdAt());
        self::assertSame($at, $draw->revealedAt());
        self::assertSame('3-6: Gold ring, Bare head', $draw->drawn());
        self::assertSame([3, 6], $draw->result($box)?->faces());
        self::assertSame(DrawSource::Physical, $draw->result($box)->source);

        $this->expectExceptionObject(LootDrawRefused::notCommitted('1042', 'product:shirt', 'loot', 1));

        $draw->pending();
    }

    public function testPhysicalDiceTakeThePlaceOfACommittedDrawNotRevealed(): void
    {
        $box = Shop::catalog()->box('starter');
        $draw = LootDraw::commit('1042', 'product:shirt', 'loot', 1, 'loot-box:starter', PendingDraw::restore(self::SEED), null, new DateTimeImmutable());
        $draw->enter(LootResult::ofFaces($box, [1, 2], DrawSource::Physical), '1-2: Copper ring, Hood', new DateTimeImmutable());

        self::assertSame(DrawSource::Physical, $draw->result($box)?->source);
        self::assertNull($draw->proof());
        self::assertNull($draw->revealedSeed());

        $this->expectException(LootDrawRefused::class);
        $this->expectExceptionMessageIsOrContains('was revealed already; a draw is made once.');

        $draw->enter(LootResult::ofFaces($box, [1, 2], DrawSource::Physical), 'again', new DateTimeImmutable());
    }

    public function testTheRefusals(): void
    {
        self::assertSame('The order 1042 has no line product:x.', LootDrawRefused::unknownLine('1042', 'product:x')->getMessage());
        self::assertSame('The option loot of line product:x is not a box left to draw.', LootDrawRefused::notToDraw('product:x', 'loot')->getMessage());
        self::assertSame('The line product:x sells 2 units; 3 codes were given, one per unit.', LootDrawRefused::unitCount('product:x', 2, 3)->getMessage());
        self::assertSame('The option epitaph of line product:x has no list to draw a text from: none.', LootDrawRefused::noEntries('product:x', 'epitaph', 'none.')->getMessage());
        self::assertSame('The draw of unit 2 of option loot on line product:x of order 1042 was never committed: commit the draws of an order when it is placed.', LootDrawRefused::notCommitted('1042', 'product:x', 'loot', 2)->getMessage());
        self::assertSame('The draw abc was revealed already; a draw is made once.', LootDrawRefused::alreadyRevealed('abc')->getMessage());
        self::assertSame('No draw has the identifier abc.', LootDrawRefused::unknownDraw('abc')->getMessage());
    }
}
