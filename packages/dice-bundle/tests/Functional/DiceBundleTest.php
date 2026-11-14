<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Tilttek\DiceBundle\Application\DiceMacros;
use Tilttek\DiceBundle\Application\DiceRolls;
use Tilttek\DiceBundle\Application\Event\RollRecorded;
use Tilttek\DiceBundle\Application\MacroShelves;
use Tilttek\DiceBundle\Domain\MacroLevel;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Form\DiceExpressionType;
use Tilttek\DiceBundle\Tests\Functional\App\HeardRolls;
use Tilttek\DiceBundle\Tests\Functional\App\TestKernel;
use Tilttek\DiceBundle\TilttekDiceBundle;
use Twig\Environment;

/**
 * The bundle in an application: rolls kept in Doctrine on SQLite and replayed, macros kept and resolved, the custom
 * dice and the notations of the configuration, the members' dice sets, the Twig filters and functions, and the form
 * field checked by its constraint.
 */
#[CoversClass(TilttekDiceBundle::class)]
final class DiceBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testAReplayableRollIsKeptInDoctrineAndReplayed(): void
    {
        $dice = $this->service(DiceRolls::class, DiceRolls::class);
        $member = ResourceReference::of('member', '1');
        $recorded = $dice->rollReplayable('4n6kh3+1d{coin}', $member, 'fi');
        $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class)->clear();

        $found = $this->service(RecordedRollRepository::class, RecordedRollRepository::class)->get($recorded->id());
        self::assertSame(['4d6kh3+1d{coin}', '2026-11-07 03:00:00'], [$found?->expression(), $found?->rolledAt()->format('Y-m-d H:i:s')]);
        self::assertTrue($dice->replay($recorded->id())->matches());
        self::assertSame('Moss', $dice->appearance($member, 20)?->name);
        self::assertSame(1, $dice->eraseOwner($member));
    }

    public function testARollMadeAtATableIsAnnouncedAndListed(): void
    {
        $dice = $this->service(DiceRolls::class, DiceRolls::class);
        $table = ResourceReference::of('table', '7');
        $recorded = $dice->rollReplayable('1d20', ResourceReference::of('member', '1'), context: $table);
        $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class)->clear();

        self::assertEquals([new RollRecorded($recorded->id(), ResourceReference::of('member', '1'), $table)], $this->service(HeardRolls::class, HeardRolls::class)->events);
        self::assertSame([$recorded->id()], array_map(static fn (RecordedRoll $roll): string => $roll->id(), $dice->rollsIn($table, 10)));
        self::assertSame([$recorded->id()], array_map(static fn (RecordedRoll $roll): string => $roll->id(), $dice->recorded([$recorded->id()])));
    }

    public function testMacrosAreKeptInDoctrineAndResolved(): void
    {
        $macros = $this->service(DiceMacros::class, DiceMacros::class);
        $shelves = new MacroShelves(ResourceReference::of('site', '1'), ResourceReference::of('table', '2'), ResourceReference::of('member', '1'));
        $macros->define($shelves, MacroLevel::Table, 'attack', '1d20+5');
        $macros->define($shelves, MacroLevel::Member, 'fire', '8d6');
        $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class)->clear();

        $dice = $this->service(DiceRolls::class, DiceRolls::class);
        self::assertSame('(1d20+5)*2, 8d6', $dice->write($macros->parse('$attack*2; $fire', $shelves)));
        self::assertSame('8d6+1d6sd', $dice->write($macros->readRolisteam('$fire+1d6s', $shelves)));
        self::assertSame(1, $macros->eraseOwner(ResourceReference::of('member', '1')));
    }

    public function testTheTemplatesWriteRollAndShowTheDice(): void
    {
        $html = $this->service('twig', Environment::class)->render('roll.html.twig', ['member' => ResourceReference::of('member', '1')]);

        self::assertStringStartsWith('2W6+1|/r 4d6ro1|none|dropped', $html);
        self::assertStringContainsString('<code class="tilttek-dice-notation">1d20</code>', $html);
        self::assertStringContainsString('data-dice-appearance="Moss" data-dice-material="glass" style="--tilttek-dice-color: #2e8b57; --tilttek-dice-ink: #000000"', $html);
    }

    public function testTheFormFieldIsCheckedByItsConstraint(): void
    {
        $factory = $this->service('test.form.factory', FormFactoryInterface::class);
        $valid = $factory->create(DiceExpressionType::class, null, ['locale' => 'fi']);
        $valid->submit('3n6!');

        $invalid = $factory->create(DiceExpressionType::class);
        $invalid->submit('3n6!');

        self::assertTrue($valid->isValid());
        self::assertFalse($invalid->isValid());
        self::assertSame('This is not a dice expression: Unexpected "n" at position 1 of "3n6!".', $invalid->getErrors()[0]->getMessage());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
