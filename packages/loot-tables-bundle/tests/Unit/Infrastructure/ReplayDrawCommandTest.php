<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\LootOptionDrawer;
use Tilttek\LootTablesBundle\Application\LootResults;
use Tilttek\LootTablesBundle\Application\PhysicalDice;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Infrastructure\Console\ReplayDrawCommand;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(ReplayDrawCommand::class)]
final class ReplayDrawCommandTest extends TestCase
{
    public function testARevealedDrawIsRolledAgainFromItsSeed(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $draw = $this->revealed($draws);
        $result = new LootResults(Shop::drawers(), $draws, new LootDrawer())->result($draw);
        self::assertNotNull($result);
        $tester = $this->tester($draws);

        self::assertSame(Command::SUCCESS, $tester->execute(['draw' => $draw->id()]));
        $display = $this->display($tester);
        self::assertStringContainsString('Commitment '.$draw->commitment(), $display);
        self::assertStringContainsString('Seed '.$draw->revealedSeed(), $display);
        self::assertStringContainsString('Client seed '.$draw->id(), $display);

        foreach ($result->picks as $pick) {
            self::assertStringContainsString(\sprintf('* %s: %s (%d)', $pick->table->name, $pick->entry->name, $pick->face), $display);
        }

        self::assertStringContainsString('[OK] The dice rolled again from the seed are the ones kept, and the seed is the one committed to.', $display);
    }

    public function testADrawWhoseFacesWereChangedDoesNotCheckOut(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $draw = $this->revealed($draws);
        $faces = new LootResults(Shop::drawers(), $draws, new LootDrawer())->result($draw)?->faces() ?? [];
        new ReflectionProperty(LootDraw::class, 'result')->setValue($draw, ['source' => 'verifiable', 'faces' => [$faces[0] % 6 + 1, $faces[1]]]);

        self::assertSame(Command::FAILURE, $this->tester($draws)->execute(['draw' => $draw->id()]));
    }

    public function testADrawWhoseProofWasChangedDoesNotCheckOut(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $draw = $this->revealed($draws);
        $proof = (array) $draw->proof();
        $proof['commitment'] = str_repeat('0', 64);
        new ReflectionProperty(LootDraw::class, 'proof')->setValue($draw, $proof);
        $tester = $this->tester($draws);

        self::assertSame(Command::FAILURE, $tester->execute(['draw' => $draw->id()]));
        self::assertStringContainsString('[ERROR] The draw does not check out: the dice rolled again or the seed are not the ones committed to.', $this->display($tester));
    }

    public function testADrawThatIsNotThereOrHasNoSeedToRollAgain(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1, 'giant' => 1]);
        new PhysicalDice(Shop::drawers(), $draws, Clock::at())->enter($order, Shop::line($order, 'shirt')->reference, 'loot', ['12']);
        $committed = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at())->commitUnit($order, Shop::line($order, 'giant'), 'loot', 1, 'loot-box:giant');
        $physical = LootDraw::idOf('1042', Shop::line($order, 'shirt')->reference->value(), 'loot', 1);
        $tester = $this->tester($draws);

        self::assertSame(Command::FAILURE, $tester->execute(['draw' => 'abc']));
        self::assertStringContainsString('[ERROR] No draw has the identifier abc.', $this->display($tester));

        foreach ([$physical, $committed->id()] as $id) {
            self::assertSame(Command::FAILURE, $tester->execute(['draw' => $id]));
            self::assertStringContainsString('[ERROR] The draw '.$id.' has no seed to roll again: it is not revealed yet, or its dice were entered at a register.', $this->display($tester));
        }
    }

    private function revealed(InMemoryLootDrawRepository $draws): LootDraw
    {
        $order = Shop::order(['shirt' => 1]);
        $commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());
        [$draw] = $commitments->commit($order);
        new LootOptionDrawer(Shop::drawers(), $commitments, $draws, new LootDrawer(), Clock::at())->draw('loot-box:starter', $order, Shop::line($order, 'shirt'), 'loot', 1);

        return $draw;
    }

    private function tester(InMemoryLootDrawRepository $draws): CommandTester
    {
        $application = new Application();
        $application->addCommand(new ReplayDrawCommand(new LootResults(Shop::drawers(), $draws, new LootDrawer())));

        return new CommandTester($application->find(ReplayDrawCommand::NAME));
    }

    /**
     * The output on one line per paragraph, its padding and wrapping removed.
     */
    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay(true));
    }
}
