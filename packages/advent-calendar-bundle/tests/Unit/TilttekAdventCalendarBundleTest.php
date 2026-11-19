<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendarBundle\Application\Calendars;
use Tilttek\AdventCalendarBundle\Application\Celebrations;
use Tilttek\AdventCalendarBundle\Application\ConfiguredCalendars;
use Tilttek\AdventCalendarBundle\Application\Contract\CalendarCatalogue;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberOpenings;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberResolver;
use Tilttek\AdventCalendarBundle\Application\NoMember;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\CheckCalendarsCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\ListCalendarsCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\ShowCalendarCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\DependencyInjection\AdventCalendarServices;
use Tilttek\AdventCalendarBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\DbalOpeningStore;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\DoctrineCalendarCatalogue;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\OpeningsSchemaListener;
use Tilttek\AdventCalendarBundle\Infrastructure\Http\CalendarController;
use Tilttek\AdventCalendarBundle\Infrastructure\Memory\InMemoryOpenings;
use Tilttek\AdventCalendarBundle\Infrastructure\Privacy\AdventCalendarPrivacy;
use Tilttek\AdventCalendarBundle\Infrastructure\Twig\KitComponents;
use Tilttek\AdventCalendarBundle\Tests\Unit\Fixture\Calendars as CalendarsFixture;
use Tilttek\AdventCalendarBundle\TilttekAdventCalendarBundle;

/**
 * The configuration tree and the services it registers, with every optional package installed, as in the monorepo.
 */
#[CoversClass(TilttekAdventCalendarBundle::class)]
#[CoversClass(AdventCalendarServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(KitComponents::class)]
final class TilttekAdventCalendarBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('tilttek_advent_calendar', $this->extension()->getAlias());
        self::assertSame(DbalOpeningStore::class, (string) $container->getAlias(OpeningStore::class));
        self::assertSame(DbalOpeningStore::class, (string) $container->getAlias(MemberOpenings::class));
        self::assertTrue($container->getAlias(OpeningStore::class)->isPublic());
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalOpeningStore::class)->getArguments());
        self::assertEquals(new Reference('doctrine.orm.default_entity_manager'), $container->getDefinition(DoctrineCalendarCatalogue::class)->getArgument(0));
        self::assertEquals(new Reference('doctrine.orm.default_entity_manager'), $container->getDefinition(CheckCalendarsCommand::class)->getArgument(0));
        self::assertSame([['command' => 'tilttek:advent-calendar:check', 'description' => CheckCalendarsCommand::DESCRIPTION]], $container->getDefinition(CheckCalendarsCommand::class)->getTag('console.command'));
        self::assertSame([['command' => 'tilttek:advent-calendar:list', 'description' => ListCalendarsCommand::DESCRIPTION]], $container->getDefinition(ListCalendarsCommand::class)->getTag('console.command'));
        self::assertSame([['command' => 'tilttek:advent-calendar:show', 'description' => ShowCalendarCommand::DESCRIPTION]], $container->getDefinition(ShowCalendarCommand::class)->getTag('console.command'));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(OpeningsSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals(new IteratorArgument([new Reference(ConfiguredCalendars::class), new Reference(DoctrineCalendarCatalogue::class)]), $container->getDefinition(Calendars::class)->getArgument(0));
        self::assertSame(Calendars::class, (string) $container->getAlias(CalendarCatalogue::class));
        self::assertSame([], $container->getDefinition(ConfiguredCalendars::class)->getArgument(0));
        self::assertSame(NoMember::class, (string) $container->getAlias(MemberResolver::class));
        self::assertSame([['method' => 'reset']], $container->getDefinition(Celebrations::class)->getTag('kernel.reset'));
        self::assertNull($container->getDefinition(KitComponents::class)->getArgument(0));

        $controller = $container->getDefinition(CalendarController::class);
        self::assertSame('base.html.twig', $controller->getArgument(8));
        self::assertFalse($controller->getArgument(9));
        self::assertSame('confetti', $controller->getArgument(10));
        self::assertSame('member', $container->getDefinition(AdventCalendarPrivacy::class)->getArgument(2));
    }

    public function testTheConfigurationIsTaken(): void
    {
        $container = $this->load([
            'store' => 'memory',
            'member_resolver' => 'app.members',
            'layout' => 'layout.html.twig',
            'kit_subdirectory' => 'kit/ui',
            'celebration' => ['enabled' => true, 'effect' => 'fireworks'],
            'calendars' => ['advent-2026' => CalendarsFixture::config('catch_up', 2)],
            'privacy' => ['member_kind' => 'player'],
        ]);

        self::assertSame(InMemoryOpenings::class, (string) $container->getAlias(OpeningStore::class));
        self::assertSame(InMemoryOpenings::class, (string) $container->getAlias(MemberOpenings::class));
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryOpenings::class)->getTag('kernel.reset'));
        self::assertFalse($container->hasDefinition(DbalOpeningStore::class));
        self::assertFalse($container->hasDefinition(DoctrineCalendarCatalogue::class));
        self::assertFalse($container->hasDefinition(CheckCalendarsCommand::class));
        self::assertFalse($container->hasDefinition(OpeningsSchemaListener::class));
        self::assertEquals(new IteratorArgument([new Reference(ConfiguredCalendars::class)]), $container->getDefinition(Calendars::class)->getArgument(0));
        self::assertSame(['advent-2026' => CalendarsFixture::config('catch_up', 2)], $container->getDefinition(ConfiguredCalendars::class)->getArgument(0));
        self::assertSame('app.members', (string) $container->getAlias(MemberResolver::class));
        self::assertFalse($container->hasDefinition(NoMember::class));
        self::assertSame('kit/ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $controller = $container->getDefinition(CalendarController::class);
        self::assertSame('layout.html.twig', $controller->getArgument(8));
        self::assertTrue($controller->getArgument(9));
        self::assertSame('fireworks', $controller->getArgument(10));
        self::assertSame('player', $container->getDefinition(AdventCalendarPrivacy::class)->getArgument(2));
    }

    public function testAnotherConnectionAndEntityManager(): void
    {
        $container = $this->load(['connection' => 'games', 'entity_manager' => 'games']);

        self::assertEquals([new Reference('doctrine.dbal.games_connection')], $container->getDefinition(DbalOpeningStore::class)->getArguments());
        self::assertEquals(new Reference('doctrine.orm.games_entity_manager'), $container->getDefinition(DoctrineCalendarCatalogue::class)->getArgument(0));
        self::assertEquals(new Reference('doctrine.orm.games_entity_manager'), $container->getDefinition(CheckCalendarsCommand::class)->getArgument(0));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'games']], $container->getDefinition(OpeningsSchemaListener::class)->getTag('doctrine.event_listener'));
    }

    public function testTheIdentifierOfACalendarKeepsItsDashes(): void
    {
        $container = $this->load(['calendars' => ['advent-2026' => CalendarsFixture::config(), 'winter.boxes' => CalendarsFixture::config()]]);

        self::assertSame(['advent-2026', 'winter.boxes'], array_keys((array) $container->getDefinition(ConfiguredCalendars::class)->getArgument(0)));
    }

    public function testACalendarThatDoesNotHoldTogetherStopsTheContainer(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"Mars/Olympus" is not a timezone.');

        $this->load(['calendars' => ['advent-2026' => ['timezone' => 'Mars/Olympus'] + CalendarsFixture::config()]]);
    }

    public function testACalendarWithoutASurpriseIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['calendars' => ['advent-2026' => ['surprises' => []] + CalendarsFixture::config()]]);
    }

    public function testAnUnknownEffectIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['celebration' => ['effect' => 'balloons']]);
    }

    public function testTheKitSubdirectoryIsTheToolkitsUnlessTheConfigurationSaysIt(): void
    {
        $container = $this->load([]);
        $container->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $bare = $this->load([]);
        new KitSubdirectoryPass()->process($bare);
        self::assertSame('', $bare->getDefinition(KitComponents::class)->getArgument(0));

        $named = $this->load(['kit_subdirectory' => 'mine']);
        $named->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, 'ui');
        new KitSubdirectoryPass()->process($named);
        self::assertSame('mine', $named->getDefinition(KitComponents::class)->getArgument(0));

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(KitComponents::class));

        $passes = new ContainerBuilder();
        new TilttekAdventCalendarBundle()->build($passes);
        self::assertCount(1, array_filter($passes->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
    }

    public function testTheComponentsOfTheKitAreNamedAfterTheirSubdirectory(): void
    {
        self::assertSame('Card:Title', new KitComponents()->name('Card:Title'));
        self::assertSame('ui:Card', new KitComponents('ui')->name('Card'));
        self::assertSame('kit:ui:Card', new KitComponents('/kit/ui/')->name('Card'));
        self::assertSame(['card', 'badge', 'button', 'alert'], KitComponents::RECIPES);
        self::assertSame('celebration', KitComponents::CELEBRATION);
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
        $extension = new TilttekAdventCalendarBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
