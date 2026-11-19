<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\DiceBundle\Infrastructure\Twig\DiceExtension;
use Tilttek\DiceTower\Application\Contract\TableAccess;
use Tilttek\DiceTower\Application\Contract\TowerPublisher;
use Tilttek\DiceTower\Application\Contract\TowerRelay;
use Tilttek\DiceTower\Application\DiceTower;
use Tilttek\DiceTower\Application\Message\RelayDrop;
use Tilttek\DiceTower\Application\NoTableAccess;
use Tilttek\DiceTower\Application\RelayDropHandler;
use Tilttek\DiceTower\Application\TowerRelays;
use Tilttek\DiceTower\Application\TowerTopics;
use Tilttek\DiceTower\Domain\Contract\TowerDropRepository;
use Tilttek\DiceTower\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\DiceTower\Infrastructure\DependencyInjection\TowerServices;
use Tilttek\DiceTower\Infrastructure\Doctrine\DoctrineTowerDropRepository;
use Tilttek\DiceTower\Infrastructure\Http\TowerEntriesController;
use Tilttek\DiceTower\Infrastructure\Memory\InMemoryTowerDropRepository;
use Tilttek\DiceTower\Infrastructure\Mercure\MercureTowerPublisher;
use Tilttek\DiceTower\Infrastructure\Mercure\TowerSubscriptions;
use Tilttek\DiceTower\Infrastructure\Twig\DiceTowerExtension;
use Tilttek\DiceTower\Infrastructure\Twig\KitComponents;
use Tilttek\DiceTower\Infrastructure\Twig\TowerView;
use Tilttek\DiceTower\TilttekDiceTowerBundle;

#[CoversClass(TilttekDiceTowerBundle::class)]
#[CoversClass(TowerServices::class)]
final class TilttekDiceTowerBundleTest extends TestCase
{
    public function testTheServicesByDefault(): void
    {
        $container = $this->load([]);

        self::assertSame('tilttek_dice_tower', $this->extension()->getAlias());
        self::assertSame(
            ['service_container', DoctrineTowerDropRepository::class, NoTableAccess::class, TowerTopics::class, MercureTowerPublisher::class, TowerRelays::class, DiceTower::class, TowerSubscriptions::class, TowerEntriesController::class, KitComponents::class, TowerView::class, DiceTowerExtension::class],
            array_keys($container->getDefinitions()),
        );
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineTowerDropRepository::class)->getArguments());
        self::assertSame(DoctrineTowerDropRepository::class, (string) $container->getAlias(TowerDropRepository::class));
        self::assertTrue($container->getAlias(TowerDropRepository::class)->isPublic());
        self::assertSame(NoTableAccess::class, (string) $container->getAlias(TableAccess::class));
        self::assertSame(MercureTowerPublisher::class, (string) $container->getAlias(TowerPublisher::class));
        self::assertSame(['urn:tilttek:dice-tower:'], $container->getDefinition(TowerTopics::class)->getArguments());
        self::assertTrue($container->getDefinition(TowerTopics::class)->isPublic());
        self::assertEquals([new Reference(HubInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(TowerTopics::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(MercureTowerPublisher::class)->getArguments());
        self::assertEquals([new TaggedIteratorArgument(TowerServices::RELAY_TAG), null, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(TowerRelays::class)->getArguments());
        self::assertSame([TowerServices::RELAY_TAG => [[]]], $container->getAutoconfiguredInstanceof()[TowerRelay::class]->getTags());
        self::assertSame(20, $container->getDefinition(DiceTower::class)->getArgument(6));
        self::assertEquals(new Reference('clock'), $container->getDefinition(DiceTower::class)->getArgument(3));
        self::assertTrue($container->getDefinition(DiceTower::class)->isPublic());
        self::assertEquals(
            [new Reference(TableAccess::class), new Reference(TowerTopics::class), new Reference(HubInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(Authorization::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE), null],
            $container->getDefinition(TowerSubscriptions::class)->getArguments(),
        );
        self::assertTrue($container->getDefinition(TowerSubscriptions::class)->isPublic());
        self::assertEquals([new Reference(DiceTower::class), new Reference(TableAccess::class)], $container->getDefinition(TowerEntriesController::class)->getArguments());
        self::assertEquals([new Reference(CustomDice::class), new Reference(DiceExtension::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(TowerView::class)->getArguments());
        self::assertTrue($container->getDefinition(TowerEntriesController::class)->isPublic());
        self::assertTrue($container->getDefinition(TowerEntriesController::class)->hasTag('controller.service_arguments'));
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(DiceTowerExtension::class)->getTags());
        self::assertTrue($container->getDefinition(DiceTowerExtension::class)->isPublic());
        self::assertEquals(new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(DiceTowerExtension::class)->getArgument(6));
    }

    public function testTheConfiguration(): void
    {
        $container = $this->load([
            'history' => 50, 'topic_prefix' => 'https://example.com/towers/', 'table_access' => 'app.tables', 'hub' => null, 'hub_name' => 'game',
            'message_bus' => 'messenger.bus.default', 'store' => 'memory', 'entity_manager' => 'dice', 'kit_subdirectory' => 'ui',
        ]);

        self::assertSame(InMemoryTowerDropRepository::class, (string) $container->getAlias(TowerDropRepository::class));
        self::assertTrue($container->getAlias(TowerDropRepository::class)->isPublic());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryTowerDropRepository::class)->getTags());
        self::assertSame('app.tables', (string) $container->getAlias(TableAccess::class));
        self::assertFalse($container->hasDefinition(NoTableAccess::class));
        self::assertSame(50, $container->getDefinition(DiceTower::class)->getArgument(6));
        self::assertSame(['https://example.com/towers/'], $container->getDefinition(TowerTopics::class)->getArguments());
        self::assertNull($container->getDefinition(MercureTowerPublisher::class)->getArgument(0));
        self::assertNull($container->getDefinition(TowerSubscriptions::class)->getArgument(2));
        self::assertSame('game', $container->getDefinition(TowerSubscriptions::class)->getArgument(5));
        self::assertEquals(new Reference('messenger.bus.default'), $container->getDefinition(TowerRelays::class)->getArgument(1));
        self::assertEquals([new Reference(DiceTower::class), new Reference(TowerRelays::class)], $container->getDefinition(RelayDropHandler::class)->getArguments());
        self::assertSame(['messenger.message_handler' => [['handles' => RelayDrop::class, 'bus' => 'messenger.bus.default']]], $container->getDefinition(RelayDropHandler::class)->getTags());
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());
    }

    public function testTheHistoryIsBounded(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['history' => 201]);
    }

    public function testTheHistoryHoldsOneEntryAtLeast(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['history' => 0]);
    }

    public function testTheLogIsKeptByDoctrineOrInMemory(): void
    {
        self::assertTrue($this->load(['store' => 'doctrine'])->hasDefinition(DoctrineTowerDropRepository::class));
        self::assertTrue($this->load(['store' => 'memory'])->hasDefinition(InMemoryTowerDropRepository::class));
    }

    public function testTheBoundsOfTheHistory(): void
    {
        self::assertSame(1, $this->load(['history' => 1])->getDefinition(DiceTower::class)->getArgument(6));
        self::assertSame(200, $this->load(['history' => 200])->getDefinition(DiceTower::class)->getArgument(6));
    }

    public function testTheLogIsMappedForDoctrineAndTheDiceBundleIsRequired(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->named('tilttek_dice'));
        $container->registerExtension($this->named('doctrine'));

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['TilttekDiceTower' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Tilttek\DiceTower\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));

        $alone = new ContainerBuilder();
        $alone->registerExtension($this->named('tilttek_dice'));

        $extension->prepend($alone);
        self::assertSame([], $alone->getExtensionConfig('doctrine'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('TilttekDiceTowerBundle reads the rolls of the journal of TilttekDiceBundle, from tilttek/dice-bundle: register it too.');
        $extension->prepend(new ContainerBuilder());
    }

    public function testTheKitPassIsAdded(): void
    {
        $container = new ContainerBuilder();
        new TilttekDiceTowerBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekDiceTowerBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    private function named(string $alias): ExtensionInterface
    {
        return new class($alias) extends Extension {
            public function __construct(
                private readonly string $alias,
            ) {
            }

            /**
             * @param array<array-key, mixed> $configs
             */
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
