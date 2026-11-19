<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

/**
 * The contract of a store of draws, run against each.
 */
abstract class RepositoryTestCase extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testADrawIsReadBackAsItWasSaved(): void
    {
        $repository = $this->repository();
        $box = Shop::catalog()->box('starter');
        $committed = LootDraw::commit('1042', 'product:shirt', 'loot', 1, 'loot-box:starter', PendingDraw::restore(self::SEED), null, new DateTimeImmutable('2026-11-08 10:00:00'));

        $repository->save($committed);
        $text = LootDraw::commit('1042', 'product:epitaph', 'epitaph', 1, 'loot-text', PendingDraw::restore(self::SEED), Shop::EPITAPHS, new DateTimeImmutable('2026-11-08 10:00:00'));
        $repository->save($text);
        $this->clear();

        $read = $repository->get($committed->id());
        self::assertInstanceOf(LootDraw::class, $read);
        self::assertSame([$committed->id(), '1042', 'product:shirt', 'loot', 1, 'loot-box:starter', $committed->commitment(), null, false], [$read->id(), $read->orderNumber(), $read->line(), $read->option(), $read->unit(), $read->drawer(), $read->commitment(), $read->texts(), $read->isRevealed()]);
        self::assertSame(self::SEED, $read->pending()->seed->hex());
        self::assertNull($read->revealedSeed());
        self::assertSame('2026-11-08 10:00:00', $read->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame(Shop::EPITAPHS, $repository->get($text->id())?->texts());
        self::assertNull($repository->get('0123456789abcdef0123456789abcdef'));

        $revealed = new LootDrawer()->reveal($box, $read->pending(), $read->clientSeedOfReveal());
        $read->reveal($revealed, 'drawn', new DateTimeImmutable('2026-11-08 11:00:00'));
        $repository->save($read);
        $this->clear();

        $again = $repository->get($committed->id());
        self::assertSame('drawn', $again?->drawn());
        self::assertSame(self::SEED, $again->revealedSeed());
        self::assertSame($revealed->result->faces(), $again->result($box)?->faces());
        self::assertSame(DrawSource::Verifiable, $again->result($box)->source);
        self::assertTrue(new LootDrawer()->verify($box, (array) $again->proof()));
        self::assertSame('2026-11-08 11:00:00', $again->revealedAt()?->format('Y-m-d H:i:s'));
    }

    public function testTheDrawsOfAnOrderByLineOptionAndUnit(): void
    {
        $repository = $this->repository();
        $box = Shop::catalog()->box('starter');
        $at = new DateTimeImmutable('2026-11-08 10:00:00');

        foreach ([['b', 'loot', 2], ['a', 'z', 1], ['c', 'a', 1], ['b', 'loot', 10], ['b', 'loot', 1], ['a', 'm', 1], ['b', 'a', 3]] as [$line, $option, $unit]) {
            $repository->save(LootDraw::physical('1042', 'product:'.$line, $option, $unit, 'loot-box:starter', LootResult::ofFaces($box, [1, 1], DrawSource::Physical), 'x', $at));
        }

        $repository->save(LootDraw::physical('1043', 'product:a', 'loot', 1, 'loot-box:starter', LootResult::ofFaces($box, [1, 1], DrawSource::Physical), 'x', $at));
        $this->clear();

        self::assertSame(
            [['product:a', 'm', 1], ['product:a', 'z', 1], ['product:b', 'a', 3], ['product:b', 'loot', 1], ['product:b', 'loot', 2], ['product:b', 'loot', 10], ['product:c', 'a', 1]],
            array_map(static fn (LootDraw $draw): array => [$draw->line(), $draw->option(), $draw->unit()], $repository->ofOrder('1042')),
        );
        self::assertSame([], $repository->ofOrder('1044'));
    }

    abstract protected function repository(): LootDrawRepository;

    abstract protected function clear(): void;
}
