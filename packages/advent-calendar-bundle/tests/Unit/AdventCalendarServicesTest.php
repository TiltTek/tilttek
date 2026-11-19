<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit;

use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\AdventCalendar\AdventCalendar;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendar\Surprise;
use Tilttek\AdventCalendarBundle\Application\Calendars;
use Tilttek\AdventCalendarBundle\Application\Celebrations;
use Tilttek\AdventCalendarBundle\Application\Contract\CalendarCatalogue;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberOpenings;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberResolver;
use Tilttek\AdventCalendarBundle\Application\Members;
use Tilttek\AdventCalendarBundle\Application\NoMember;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\CheckCalendarsCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\ListCalendarsCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\ShowCalendarCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\DependencyInjection\AdventCalendarServices;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\DoctrineCalendarCatalogue;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\OpeningsSchemaListener;
use Tilttek\AdventCalendarBundle\Infrastructure\EventListener\CelebrationListener;
use Tilttek\AdventCalendarBundle\Infrastructure\Form\CalendarType;
use Tilttek\AdventCalendarBundle\Infrastructure\Form\SurpriseType;
use Tilttek\AdventCalendarBundle\Infrastructure\Http\CalendarController;
use Tilttek\AdventCalendarBundle\Infrastructure\Memory\InMemoryOpenings;
use Tilttek\AdventCalendarBundle\Infrastructure\Privacy\AdventCalendarPrivacy;
use Tilttek\AdventCalendarBundle\Infrastructure\Twig\AdventCalendarExtension;
use Tilttek\AdventCalendarBundle\Infrastructure\Twig\KitComponents;
use Tilttek\AdventCalendarBundle\TilttekAdventCalendarBundle;

/**
 * The arguments, tags and visibility of each service the bundle registers, and the defaults and limits of its tree.
 */
#[CoversClass(TilttekAdventCalendarBundle::class)]
#[CoversClass(AdventCalendarServices::class)]
final class AdventCalendarServicesTest extends TestCase
{
    public function testTheCalendarAndItsCollaboratorsAreWired(): void
    {
        $container = $this->load([]);

        $calendar = $container->getDefinition(AdventCalendar::class);
        self::assertEquals([new Reference(OpeningStore::class), new Reference('clock'), new Reference('event_dispatcher')], $calendar->getArguments());
        self::assertTrue($calendar->isPublic());
        self::assertTrue($container->hasDefinition(NoMember::class));
        self::assertEquals([new Reference(MemberResolver::class)], $container->getDefinition(Members::class)->getArguments());
        self::assertTrue($container->hasDefinition(Celebrations::class));
        self::assertEquals([new Reference(Celebrations::class)], $container->getDefinition(CelebrationListener::class)->getArguments());
        self::assertSame([['event' => BoxOpened::class]], $container->getDefinition(CelebrationListener::class)->getTag('kernel.event_listener'));

        foreach ([SurpriseType::class, CalendarType::class] as $type) {
            self::assertTrue($container->hasDefinition($type));
            self::assertSame([[]], $container->getDefinition($type)->getTag('form.type'));
        }

        $extension = $container->getDefinition(AdventCalendarExtension::class);
        self::assertEquals([new Reference(KitComponents::class)], $extension->getArguments());
        self::assertSame([[]], $extension->getTag('twig.attribute_extension'));
        self::assertSame([[]], $extension->getTag('twig.runtime'));
    }

    public function testTheControllerIsPublicAndGetsItsServicesInOrder(): void
    {
        $controller = $this->load([])->getDefinition(CalendarController::class);

        self::assertTrue($controller->isPublic());
        self::assertSame([[]], $controller->getTag('controller.service_arguments'));
        self::assertEquals([
            new Reference(CalendarCatalogue::class),
            new Reference(AdventCalendar::class),
            new Reference(Members::class),
            new Reference(Celebrations::class),
            new Reference('clock'),
            new Reference('twig'),
            new Reference('security.csrf.token_manager'),
            new Reference('router'),
            'base.html.twig',
            false,
            'confetti',
        ], $controller->getArguments());
    }

    public function testTheCommandsAndTheStoresAreWired(): void
    {
        $container = $this->load([]);

        self::assertEquals([new Reference(CalendarCatalogue::class)], $container->getDefinition(ListCalendarsCommand::class)->getArguments());
        self::assertEquals([new Reference(CalendarCatalogue::class), new Reference('clock')], $container->getDefinition(ShowCalendarCommand::class)->getArguments());
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(OpeningsSchemaListener::class)->getArguments());
        self::assertEquals(
            [new Reference('doctrine.orm.default_entity_manager'), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)],
            $container->getDefinition(DoctrineCalendarCatalogue::class)->getArguments(),
        );
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(CheckCalendarsCommand::class)->getArguments());
        self::assertTrue($container->getAlias(CalendarCatalogue::class)->isPublic());
        self::assertSame(Calendars::class, (string) $container->getAlias(CalendarCatalogue::class));
    }

    public function testThePrivacyModuleIsPublicAndTagged(): void
    {
        $privacy = $this->load([])->getDefinition(AdventCalendarPrivacy::class);

        self::assertTrue($privacy->isPublic());
        self::assertEquals([new Reference(AdventCalendar::class), new Reference(MemberOpenings::class), 'member'], $privacy->getArguments());
        self::assertSame([[]], $privacy->getTag(PhilPrivacyBundle::EXPORTER_TAG));
        self::assertSame([[]], $privacy->getTag(PhilPrivacyBundle::ERASER_TAG));
    }

    public function testTheMemoryStoreIsPublic(): void
    {
        $container = $this->load(['store' => 'memory']);

        self::assertTrue($container->getAlias(OpeningStore::class)->isPublic());
        self::assertSame(InMemoryOpenings::class, (string) $container->getAlias(OpeningStore::class));
    }

    public function testTheTwoStoresAreAccepted(): void
    {
        self::assertSame('doctrine', $this->setting(['store' => 'doctrine'], 'store'));
        self::assertSame('memory', $this->setting(['store' => 'memory'], 'store'));
    }

    public function testBothEffectsAreAccepted(): void
    {
        self::assertSame('confetti', $this->setting(['celebration' => ['effect' => 'confetti']], 'celebration', 'effect'));
        self::assertSame('fireworks', $this->setting(['celebration' => ['effect' => 'fireworks']], 'celebration', 'effect'));
    }

    public function testThePoliciesAreAcceptedAndDefaultToUntilClosing(): void
    {
        $calendar = static fn (array $more): array => $more + ['title' => 'T', 'timezone' => 'UTC', 'first_date' => '2026-12-01', 'surprises' => [['kind' => 'points']]];

        self::assertSame('until_closing', $this->setting(['calendars' => ['a' => $calendar([])]], 'calendars', 'a', 'policy'));

        foreach (['until_closing', 'same_day', 'catch_up'] as $policy) {
            $more = 'catch_up' === $policy ? ['catch_up_days' => 2] : [];
            self::assertSame($policy, $this->setting(['calendars' => ['a' => $calendar(['policy' => $policy] + $more)]], 'calendars', 'a', 'policy'));
        }
    }

    public function testACalendarLeavesItsDefaultsToTheTree(): void
    {
        $config = ['calendars' => ['a' => ['title' => 'T', 'timezone' => 'UTC', 'first_date' => '2026-12-01', 'surprises' => [['kind' => 'points']]]]];

        self::assertSame(0, $this->setting($config, 'calendars', 'a', 'catch_up_days'));
        self::assertTrue($this->setting($config, 'calendars', 'a', 'published'));
        self::assertNull($this->setting($config, 'calendars', 'a', 'closing_date'));
        self::assertSame(['kind' => 'points', 'reference' => 'points', 'quantity' => 1], $this->setting($config, 'calendars', 'a', 'surprises', 0));
    }

    public function testAQuantityIsOneToTheLimit(): void
    {
        $surprise = static fn (int $quantity): array => ['calendars' => ['a' => ['title' => 'T', 'timezone' => 'UTC', 'first_date' => '2026-12-01', 'surprises' => [['kind' => 'points', 'quantity' => $quantity]]]]];

        self::assertSame(1, $this->setting($surprise(1), 'calendars', 'a', 'surprises', 0, 'quantity'));
        self::assertSame(Surprise::MAX_QUANTITY, $this->setting($surprise(Surprise::MAX_QUANTITY), 'calendars', 'a', 'surprises', 0, 'quantity'));

        foreach ([0 => 'too small', Surprise::MAX_QUANTITY + 1 => 'too big'] as $refused => $why) {
            try {
                $this->process($surprise($refused));
                self::fail('The quantity '.$refused.' is refused.');
            } catch (InvalidConfigurationException $exception) {
                self::assertStringContainsString($why, $exception->getMessage());
            }
        }
    }

    public function testACalendarRefusedByTheLibraryKeepsItsReasonAndItsPrevious(): void
    {
        try {
            $this->process(['calendars' => ['a' => ['title' => 'T', 'timezone' => 'Mars/Olympus', 'first_date' => '2026-12-01', 'surprises' => [['kind' => 'points']]]]]);
            self::fail('The timezone is refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame(0, $invalidConfigurationException->getCode());
            self::assertNotNull($invalidConfigurationException->getPrevious());
            self::assertSame(0, $invalidConfigurationException->getPrevious()->getCode());
            self::assertNotNull($invalidConfigurationException->getPrevious()->getPrevious());
        }
    }

    /**
     * One value of the configuration once the tree has processed it, found by its path.
     *
     * @param array<string, mixed> $config
     */
    private function setting(array $config, string|int ...$path): mixed
    {
        $node = $this->process($config);

        foreach ($path as $key) {
            self::assertIsArray($node);
            self::assertArrayHasKey($key, $node);
            $node = $node[$key];
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function process(array $config): array
    {
        $extension = new TilttekAdventCalendarBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        return new Processor()->process($configuration->getConfigTreeBuilder()->buildTree(), [$config]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new TilttekAdventCalendarBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }
}
