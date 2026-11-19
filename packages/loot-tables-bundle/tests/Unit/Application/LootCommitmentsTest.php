<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Application;

use Phil\CommerceBundle\Application\LineOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Exception\LootDrawRefused;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\CopyingRepository;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(LootCommitments::class)]
final class LootCommitmentsTest extends TestCase
{
    public function testEachUnitOfEachOptionDrawnHereIsCommitted(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 2, 'epitaph' => 1, 'mug' => 1, 'elsewhere' => 1]);
        $committed = $this->commitments($draws)->commit($order);
        $shirt = Shop::line($order, 'shirt')->reference->value();
        $epitaph = Shop::line($order, 'epitaph')->reference->value();

        self::assertSame([[$shirt, 'loot', 1], [$shirt, 'loot', 2], [$epitaph, 'epitaph', 1]], array_map(static fn (LootDraw $draw): array => [$draw->line(), $draw->option(), $draw->unit()], $committed));
        self::assertSame(['loot-box:starter', 'loot-box:starter', 'loot-text'], array_map(static fn (LootDraw $draw): string => $draw->drawer(), $committed));
        self::assertSame([null, null, Shop::EPITAPHS], array_map(static fn (LootDraw $draw): ?array => $draw->texts(), $committed));
        self::assertSame('2026-11-08 10:00:00', $committed[0]->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame($committed[0], $draws->get(LootDraw::idOf('1042', $shirt, 'loot', 1)));
        self::assertNull($committed[0]->revealedSeed());
        self::assertFalse($committed[0]->isRevealed());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $committed[0]->commitment());
        self::assertNotSame($committed[0]->commitment(), $committed[1]->commitment());
        self::assertCount(3, $draws->ofOrder('1042'));
    }

    public function testCommittingAgainKeepsTheFirstCommitments(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1]);
        $first = $this->commitments($draws)->commit($order);
        $again = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at('2026-11-09'))->commit($order);

        self::assertSame($first, $again);
        self::assertSame('2026-11-08', $again[0]->createdAt()->format('Y-m-d'));
    }

    public function testAChosenValueBeforeAValueToDrawOnTheSameLine(): void
    {
        $order = Shop::order(['combo' => 1]);

        self::assertSame(['loot'], array_map(static fn (LootDraw $draw): string => $draw->option(), $this->commitments(new InMemoryLootDrawRepository())->commit($order)));
    }

    public function testEachCommitmentSealsTheSecureSeedKeptForTheReveal(): void
    {
        [$draw] = $this->commitments(new CopyingRepository())->commit(Shop::order(['shirt' => 1]));

        self::assertSame(Seed::fromHex($draw->pending()->seed->hex())->commitment($draw->pending()->nonce)->hex, $draw->commitment());
        self::assertSame(0, $draw->pending()->nonce);
    }

    public function testOneUnitCommittedNow(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 3]);
        $line = Shop::line($order, 'shirt');
        $draw = $this->commitments($draws)->commitUnit($order, $line, 'loot', 3, 'loot-box:starter');

        self::assertSame([3, 'loot-box:starter'], [$draw->unit(), $draw->drawer()]);
        self::assertSame($draw, $draws->get(LootDraw::idOf('1042', $line->reference->value(), 'loot', 3)));
        self::assertCount(1, $draws->ofOrder('1042'));
    }

    public function testAValueAlreadyDrawnIsLeftAlone(): void
    {
        $order = Shop::order(['shirt' => 1]);
        $order->recordDraws(Shop::line($order, 'shirt')->reference, 'loot', ['already']);

        self::assertSame([], $this->commitments(new InMemoryLootDrawRepository())->commit($order));
    }

    public function testATextWhoseListADieCannotDraw(): void
    {
        $this->expectException(LootDrawRefused::class);
        $this->expectExceptionMessageIsOrContains('The option epitaph of line product.');
        $this->expectExceptionMessageIsOrContains('has no list to draw a text from: The table text has 1 entries; a table has 2 to 1000, one per side of its die.');

        $this->commitments(new InMemoryLootDrawRepository())->commit(Shop::order(['lonely' => 1]));
    }

    public function testATextOptionTheCatalogueNoLongerDeclares(): void
    {
        $order = Shop::order(['epitaph' => 1]);
        $commitments = new LootCommitments(Shop::drawers(), new LineOptions([]), new InMemoryLootDrawRepository(), new SecureSeeds(), Clock::at());

        $this->expectException(LootDrawRefused::class);
        $this->expectExceptionMessageIsOrContains('has no list to draw a text from: The table text has 0 entries');

        $commitments->commit($order);
    }

    private function commitments(LootDrawRepository $draws): LootCommitments
    {
        return new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());
    }
}
