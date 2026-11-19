<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\LootOptionDrawer;
use Tilttek\LootTablesBundle\Application\LootPrint;
use Tilttek\LootTablesBundle\Application\LootResults;
use Tilttek\LootTablesBundle\Application\PhysicalDice;
use Tilttek\LootTablesBundle\Application\PrintedDraw;
use Tilttek\LootTablesBundle\Application\PrintState;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(LootPrint::class)]
#[CoversClass(PrintedDraw::class)]
final class LootPrintTest extends TestCase
{
    public function testWhatIsPrintedOfEachDrawOfAnOrder(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 2, 'epitaph' => 1, 'giant' => 1]);
        $commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());
        $commitments->commit($order);

        $drawer = new LootOptionDrawer(Shop::drawers(), $commitments, $draws, new LootDrawer(), Clock::at());
        $shirt = Shop::line($order, 'shirt');
        $drawer->draw('loot-box:starter', $order, $shirt, 'loot', 1);
        $drawer->draw('loot-text', $order, Shop::line($order, 'epitaph'), 'epitaph', 1);
        $drawer->draw('loot-box:giant', $order, Shop::line($order, 'giant'), 'loot', 1);

        $results = new LootResults(Shop::drawers(), $draws, new LootDrawer());

        $printed = new LootPrint($results)->compose('1042');

        self::assertSame(array_map(static fn (LootDraw $draw): string => $draw->id(), $results->ofOrder('1042')), array_map(static fn (PrintedDraw $one): string => $one->draw->id(), $printed));
        $by = [];

        foreach ($printed as $one) {
            $by[$one->draw->drawer().' '.$one->draw->unit()] = $one;
        }

        ['loot-text 1' => $epitaph, 'loot-box:giant 1' => $giant, 'loot-box:starter 1' => $first, 'loot-box:starter 2' => $second] = $by;
        self::assertSame([PrintState::Verified, PrintState::Verified, PrintState::Verified, PrintState::Pending], [$epitaph->state, $giant->state, $first->state, $second->state]);

        $result = $results->result($first->draw);
        self::assertNotNull($result);
        self::assertSame($result->faces(), $first->result?->faces());
        self::assertSame($results->shopCode($first->draw), $first->shopCode);
        self::assertEquals($result->picks, $first->picks());
        self::assertSame($first->result->picks, $first->picks());
        self::assertNull($first->text());
        $rings = ['/loot/copper.png', '/loot/silver.png', '/loot/gold.png', null, null, null];
        $hats = ['/loot/helmet.png', '/loot/hood.png', '/loot/crown.png', '/loot/cap.png', '/loot/pot.png', null];
        self::assertSame(array_values(array_filter([$rings[$result->faces()[0] - 1], $hats[$result->faces()[1] - 1]])), $first->images());

        self::assertContains($epitaph->text(), Shop::EPITAPHS);
        self::assertSame([], $epitaph->picks());
        self::assertSame([], $epitaph->images());
        self::assertSame((string) $epitaph->result?->faces()[0], $epitaph->shopCode);

        self::assertNull($giant->shopCode);
        self::assertCount(1, $giant->picks());

        self::assertNull($second->result);
        self::assertNull($second->shopCode);
        self::assertNull($second->text());
        self::assertSame([], $second->picks());
        self::assertSame([], $second->images());
        self::assertSame([], new LootPrint($results)->compose('9999'));
    }

    public function testPhysicalDiceAndADrawThatDoesNotCheckOut(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1, 'giant' => 1]);
        new PhysicalDice(Shop::drawers(), $draws, Clock::at())->enter($order, Shop::line($order, 'shirt')->reference, 'loot', ['12']);
        $commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());
        $giant = Shop::line($order, 'giant');
        $draw = $commitments->commitUnit($order, $giant, 'loot', 1, 'loot-box:giant');
        new LootOptionDrawer(Shop::drawers(), $commitments, $draws, new LootDrawer(), Clock::at())->draw('loot-box:giant', $order, $giant, 'loot', 1);
        new ReflectionProperty($draw::class, 'proof')->setValue($draws->get($draw->id()), ['seed' => 'none']);

        $printed = new LootPrint(new LootResults(Shop::drawers(), $draws, new LootDrawer()))->compose('1042');
        $states = array_map(static fn (PrintedDraw $one): string => $one->state->value, $printed);
        sort($states);

        self::assertSame(['not-verified', 'physical'], $states);

        foreach ($printed as $one) {
            if (PrintState::Physical === $one->state) {
                self::assertSame(['/loot/copper.png', '/loot/hood.png'], $one->images());
                self::assertSame('12', $one->shopCode);
            }
        }
    }

    public function testTheStates(): void
    {
        self::assertSame(['verified', 'not-verified', 'physical', 'pending'], array_map(static fn (PrintState $state): string => $state->value, PrintState::cases()));
    }
}
