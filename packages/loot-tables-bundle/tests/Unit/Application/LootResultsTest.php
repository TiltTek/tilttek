<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tilttek\LootTables\Code\ShopCode;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\LootOptionDrawer;
use Tilttek\LootTablesBundle\Application\LootResults;
use Tilttek\LootTablesBundle\Application\PhysicalDice;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(LootResults::class)]
final class LootResultsTest extends TestCase
{
    public function testTheResultsOfAnOrder(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1, 'epitaph' => 1, 'giant' => 1]);
        $commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());

        $commitments->commit($order);

        $results = new LootResults(Shop::drawers(), $draws, new LootDrawer());
        $shirt = $draws->get(LootDraw::idOf('1042', Shop::line($order, 'shirt')->reference->value(), 'loot', 1));
        self::assertInstanceOf(LootDraw::class, $shirt);

        self::assertCount(3, $results->ofOrder('1042'));
        self::assertSame([], $results->ofOrder('9999'));
        self::assertNull($results->result($shirt));
        self::assertNull($results->shopCode($shirt));
        self::assertFalse($results->verify($shirt));

        $drawer = new LootOptionDrawer(Shop::drawers(), $commitments, $draws, new LootDrawer(), Clock::at());

        foreach (['shirt' => 'loot', 'epitaph' => 'epitaph', 'giant' => 'loot'] as $sellable => $option) {
            $line = Shop::line($order, $sellable);
            $drawer->draw((string) $line->configuration->value($option)?->drawer, $order, $line, $option, 1);
        }

        $revealed = $draws->get($shirt->id());
        self::assertInstanceOf(LootDraw::class, $revealed);
        $result = $results->result($revealed);
        self::assertInstanceOf(LootResult::class, $result);
        self::assertSame(ShopCode::write($result), $results->shopCode($revealed));
        self::assertSame(['rings', 'hats'], [$result->picks[0]->table->key, $result->picks[1]->table->key]);
        self::assertEquals(Shop::catalog()->box('starter')->tables[0]->entries[$result->faces()[0] - 1], $result->entries()[0]);
        self::assertTrue($results->verify($revealed));

        foreach ($results->ofOrder('1042') as $draw) {
            self::assertTrue($results->verify($draw));
            $drawn = $results->result($draw);
            self::assertInstanceOf(LootResult::class, $drawn);

            if ('loot-text' === $draw->drawer()) {
                self::assertContains($drawn->entries()[0]->name, Shop::EPITAPHS);
                self::assertSame((string) $drawn->faces()[0], $results->shopCode($draw));
            }

            if ('loot-box:giant' === $draw->drawer()) {
                self::assertNull($results->shopCode($draw));
            }
        }
    }

    public function testPhysicalDiceAreNotVerifiable(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1]);
        $reference = Shop::line($order, 'shirt')->reference;
        new PhysicalDice(Shop::drawers(), $draws, Clock::at())->enter($order, $reference, 'loot', ['42']);
        $draw = $draws->ofOrder('1042')[0];
        $results = new LootResults(Shop::drawers(), $draws, new LootDrawer());

        self::assertSame('42', $results->shopCode($draw));
        self::assertFalse($results->verify($draw));
    }

    public function testAProofWhoseCommitmentIsNotTheOneShown(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1]);
        $line = Shop::line($order, 'shirt');
        $commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());
        [$draw] = $commitments->commit($order);
        new LootOptionDrawer(Shop::drawers(), $commitments, $draws, new LootDrawer(), Clock::at())->draw('loot-box:starter', $order, $line, 'loot', 1);
        $results = new LootResults(Shop::drawers(), $draws, new LootDrawer());

        // An honest proof of another seed: it verifies on its own, but not against the commitment shown.
        $other = new LootDrawer()->reveal(Shop::catalog()->box('starter'), PendingDraw::restore(str_repeat('ab', 32)), 'buyer')->proof();
        self::assertTrue(new LootDrawer()->verify(Shop::catalog()->box('starter'), $other));
        new ReflectionProperty(LootDraw::class, 'proof')->setValue($draw, $other);
        self::assertFalse($results->verify($draw));

        new ReflectionProperty(LootDraw::class, 'proof')->setValue($draw, ['seed' => 'none']);
        self::assertFalse($results->verify($draw));
    }

    public function testARevealedDrawIsRolledAgainFromTheSeedItPublished(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1, 'epitaph' => 1]);
        $commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());
        [$shirt, $epitaph] = $commitments->commit($order);
        $results = new LootResults(Shop::drawers(), $draws, new LootDrawer());

        self::assertSame($shirt, $results->draw($shirt->id()));
        self::assertNull($results->draw('0123456789abcdef0123456789abcdef'));
        self::assertNull($results->replay($shirt));

        $drawer = new LootOptionDrawer(Shop::drawers(), $commitments, $draws, new LootDrawer(), Clock::at());
        $drawer->draw('loot-box:starter', $order, Shop::line($order, 'shirt'), 'loot', 1);
        $drawer->draw('loot-text', $order, Shop::line($order, 'epitaph'), 'epitaph', 1);

        foreach ([$shirt, $epitaph] as $draw) {
            $replayed = $results->replay($draw);
            self::assertInstanceOf(LootResult::class, $replayed);
            $kept = $results->result($draw);
            self::assertInstanceOf(LootResult::class, $kept);
            self::assertSame($kept->faces(), $replayed->faces());
            self::assertEquals($kept->entries(), $replayed->entries());
        }
    }

    public function testPhysicalDiceHaveNothingToRollAgain(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1]);
        new PhysicalDice(Shop::drawers(), $draws, Clock::at())->enter($order, Shop::line($order, 'shirt')->reference, 'loot', ['42']);

        self::assertNull(new LootResults(Shop::drawers(), $draws, new LootDrawer())->replay($draws->ofOrder('1042')[0]));
    }
}
