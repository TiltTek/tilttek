<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Application;

use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Exception\InvalidCode;
use Tilttek\LootTables\LootTable;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\PhysicalDice;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Exception\LootDrawRefused;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(PhysicalDice::class)]
final class PhysicalDiceTest extends TestCase
{
    public function testTheDiceOfEachUnitAreRecordedOnTheLine(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 2]);
        $reference = Shop::line($order, 'shirt')->reference;
        new PhysicalDice(Shop::drawers(), $draws, Clock::at('2026-11-08 15:00:00'))->enter($order, $reference, 'loot', ['36', '11']);

        self::assertSame(['3-6: Gold ring, Bare head', '1-1: Copper ring, Helmet'], $order->line($reference)?->configuration->value('loot')?->draws);
        $box = Shop::catalog()->box('starter');
        $first = $draws->get(LootDraw::idOf('1042', $reference->value(), 'loot', 1));
        $second = $draws->get(LootDraw::idOf('1042', $reference->value(), 'loot', 2));
        self::assertSame([3, 6], $first?->result($box)?->faces());
        self::assertSame(DrawSource::Physical, $first->result($box)->source);
        self::assertSame('3-6: Gold ring, Bare head', $first->drawn());
        self::assertSame('loot-box:starter', $first->drawer());
        self::assertSame('2026-11-08 15:00:00', $first->revealedAt()?->format('Y-m-d H:i:s'));
        self::assertSame([1, 1], $second?->result($box)?->faces());
    }

    public function testDiceTakeThePlaceOfACommitmentNotRevealed(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1]);
        $reference = Shop::line($order, 'shirt')->reference;
        [$committed] = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at())->commit($order);
        new PhysicalDice(Shop::drawers(), $draws, Clock::at())->enter($order, $reference, 'loot', ['25']);

        self::assertSame($committed, $draws->get($committed->id()));
        self::assertSame([2, 5], $committed->result(Shop::catalog()->box('starter'))?->faces());
        self::assertNotNull($committed->commitment());
        self::assertSame(DrawSource::Physical, $committed->result(Shop::catalog()->box('starter'))->source);
    }

    public function testWhatTheFormOfTheDiceAsksFor(): void
    {
        $order = Shop::order(['shirt' => 3]);
        $toEnter = new PhysicalDice(Shop::drawers(), new InMemoryLootDrawRepository(), Clock::at())->toEnter($order, Shop::line($order, 'shirt')->reference, 'loot');

        self::assertSame(3, $toEnter['units']);
        self::assertSame('starter', $toEnter['box']->key);
        self::assertSame(['rings', 'hats'], array_map(static fn (LootTable $table): string => $table->key, $toEnter['box']->tables));
    }

    public function testNothingIsAskedForALineTheOrderDoesNotHave(): void
    {
        $this->expectExceptionObject(LootDrawRefused::unknownLine('1042', 'product:hat'));

        new PhysicalDice(Shop::drawers(), new InMemoryLootDrawRepository(), Clock::at())->toEnter(Shop::order(['shirt' => 1]), SellableReference::of('product', 'hat'), 'loot');
    }

    #[DataProvider('notToDraw')]
    public function testNothingIsAskedForAnOptionThatIsNotABoxLeftToDraw(string $sellable, string $option): void
    {
        $order = Shop::order([$sellable => 1]);
        $reference = Shop::line($order, $sellable)->reference;

        $this->expectExceptionObject(LootDrawRefused::notToDraw($reference->value(), $option));

        new PhysicalDice(Shop::drawers(), new InMemoryLootDrawRepository(), Clock::at())->toEnter($order, $reference, $option);
    }

    public function testAnUnknownLine(): void
    {
        $this->expectExceptionObject(LootDrawRefused::unknownLine('1042', 'product:hat'));

        new PhysicalDice(Shop::drawers(), new InMemoryLootDrawRepository(), Clock::at())->enter(Shop::order(['shirt' => 1]), SellableReference::of('product', 'hat'), 'loot', ['11']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notToDraw(): iterable
    {
        yield 'an option the line does not have' => ['shirt', 'hat'];
        yield 'a chosen text' => ['mug', 'caption'];
        yield 'a text drawn from a list' => ['epitaph', 'epitaph'];
        yield 'a box drawn elsewhere' => ['elsewhere', 'loot'];
    }

    #[DataProvider('notToDraw')]
    public function testAnOptionThatIsNotABoxLeftToDraw(string $sellable, string $option): void
    {
        $order = Shop::order([$sellable => 1]);
        $reference = Shop::line($order, $sellable)->reference;

        $this->expectExceptionObject(LootDrawRefused::notToDraw($reference->value(), $option));

        new PhysicalDice(Shop::drawers(), new InMemoryLootDrawRepository(), Clock::at())->enter($order, $reference, $option, ['11']);
    }

    public function testABoxDrawnAlready(): void
    {
        $order = Shop::order(['shirt' => 1]);
        $reference = Shop::line($order, 'shirt')->reference;
        $dice = new PhysicalDice(Shop::drawers(), new InMemoryLootDrawRepository(), Clock::at());
        $dice->enter($order, $reference, 'loot', ['11']);

        $this->expectExceptionObject(LootDrawRefused::notToDraw($reference->value(), 'loot'));

        $dice->enter($order, $reference, 'loot', ['22']);
    }

    public function testOneCodePerUnit(): void
    {
        $order = Shop::order(['shirt' => 2]);
        $reference = Shop::line($order, 'shirt')->reference;

        $this->expectExceptionObject(LootDrawRefused::unitCount($reference->value(), 2, 1));

        new PhysicalDice(Shop::drawers(), new InMemoryLootDrawRepository(), Clock::at())->enter($order, $reference, 'loot', ['11']);
    }

    public function testACodeThatDoesNotReadRecordsNothing(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 2]);
        $reference = Shop::line($order, 'shirt')->reference;

        try {
            new PhysicalDice(Shop::drawers(), $draws, Clock::at())->enter($order, $reference, 'loot', ['11', '17']);
            self::fail('A face 7 was entered on a d6.');
        } catch (InvalidCode $invalidCode) {
            self::assertSame('Character 2 of the code is not a face of a d6: 123456.', $invalidCode->getMessage());
        }

        self::assertSame([], $draws->ofOrder('1042'));
        self::assertTrue($order->line($reference)?->configuration->value('loot')?->isPending());
    }
}
