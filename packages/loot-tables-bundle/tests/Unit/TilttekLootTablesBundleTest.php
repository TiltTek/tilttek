<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit;

use Phil\CommerceBundle\Application\LineOptions;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\LootCatalog;
use Tilttek\LootTablesBundle\Application\Drawers;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\LootOptionDrawer;
use Tilttek\LootTablesBundle\Application\LootPrint;
use Tilttek\LootTablesBundle\Application\LootResults;
use Tilttek\LootTablesBundle\Application\PhysicalDice;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Console\ReplayDrawCommand;
use Tilttek\LootTablesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\LootTablesBundle\Infrastructure\DependencyInjection\LootServices;
use Tilttek\LootTablesBundle\Infrastructure\Doctrine\DoctrineLootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Form\PhysicalDiceType;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Pdf\LootSheetPdf;
use Tilttek\LootTablesBundle\Infrastructure\Twig\Components\CommitmentsComponent;
use Tilttek\LootTablesBundle\Infrastructure\Twig\Components\PhysicalDiceComponent;
use Tilttek\LootTablesBundle\Infrastructure\Twig\KitComponents;
use Tilttek\LootTablesBundle\Infrastructure\Workflow\CommitOnPlace;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;
use Tilttek\LootTablesBundle\TilttekLootTablesBundle;

#[CoversClass(TilttekLootTablesBundle::class)]
#[CoversClass(LootServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class TilttekLootTablesBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('tilttek_loot_tables', $this->extension()->getAlias());
        self::assertSame(['service_container', LootCatalog::class, LootDrawer::class, SecureSeeds::class, Drawers::class, DoctrineLootDrawRepository::class, LootCommitments::class, LootOptionDrawer::class, PhysicalDice::class, LootResults::class, LootPrint::class, CommitOnPlace::class, PhysicalDiceType::class, PhysicalDiceComponent::class, KitComponents::class, CommitmentsComponent::class, ReplayDrawCommand::class, LootSheetPdf::class], array_keys($container->getDefinitions()));
        self::assertSame([LootServices::class, 'catalog'], $container->getDefinition(LootCatalog::class)->getFactory());
        self::assertSame([['tables' => [], 'boxes' => []]], $container->getDefinition(LootCatalog::class)->getArguments());
        self::assertTrue($container->getDefinition(LootCatalog::class)->isPublic());
        self::assertFalse($container->getDefinition(LootDrawer::class)->isPublic());
        self::assertSame([], $container->getDefinition(SecureSeeds::class)->getArguments());
        self::assertFalse($container->getDefinition(SecureSeeds::class)->isPublic());
        self::assertEquals([new Reference(LootCatalog::class)], $container->getDefinition(Drawers::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineLootDrawRepository::class)->getArguments());
        self::assertSame([DoctrineLootDrawRepository::class, true], [(string) $container->getAlias(LootDrawRepository::class), $container->getAlias(LootDrawRepository::class)->isPublic()]);
        self::assertEquals([new Reference(Drawers::class), new Reference(LineOptions::class), new Reference(LootDrawRepository::class), new Reference(SecureSeeds::class), new Reference('clock')], $container->getDefinition(LootCommitments::class)->getArguments());
        self::assertTrue($container->getDefinition(LootCommitments::class)->isPublic());
        self::assertEquals([new Reference(Drawers::class), new Reference(LootCommitments::class), new Reference(LootDrawRepository::class), new Reference(LootDrawer::class), new Reference('clock'), true], $container->getDefinition(LootOptionDrawer::class)->getArguments());
        self::assertSame(['phil_commerce.option_drawer' => [[]]], $container->getDefinition(LootOptionDrawer::class)->getTags());
        self::assertFalse($container->getDefinition(LootOptionDrawer::class)->isPublic());
        self::assertEquals([new Reference(Drawers::class), new Reference(LootDrawRepository::class), new Reference('clock')], $container->getDefinition(PhysicalDice::class)->getArguments());
        self::assertTrue($container->getDefinition(PhysicalDice::class)->isPublic());
        self::assertEquals([new Reference(Drawers::class), new Reference(LootDrawRepository::class), new Reference(LootDrawer::class)], $container->getDefinition(LootResults::class)->getArguments());
        self::assertTrue($container->getDefinition(LootResults::class)->isPublic());
        self::assertEquals([new Reference(LootResults::class)], $container->getDefinition(LootPrint::class)->getArguments());
        self::assertTrue($container->getDefinition(LootPrint::class)->isPublic());
        self::assertEquals([new Reference(LootCommitments::class)], $container->getDefinition(CommitOnPlace::class)->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => 'workflow.phil_commerce_order.completed.place']]], $container->getDefinition(CommitOnPlace::class)->getTags());
        self::assertFalse($container->getDefinition(CommitOnPlace::class)->isPublic());
        self::assertEquals([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(PhysicalDiceType::class)->getArguments());
        self::assertSame(['form.type' => [[]]], $container->getDefinition(PhysicalDiceType::class)->getTags());
        self::assertFalse($container->getDefinition(PhysicalDiceType::class)->isPublic());
        self::assertTrue($container->getDefinition(PhysicalDiceComponent::class)->isAutoconfigured());
        self::assertSame([], $container->getDefinition(PhysicalDiceComponent::class)->getArguments());
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertEquals([new Reference(OrderRepository::class), new Reference(LootResults::class), new Reference(KitComponents::class)], $container->getDefinition(CommitmentsComponent::class)->getArguments());
        self::assertTrue($container->getDefinition(CommitmentsComponent::class)->isAutoconfigured());
        self::assertFalse($container->getDefinition(CommitmentsComponent::class)->isPublic());
        self::assertEquals([new Reference(LootResults::class)], $container->getDefinition(ReplayDrawCommand::class)->getArguments());
        self::assertSame(['console.command' => [['command' => 'tilttek:loot-tables:replay', 'description' => 'Rolls a revealed loot draw again from its seed and checks it against its commitment']]], $container->getDefinition(ReplayDrawCommand::class)->getTags());
        self::assertEquals([new Reference(LootPrint::class)], $container->getDefinition(LootSheetPdf::class)->getArguments());
        self::assertTrue($container->getDefinition(LootSheetPdf::class)->isPublic());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['tables' => Shop::CATALOGUE['tables'], 'boxes' => Shop::CATALOGUE['boxes'], 'require_commitment' => false, 'commit_on_place' => false, 'store' => 'memory', 'kit_subdirectory' => 'ui']);
        $catalogue = $container->getDefinition(LootCatalog::class)->getArgument(0);

        self::assertIsArray($catalogue);
        /** @var array{tables: array<string, array{entries: list<array<string, mixed>>}>, boxes: array<string, mixed>} $catalogue */
        self::assertSame(['key' => 'bare', 'name' => 'Bare head', 'image' => null, 'slot' => null], $catalogue['tables']['hats']['entries'][5]);
        self::assertSame(['name' => 'Starter box', 'tables' => ['rings', 'hats']], $catalogue['boxes']['starter']);
        self::assertSame([6, 6], LootServices::catalog($catalogue)->box('starter')->dice());
        self::assertFalse($container->getDefinition(LootOptionDrawer::class)->getArgument(5));
        self::assertFalse($container->hasDefinition(CommitOnPlace::class));
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertSame([InMemoryLootDrawRepository::class, true], [(string) $container->getAlias(LootDrawRepository::class), $container->getAlias(LootDrawRepository::class)->isPublic()]);
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryLootDrawRepository::class)->getTags());
        self::assertFalse($container->hasDefinition(DoctrineLootDrawRepository::class));
    }

    public function testTheKitIsNamedAfterTheToolkitUnlessTheBundleSaysWhere(): void
    {
        $pass = new KitSubdirectoryPass();
        $empty = new ContainerBuilder();
        $pass->process($empty);
        self::assertFalse($empty->hasDefinition(KitComponents::class));

        $container = $this->load([]);
        $pass->process($container);
        self::assertSame([''], $container->getDefinition(KitComponents::class)->getArguments());

        $container = $this->load([]);
        $container->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, 'ui');

        $pass->process($container);
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());

        $container = $this->load([]);
        $container->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, ['ui']);

        $pass->process($container);
        self::assertSame([''], $container->getDefinition(KitComponents::class)->getArguments());

        $container = $this->load(['kit_subdirectory' => 'kit']);
        $container->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, 'ui');

        $pass->process($container);
        self::assertSame(['kit'], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertSame('.ux_toolkit.components.subdirectory', KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY);

        $builder = new ContainerBuilder();
        new TilttekLootTablesBundle()->build($builder);
        self::assertCount(1, array_filter($builder->getCompilerPassConfig()->getPasses(), static fn (object $one): bool => $one instanceof KitSubdirectoryPass));
    }

    public function testAnotherEntityManagerKeepsTheDraws(): void
    {
        self::assertEquals([new Reference('doctrine.orm.loot_entity_manager')], $this->load(['entity_manager' => 'loot', 'store' => 'doctrine'])->getDefinition(DoctrineLootDrawRepository::class)->getArguments());
    }

    public function testAWrongBoxFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidLoot::because('The box starter rolls the table hats, which the catalogue does not declare.'));

        $this->load(['tables' => ['rings' => Shop::CATALOGUE['tables']['rings']], 'boxes' => Shop::CATALOGUE['boxes']]);
    }

    public function testAConfigurationItRefuses(): void
    {
        foreach ([['store' => 'redis'], ['entity_manager' => ''], ['commit_on_place' => 'yes'], ['tables' => ['rings' => ['entries' => []]]], ['tables' => ['rings' => ['name' => 'Rings']]], ['tables' => ['rings' => ['name' => '', 'entries' => []]]], ['tables' => ['rings' => ['name' => 'Rings', 'entries' => [['name' => 'A']]]]], ['tables' => ['rings' => ['name' => 'Rings', 'entries' => [['key' => 'a']]]]], ['tables' => ['rings' => ['name' => 'Rings', 'entries' => [['key' => '', 'name' => 'A']]]]], ['tables' => ['rings' => ['name' => 'Rings', 'entries' => [['key' => 'a', 'name' => '']]]]], ['boxes' => ['box' => ['tables' => []]]], ['boxes' => ['box' => ['name' => 'Box']]], ['boxes' => ['box' => ['name' => '', 'tables' => []]]]] as $config) {
            try {
                $this->load($config);
                self::fail(\sprintf('%s was accepted.', json_encode($config, \JSON_THROW_ON_ERROR)));
            } catch (InvalidConfigurationException) {
            }
        }

        $processed = $this->processed(['tables' => ['my-rings' => Shop::CATALOGUE['tables']['rings']], 'boxes' => ['my-box' => ['name' => 'Box', 'tables' => ['my-rings']]]]);
        self::assertSame(['my-box' => ['name' => 'Box', 'tables' => ['my-rings']]], $processed['boxes']);
    }

    public function testTheDrawsAreMappedForDoctrine(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $builder = new ContainerBuilder();
        $extension->prepend($builder);
        self::assertSame([], $builder->getExtensionConfig('doctrine'));

        $doctrine = self::createStub(ExtensionInterface::class);
        $doctrine->method('getAlias')->willReturn('doctrine');
        $builder->registerExtension($doctrine);
        $extension->prepend($builder);

        self::assertSame([['orm' => ['mappings' => ['TilttekLootTablesBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Tilttek\LootTablesBundle\Domain\Model',
        ]]]]], $builder->getExtensionConfig('doctrine'));
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

    /**
     * @param array<string, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function processed(array $config): array
    {
        $catalogue = $this->load($config)->getDefinition(LootCatalog::class)->getArgument(0);
        self::assertIsArray($catalogue);

        return $catalogue;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekLootTablesBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
