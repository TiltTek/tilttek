<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit;

use Phil\ContentWarnings\BaseCatalogue;
use Phil\ContentWarnings\Catalogue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\TableSafety\Contract\SignalObserver;
use Tilttek\TableSafety\Contract\SignalStore;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Store\InMemorySignalStore;
use Tilttek\TableSafety\TableSafety;
use Tilttek\TableSafety\TableSettings;
use Tilttek\TableSafetyBundle\Application\Contract\TableAccess;
use Tilttek\TableSafetyBundle\Application\NoTableAccess;
use Tilttek\TableSafetyBundle\Application\SignalTopics;
use Tilttek\TableSafetyBundle\Infrastructure\Console\PurgeSignalsCommand;
use Tilttek\TableSafetyBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\TableSafetyBundle\Infrastructure\DependencyInjection\TableSafetyServices;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\DbalSignalStore;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\TableSafetySchemaListener;
use Tilttek\TableSafetyBundle\Infrastructure\Http\Screens;
use Tilttek\TableSafetyBundle\Infrastructure\Mercure\MercureSignalObserver;
use Tilttek\TableSafetyBundle\Infrastructure\Privacy\TableSafetyPrivacy;
use Tilttek\TableSafetyBundle\Infrastructure\Twig\KitComponents;
use Tilttek\TableSafetyBundle\TilttekTableSafetyBundle;

#[CoversClass(TilttekTableSafetyBundle::class)]
#[CoversClass(TableSafetyServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(KitComponents::class)]
final class TilttekTableSafetyBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('tilttek_table_safety', $this->extension()->getAlias());
        self::assertSame(DbalSignalStore::class, (string) $container->getAlias(SignalStore::class));
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalSignalStore::class)->getArguments());
        self::assertTrue($container->hasDefinition(TableSafetySchemaListener::class));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(TableSafetySchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame(NoTableAccess::class, (string) $container->getAlias(TableAccess::class));
        self::assertFalse($container->has(SignalObserver::class));
        self::assertFalse($container->hasDefinition(MercureSignalObserver::class));
        self::assertNull($container->getDefinition(TableSafety::class)->getArgument(5));
        self::assertSame('base.html.twig', $container->getDefinition(Screens::class)->getArgument(3));
        self::assertSame('30 days', $container->getDefinition(PurgeSignalsCommand::class)->getArgument(2));
        self::assertSame('member', $container->getDefinition(TableSafetyPrivacy::class)->getArgument(1));
        self::assertSame(BaseCatalogue::class, $container->getDefinition(Catalogue::class)->getFactory()[0] ?? null);

        $defaults = $container->getDefinition(TableSettings::class.'.defaults');
        self::assertSame(SafetyTool::cases(), $defaults->getArgument(0));
        self::assertFalse($defaults->getArgument(1));
        self::assertSame(Audience::Table, $defaults->getArgument(2));
        $template = $defaults->getArgument(4);
        self::assertInstanceOf(Definition::class, $template);
        self::assertSame(['campaign'], $template->getArguments());
        self::assertSame(3, $container->getDefinition(TableSafety::class)->getArgument(7));
    }

    public function testTheConfigurationIsTaken(): void
    {
        $container = new ContainerBuilder();
        $container->register(Catalogue::class, Catalogue::class);
        $this->extension()->load([[
            'store' => 'memory',
            'table_access' => 'app.tables',
            'layout' => 'layout.html.twig',
            'defaults' => ['tools' => ['x-card', 'open-door'], 'named_signals' => true, 'signals_seen_by' => 'game-master', 'template' => 'online'],
            'mercure' => ['hub' => 'mercure.hub.default', 'hub_name' => 'default', 'topic_prefix' => 'urn:x:'],
            'retention' => '2 weeks',
            'feedback_threshold' => 5,
            'kit_subdirectory' => 'kit',
            'privacy' => ['participant_kind' => 'player'],
        ]], $container);

        self::assertSame(InMemorySignalStore::class, (string) $container->getAlias(SignalStore::class));
        self::assertSame([], $container->getDefinition(InMemorySignalStore::class)->getArguments());
        self::assertFalse($container->hasDefinition(TableSafetySchemaListener::class));
        self::assertSame('app.tables', (string) $container->getAlias(TableAccess::class));
        self::assertSame(MercureSignalObserver::class, (string) $container->getAlias(SignalObserver::class));
        self::assertEquals(new Reference('mercure.hub.default'), $container->getDefinition(MercureSignalObserver::class)->getArgument(0));
        self::assertEquals(new Reference(SignalObserver::class), $container->getDefinition(TableSafety::class)->getArgument(5));
        self::assertSame('urn:x:', $container->getDefinition(SignalTopics::class)->getArgument(0));
        self::assertSame('layout.html.twig', $container->getDefinition(Screens::class)->getArgument(3));
        self::assertSame('2 weeks', $container->getDefinition(PurgeSignalsCommand::class)->getArgument(2));
        self::assertSame(5, $container->getDefinition(TableSafety::class)->getArgument(7));
        self::assertSame('kit', $container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertSame('player', $container->getDefinition(TableSafetyPrivacy::class)->getArgument(1));
        self::assertNull($container->getDefinition(Catalogue::class)->getFactory());

        $defaults = $container->getDefinition(TableSettings::class.'.defaults');
        self::assertSame([SafetyTool::XCard, SafetyTool::OpenDoor], $defaults->getArgument(0));
        self::assertTrue($defaults->getArgument(1));
        self::assertSame(Audience::GameMaster, $defaults->getArgument(2));
    }

    public function testAnUnknownToolIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('A tool is one of "lines-and-veils"');

        $this->load([['defaults' => ['tools' => ['x-card', 'safe-word']]]]);
    }

    public function testAnOddRetentionIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The retention is a number and a unit, such as "30 days".');

        $this->load([['retention' => 'forever']]);
    }

    public function testAFeedbackThresholdOfOneShowsEveryAnswer(): void
    {
        self::assertSame(1, $this->load([['feedback_threshold' => 1]])->getDefinition(TableSafety::class)->getArgument(7));
    }

    public function testAFeedbackThresholdUnderOneIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['feedback_threshold' => 0]]);
    }

    public function testTheKitIsNamedAfterTheToolkit(): void
    {
        $container = $this->load([]);
        $container->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, 'ui/kit');
        new KitSubdirectoryPass()->process($container);

        self::assertSame('ui/kit', $container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertSame('ui:kit:Card:Title', new KitComponents('/ui/kit/')->name('Card:Title'));
        self::assertSame('Card', new KitComponents()->name('Card'));

        $named = $this->load([['kit_subdirectory' => 'mine']]);
        $named->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, 'ui');
        new KitSubdirectoryPass()->process($named);
        self::assertSame('mine', $named->getDefinition(KitComponents::class)->getArgument(0));

        $none = $this->load([]);
        new KitSubdirectoryPass()->process($none);
        self::assertSame('', $none->getDefinition(KitComponents::class)->getArgument(0));

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(KitComponents::class));
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new TilttekTableSafetyBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekTableSafetyBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
