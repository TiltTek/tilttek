<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit;

use Phil\ContentWarnings\Catalogue;
use Phil\PagedMedia\Pdf\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\Authorization;
use Tilttek\TableSafety\Contract\FeedbackStore;
use Tilttek\TableSafety\Contract\ParticipationStore;
use Tilttek\TableSafety\Contract\SignalStore;
use Tilttek\TableSafety\Contract\TableStore;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\SessionZero\Templates;
use Tilttek\TableSafety\TableSafety;
use Tilttek\TableSafety\TableSettings;
use Tilttek\TableSafetyBundle\Application\NoTableAccess;
use Tilttek\TableSafetyBundle\Application\SignalTopics;
use Tilttek\TableSafetyBundle\Infrastructure\Console\PurgeSignalsCommand;
use Tilttek\TableSafetyBundle\Infrastructure\DependencyInjection\TableSafetyServices;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\TableSafetySchemaListener;
use Tilttek\TableSafetyBundle\Infrastructure\Form\SafetyToolsType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\SessionZeroType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\StarsAndWishesType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\TableSettingsType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\ThemeAnswersType;
use Tilttek\TableSafetyBundle\Infrastructure\Http\AnswersController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\CardsController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\GameMasterController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\Screens;
use Tilttek\TableSafetyBundle\Infrastructure\Http\TableController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\Views;
use Tilttek\TableSafetyBundle\Infrastructure\Mercure\SignalSubscriptions;
use Tilttek\TableSafetyBundle\Infrastructure\Print\SafetyCardsPdf;
use Tilttek\TableSafetyBundle\Infrastructure\Privacy\TableSafetyPrivacy;
use Tilttek\TableSafetyBundle\Infrastructure\Twig\KitComponents;
use Tilttek\TableSafetyBundle\Infrastructure\Twig\TableSafetyExtension;
use Tilttek\TableSafetyBundle\TilttekTableSafetyBundle;

/**
 * What each service of the bundle is given and how it is exposed: arguments, tags and visibility.
 */
#[CoversClass(TableSafetyServices::class)]
#[CoversClass(TilttekTableSafetyBundle::class)]
final class TableSafetyServicesTest extends TestCase
{
    public function testTheServicesWithoutAHub(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertEquals([$connection], $container->getDefinition(TableSafetySchemaListener::class)->getArguments());

        foreach ([TableStore::class, ParticipationStore::class, SignalStore::class, FeedbackStore::class] as $contract) {
            self::assertTrue($container->getAlias($contract)->isPublic(), $contract);
        }

        self::assertTrue($container->hasDefinition(NoTableAccess::class));
        self::assertTrue($container->getDefinition(TableSafety::class)->isPublic());
        self::assertEquals(new Reference(TableSettings::class.'.defaults'), $container->getDefinition(TableSafety::class)->getArgument(6));

        $template = $container->getDefinition(TableSettings::class.'.defaults')->getArgument(4);
        self::assertInstanceOf(Definition::class, $template);
        self::assertSame([Templates::class, 'get'], $template->getFactory());

        self::assertEquals([
            new Reference(SignalTopics::class),
            null,
            null,
            new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
        ], $container->getDefinition(SignalSubscriptions::class)->getArguments());

        $this->assertScreens($container);
    }

    public function testTheServicesWithAHub(): void
    {
        $container = $this->load([['store' => 'memory', 'mercure' => ['hub' => 'mercure.hub.default', 'hub_name' => 'default']]]);

        self::assertEquals([
            new Reference(SignalTopics::class),
            new Reference('mercure.hub.default'),
            class_exists(Authorization::class) ? new Reference(Authorization::class, ContainerInterface::NULL_ON_INVALID_REFERENCE) : null,
            new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            'default',
        ], $container->getDefinition(SignalSubscriptions::class)->getArguments());
        self::assertFalse($container->hasDefinition(TableSafetySchemaListener::class));

        $this->assertScreens($container);
    }

    public function testTheStoreIsOneOfTheTwoTheBundleKnows(): void
    {
        self::assertTrue($this->load([['store' => 'doctrine']])->hasDefinition(TableSafetySchemaListener::class));
        self::assertFalse($this->load([['store' => 'memory']])->hasDefinition(TableSafetySchemaListener::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->load([['store' => 'redis']]);
    }

    public function testAnUnknownToolIsRefusedWithTheWholeList(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('A tool is one of "'.implode('", "', array_map(static fn (SafetyTool $tool): string => $tool->value, SafetyTool::cases())).'".');

        $this->load([['defaults' => ['tools' => ['safe-word']]]]);
    }

    private function assertScreens(ContainerBuilder $container): void
    {
        foreach ([ThemeAnswersType::class, SessionZeroType::class, StarsAndWishesType::class, SafetyToolsType::class, TableSettingsType::class] as $type) {
            self::assertTrue($container->getDefinition($type)->hasTag('form.type'), $type);
        }

        $extension = $container->getDefinition(TableSafetyExtension::class);
        self::assertEquals([new Reference(KitComponents::class)], $extension->getArguments());
        self::assertTrue($extension->hasTag('twig.attribute_extension'));
        self::assertTrue($extension->hasTag('twig.runtime'));

        self::assertEquals([new Reference(TableSafety::class), new Reference('router')], $container->getDefinition(Views::class)->getArguments());

        $table = $container->getDefinition(TableController::class);
        self::assertEquals([new Reference(Screens::class), new Reference(TableSafety::class), new Reference('clock'), new Reference('router'), new Reference(Views::class), new Reference(SignalSubscriptions::class)], $table->getArguments());
        self::assertTrue($table->hasTag('controller.service_arguments'));
        self::assertTrue($table->isPublic());

        $answers = $container->getDefinition(AnswersController::class);
        self::assertEquals([new Reference(Screens::class), new Reference(TableSafety::class), new Reference('form.factory'), new Reference('clock'), new Reference('router')], $answers->getArguments());
        self::assertTrue($answers->hasTag('controller.service_arguments'));
        self::assertTrue($answers->isPublic());

        $master = $container->getDefinition(GameMasterController::class);
        self::assertEquals([new Reference(Screens::class), new Reference(TableSafety::class), new Reference('form.factory'), new Reference('router'), new Reference(Views::class), new Reference(SignalSubscriptions::class)], $master->getArguments());
        self::assertTrue($master->hasTag('controller.service_arguments'));
        self::assertTrue($master->isPublic());

        $cards = $container->getDefinition(CardsController::class);
        self::assertEquals([
            new Reference(Screens::class),
            new Reference(Views::class),
            new Reference('clock'),
            new Reference(SafetyCardsPdf::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $cards->getArguments());
        self::assertTrue($cards->hasTag('controller.service_arguments'));
        self::assertTrue($cards->isPublic());
        self::assertSame(class_exists(Document::class), $container->hasDefinition(SafetyCardsPdf::class));

        self::assertSame([['command' => 'tilttek:table-safety:purge-signals', 'description' => 'Deletes the signals older than the retention']], $container->getDefinition(PurgeSignalsCommand::class)->getTag('console.command'));
        self::assertTrue($container->getDefinition(TableSafetyPrivacy::class)->isPublic());
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(Catalogue::class, Catalogue::class);
        new TilttekTableSafetyBundle()->getContainerExtension()?->load($configs, $container);

        return $container;
    }
}
