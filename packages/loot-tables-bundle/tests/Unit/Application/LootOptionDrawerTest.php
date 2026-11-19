<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\LootTables\Code\DrawText;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\LootOptionDrawer;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Exception\LootDrawRefused;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\CopyingRepository;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(LootOptionDrawer::class)]
final class LootOptionDrawerTest extends TestCase
{
    public function testItDrawsTheBoxesAndTheTextsDrawnHere(): void
    {
        $drawer = $this->drawer(new InMemoryLootDrawRepository());

        self::assertTrue($drawer->supports('loot-box:starter'));
        self::assertTrue($drawer->supports('loot-text'));
        self::assertFalse($drawer->supports('loot-box:unknown'));
        self::assertFalse($drawer->supports('mystery'));
    }

    public function testEachUnitIsRevealedFromItsCommitmentOnce(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 2]);
        $line = Shop::line($order, 'shirt');
        $this->commitments($draws)->commit($order);
        $drawer = $this->drawer($draws, Clock::at('2026-11-08 11:00:00'));

        $first = $drawer->draw('loot-box:starter', $order, $line, 'loot', 1);
        $draw = $draws->get(LootDraw::idOf('1042', $line->reference->value(), 'loot', 1));
        self::assertInstanceOf(LootDraw::class, $draw);
        $box = Shop::catalog()->box('starter');
        $result = $draw->result($box);
        self::assertNotNull($result);
        self::assertSame(DrawText::write($result), $first);
        self::assertSame(DrawSource::Verifiable, $result->source);
        self::assertSame('2026-11-08 11:00:00', $draw->revealedAt()?->format('Y-m-d H:i:s'));
        self::assertTrue(new LootDrawer()->verify($box, (array) $draw->proof()));
        self::assertSame($draw->commitment(), $draw->proof()['commitment'] ?? null);

        self::assertSame($first, $this->drawer($draws, Clock::at('2026-11-09'))->draw('loot-box:starter', $order, $line, 'loot', 1));
        self::assertSame('2026-11-08 11:00:00', $draw->revealedAt()->format('Y-m-d H:i:s'));
        self::assertMatchesRegularExpression('/^[1-6]-[1-6]: .+ ring, /', $drawer->draw('loot-box:starter', $order, $line, 'loot', 2));
    }

    public function testTheRevealIsSaved(): void
    {
        $draws = new CopyingRepository();
        $order = Shop::order(['shirt' => 1]);
        $line = Shop::line($order, 'shirt');
        $commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());

        $commitments->commit($order);

        $drawn = new LootOptionDrawer(Shop::drawers(), new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at()), $draws, new LootDrawer(), Clock::at())->draw('loot-box:starter', $order, $line, 'loot', 1);

        self::assertSame($drawn, $draws->get(LootDraw::idOf('1042', $line->reference->value(), 'loot', 1))?->drawn());
    }

    public function testATextIsDrawnAmongTheEntriesCommitted(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['epitaph' => 1]);
        $this->commitments($draws)->commit($order);

        self::assertContains($this->drawer($draws)->draw('loot-text', $order, Shop::line($order, 'epitaph'), 'epitaph', 1), Shop::EPITAPHS);
    }

    public function testAUnitNeverCommittedIsRefused(): void
    {
        $order = Shop::order(['shirt' => 1]);
        $line = Shop::line($order, 'shirt');

        $this->expectExceptionObject(LootDrawRefused::notCommitted('1042', $line->reference->value(), 'loot', 1));

        $this->drawer(new InMemoryLootDrawRepository())->draw('loot-box:starter', $order, $line, 'loot', 1);
    }

    public function testWithoutACommitmentRequiredAUnitIsCommittedAndRevealedAtOnce(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1]);
        $line = Shop::line($order, 'shirt');
        $drawn = new LootOptionDrawer(Shop::drawers(), $this->commitments($draws), $draws, new LootDrawer(), Clock::at(), false)->draw('loot-box:starter', $order, $line, 'loot', 1);
        $draw = $draws->get(LootDraw::idOf('1042', $line->reference->value(), 'loot', 1));

        self::assertInstanceOf(LootDraw::class, $draw);
        self::assertSame($draw->drawn(), $drawn);
        self::assertNotNull($draw->commitment());
        self::assertTrue($draw->isRevealed());
        self::assertSame($draw->id(), $draw->proof()['client_seed'] ?? null);
    }

    public function testTheSeedAloneRollsTheDrawWithItsIdentifierAsClientSeed(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1]);
        [$draw] = $this->commitments($draws)->commit($order);
        $drawn = $this->drawer($draws)->draw('loot-box:starter', $order, Shop::line($order, 'shirt'), 'loot', 1);

        self::assertSame($draw->id(), $draw->proof()['client_seed'] ?? null);
        self::assertSame($draw->revealedSeed(), $draw->proof()['seed'] ?? null);
        $again = new LootDrawer()->reveal(Shop::catalog()->box('starter'), $draw->pending(), $draw->id());
        self::assertSame(DrawText::write($again->result), $drawn);
    }

    private function commitments(InMemoryLootDrawRepository $draws): LootCommitments
    {
        return new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());
    }

    private function drawer(InMemoryLootDrawRepository $draws, ?MockClock $clock = null): LootOptionDrawer
    {
        return new LootOptionDrawer(Shop::drawers(), $this->commitments($draws), $draws, new LootDrawer(), $clock ?? Clock::at());
    }
}
