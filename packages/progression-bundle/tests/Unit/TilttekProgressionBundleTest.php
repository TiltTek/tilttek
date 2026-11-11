<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Unit;

use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Progression;
use Tilttek\Progression\ProgressionProjection;
use Tilttek\Progression\Store\ExperienceStore;
use Tilttek\Progression\Store\InMemoryExperienceStore;
use Tilttek\Progression\Store\InMemoryMilestoneStore;
use Tilttek\Progression\Store\InMemoryStreakStore;
use Tilttek\Progression\Store\MilestoneStore;
use Tilttek\Progression\Store\StreakStore;
use Tilttek\Progression\Streak\ContextTimezone;
use Tilttek\Progression\Streak\FixedTimezone;
use Tilttek\Progression\Streak\StreakKeeper;
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Streak\TimezoneResolver;
use Tilttek\Progression\Tracks;
use Tilttek\ProgressionBundle\Infrastructure\Console\ShowCommand;
use Tilttek\ProgressionBundle\Infrastructure\Console\SweepCommand;
use Tilttek\ProgressionBundle\Infrastructure\DependencyInjection\ProgressionServices;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\DbalExperienceStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\DbalMilestoneStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\DbalStreakStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\ProgressionSchemaListener;
use Tilttek\ProgressionBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\ProgressionBundle\Infrastructure\Privacy\ProgressionPrivacy;
use Tilttek\ProgressionBundle\Infrastructure\Twig\ProgressionExtension;
use Tilttek\ProgressionBundle\TilttekProgressionBundle;

#[CoversClass(TilttekProgressionBundle::class)]
#[CoversClass(ProgressionServices::class)]
final class TilttekProgressionBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertSame('tilttek_progression', $this->extension()->getAlias());
        self::assertSame([
            'service_container', DbalExperienceStore::class, DbalMilestoneStore::class, DbalStreakStore::class, ProgressionSchemaListener::class,
            Tracks::class, StreakRules::class, 'tilttek_progression.timezone', TimezoneResolver::class, DispatchingObserver::class, StreakKeeper::class,
            Progression::class, ProgressionProjection::class, SweepCommand::class, ShowCommand::class, ProgressionExtension::class, ...$this->privacy(),
        ], array_keys($container->getDefinitions()));

        foreach ([ExperienceStore::class => DbalExperienceStore::class, MilestoneStore::class => DbalMilestoneStore::class, StreakStore::class => DbalStreakStore::class] as $port => $store) {
            self::assertEquals([$connection], $container->getDefinition($store)->getArguments());
            self::assertFalse($container->getDefinition($store)->isPublic());
            self::assertSame([$store, true], [(string) $container->getAlias($port), $container->getAlias($port)->isPublic()]);
        }

        self::assertEquals([$connection], $container->getDefinition(ProgressionSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(ProgressionSchemaListener::class)->getTags());
        self::assertSame([Tracks::class, 'fromArray'], $container->getDefinition(Tracks::class)->getFactory());
        self::assertSame([[], 'UTC'], $container->getDefinition(Tracks::class)->getArguments());
        self::assertTrue($container->getDefinition(Tracks::class)->isPublic());
        self::assertSame([StreakRules::class, 'fromArray'], $container->getDefinition(StreakRules::class)->getFactory());
        self::assertSame([[], 'UTC'], $container->getDefinition(StreakRules::class)->getArguments());
        self::assertTrue($container->getDefinition(StreakRules::class)->isPublic());
        self::assertSame(['UTC'], $container->getDefinition('tilttek_progression.timezone')->getArguments());
        self::assertSame(FixedTimezone::class, $container->getDefinition(TimezoneResolver::class)->getClass());
        self::assertEquals([new Reference('tilttek_progression.timezone')], $container->getDefinition(TimezoneResolver::class)->getArguments());
        self::assertEquals([new Reference(EventDispatcherInterface::class)], $container->getDefinition(DispatchingObserver::class)->getArguments());
        self::assertEquals([new Reference(StreakRules::class), new Reference(StreakStore::class), new Reference(DispatchingObserver::class)], $container->getDefinition(StreakKeeper::class)->getArguments());
        self::assertEquals([new Reference(Tracks::class), new Reference(StreakKeeper::class), new Reference(ExperienceStore::class), new Reference(MilestoneStore::class), new Reference(StreakStore::class), new Reference('clock')], $container->getDefinition(Progression::class)->getArguments());
        self::assertTrue($container->getDefinition(Progression::class)->isPublic());
        self::assertEquals([new Reference(Tracks::class), new Reference(StreakKeeper::class), new Reference(ExperienceStore::class), new Reference(MilestoneStore::class), new Reference('clock'), new Reference(TimezoneResolver::class), new Reference(DispatchingObserver::class), 'progression'], $container->getDefinition(ProgressionProjection::class)->getArguments());
        self::assertSame(['tilttek_activity.projection' => [[]], 'kernel.reset' => [['method' => 'clearReplay']]], $container->getDefinition(ProgressionProjection::class)->getTags());
        self::assertTrue($container->getDefinition(ProgressionProjection::class)->isPublic());

        foreach ([SweepCommand::class, ShowCommand::class] as $command) {
            self::assertEquals([new Reference(Progression::class)], $container->getDefinition($command)->getArguments());
            self::assertSame(['console.command' => [[]]], $container->getDefinition($command)->getTags());
        }

        self::assertEquals([new Reference(Progression::class), 'tilttek_progression'], $container->getDefinition(ProgressionExtension::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(ProgressionExtension::class)->getTags());
        self::assertTrue($container->getDefinition(ProgressionExtension::class)->isPublic());
    }

    public function testThePrivacyModuleWhenPhilPrivacyBundleIsInstalled(): void
    {
        if (!interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $container = $this->load([]);
        self::assertEquals([new Reference(Progression::class), 'member'], $container->getDefinition(ProgressionPrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(ProgressionPrivacy::class)->getTags());
        self::assertSame('user', $this->load(['privacy' => ['member_kind' => 'user']])->getDefinition(ProgressionPrivacy::class)->getArgument(1));
    }

    public function testNoPrivacyModuleWithoutPhilPrivacyBundle(): void
    {
        if (interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is installed: its absence is checked where it is missing.');
        }

        self::assertFalse($this->load([])->hasDefinition(ProgressionPrivacy::class));
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'store' => 'memory',
            'timezone' => 'America/Toronto',
            'tracks' => [
                'player' => [
                    'curve' => ['type' => 'linear', 'step' => 100, 'levels' => 20],
                    'seasons' => ['length' => 'quarter'],
                    'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 20, 'cap' => ['amount' => 100, 'period' => 'day'], 'context' => ['ranked' => true]]],
                    'milestones' => ['veteran' => ['level' => 10], 'thousand' => ['experience' => 1000]],
                ],
                'character' => ['curve' => ['type' => 'table', 'thresholds' => [300, 900]], 'subject' => 'object', 'seasons' => ['named' => [['code' => 'summer', 'start' => '2026-06-21', 'end' => '2026-09-22']]]],
            ],
            'streaks' => ['daily' => ['verbs' => ['game.played'], 'grace' => ['allowed' => 1, 'window' => 7]]],
            'timezone_context' => 'timezone',
            'projection' => 'levels',
            'translation_domain' => 'levels',
        ]);

        self::assertSame([
            'service_container', InMemoryExperienceStore::class, InMemoryMilestoneStore::class, InMemoryStreakStore::class,
            Tracks::class, StreakRules::class, 'tilttek_progression.timezone', TimezoneResolver::class, DispatchingObserver::class, StreakKeeper::class,
            Progression::class, ProgressionProjection::class, SweepCommand::class, ShowCommand::class, ProgressionExtension::class, ...$this->privacy(),
        ], array_keys($container->getDefinitions()));
        self::assertSame([], $container->getDefinition(InMemoryStreakStore::class)->getArguments());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryExperienceStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryMilestoneStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryStreakStore::class)->getTags());
        self::assertSame([InMemoryStreakStore::class, true], [(string) $container->getAlias(StreakStore::class), $container->getAlias(StreakStore::class)->isPublic()]);

        self::assertEquals([
            'player' => [
                'curve' => ['type' => 'linear', 'step' => 100, 'levels' => 20, 'thresholds' => []],
                'subject' => 'member',
                'seasons' => ['length' => 'quarter', 'named' => []],
                'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 20, 'cap' => ['amount' => 100, 'period' => 'day'], 'context' => ['ranked' => true], 'per_quantity' => true]],
                'milestones' => ['veteran' => ['level' => 10], 'thousand' => ['experience' => 1000]],
            ],
            'character' => [
                'curve' => ['type' => 'table', 'thresholds' => [300, 900]],
                'subject' => 'object',
                'seasons' => ['named' => [['code' => 'summer', 'start' => '2026-06-21', 'end' => '2026-09-22']]],
                'rules' => [],
                'milestones' => [],
            ],
        ], $container->getDefinition(Tracks::class)->getArgument(0));
        self::assertSame('America/Toronto', $container->getDefinition(Tracks::class)->getArgument(1));
        self::assertSame(['daily' => ['verbs' => ['game.played'], 'grace' => ['allowed' => 1, 'window' => 7], 'unit' => 'day', 'context' => []]], $container->getDefinition(StreakRules::class)->getArgument(0));
        self::assertSame('America/Toronto', $container->getDefinition(StreakRules::class)->getArgument(1));
        self::assertSame(['America/Toronto'], $container->getDefinition('tilttek_progression.timezone')->getArguments());
        self::assertSame(ContextTimezone::class, $container->getDefinition(TimezoneResolver::class)->getClass());
        self::assertEquals(['timezone', new Reference('tilttek_progression.timezone')], $container->getDefinition(TimezoneResolver::class)->getArguments());
        self::assertSame('levels', $container->getDefinition(ProgressionProjection::class)->getArgument(7));
        self::assertSame('levels', $container->getDefinition(ProgressionExtension::class)->getArgument(1));

        $other = $this->load(['connection' => 'gamification']);
        self::assertEquals([new Reference('doctrine.dbal.gamification_connection')], $other->getDefinition(DbalStreakStore::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'gamification']]], $other->getDefinition(ProgressionSchemaListener::class)->getTags());
        self::assertTrue($this->load(['store' => 'doctrine'])->hasDefinition(DbalExperienceStore::class));
    }

    public function testTheEdgesOfTheConfiguration(): void
    {
        $container = $this->load([
            'tracks' => [
                'game-master' => [
                    'curve' => ['type' => 'linear', 'step' => 1, 'levels' => 2],
                    'subject' => 'member',
                    'seasons' => ['length' => 'month'],
                    'rules' => ['ran-games' => ['verbs' => ['game.ran'], 'experience' => 1, 'cap' => ['amount' => 1, 'period' => 'day'], 'context' => ['the-role' => 'gm']]],
                    'milestones' => ['first-game' => ['experience' => 1], 'level-two' => ['level' => 2]],
                ],
                'quarterly' => ['curve' => ['type' => 'quadratic', 'base' => 1], 'seasons' => ['length' => 'quarter']],
                'yearly' => ['curve' => ['type' => 'exponential', 'base' => 1, 'factor' => 2.0, 'levels' => 3], 'seasons' => ['length' => 'year']],
            ],
            'streaks' => [
                'weekly-play' => ['verbs' => ['game.played'], 'unit' => 'week', 'grace' => ['allowed' => 1, 'window' => 2]],
                'daily' => ['verbs' => ['game.played'], 'unit' => 'day'],
            ],
        ]);

        $tracks = $container->getDefinition(Tracks::class)->getArgument(0);
        self::assertIsArray($tracks);
        self::assertSame(['game-master', 'quarterly', 'yearly'], array_keys($tracks));
        $master = $tracks['game-master'];
        self::assertIsArray($master);
        self::assertEquals(['ran-games' => ['verbs' => ['game.ran'], 'experience' => 1, 'per_quantity' => true, 'cap' => ['amount' => 1, 'period' => 'day'], 'context' => ['the_role' => 'gm']]], $master['rules']);
        self::assertEquals(['first-game' => ['experience' => 1], 'level-two' => ['level' => 2]], $master['milestones']);
        $streaks = $container->getDefinition(StreakRules::class)->getArgument(0);
        self::assertIsArray($streaks);
        self::assertSame(['weekly-play', 'daily'], array_keys($streaks));
    }

    public function testAWrongTrackFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The linear curve of the track "player" needs its "step".'));
        $this->load(['tracks' => ['player' => ['curve' => ['type' => 'linear']]]]);
    }

    public function testAWrongStreakFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('A grace forgives at least 1 missed unit in a window longer than that, of at most 366; 3 in 3 is not one.'));
        $this->load(['streaks' => ['daily' => ['verbs' => ['a'], 'grace' => ['allowed' => 3, 'window' => 3]]]]);
    }

    public function testATimezoneThatIsNoneFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('"Mars/Olympus" is not a timezone.'));
        $this->load(['timezone' => 'Mars/Olympus']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function wrongConfigurations(): iterable
    {
        yield 'store' => [['store' => 'redis']];
        yield 'empty connection' => [['connection' => '']];
        yield 'empty projection' => [['projection' => '']];
        yield 'empty domain' => [['translation_domain' => '']];
        yield 'empty member kind' => [['privacy' => ['member_kind' => '']]];
        yield 'curve type' => [['tracks' => ['p' => ['curve' => ['type' => 'cubic']]]]];
        yield 'no curve' => [['tracks' => ['p' => []]]];
        yield 'subject' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 1], 'subject' => 'team']]]];
        yield 'rule experience' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 1], 'rules' => ['r' => ['verbs' => ['a'], 'experience' => 0]]]]]];
        yield 'rule verbs' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 1], 'rules' => ['r' => ['verbs' => [], 'experience' => 1]]]]]];
        yield 'cap period' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 1], 'rules' => ['r' => ['verbs' => ['a'], 'experience' => 1, 'cap' => ['amount' => 1, 'period' => 'hour']]]]]]];
        yield 'rule context' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 1], 'rules' => ['r' => ['verbs' => ['a'], 'experience' => 1, 'context' => ['x' => 1.5]]]]]]];
        yield 'season length' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 1], 'seasons' => ['length' => 'week']]]]];
        yield 'streak unit' => [['streaks' => ['daily' => ['verbs' => ['a'], 'unit' => 'month']]]];
        yield 'streak verbs' => [['streaks' => ['daily' => ['verbs' => []]]]];
        yield 'streak grace' => [['streaks' => ['daily' => ['verbs' => ['a'], 'grace' => ['allowed' => 0, 'window' => 7]]]]];
        yield 'streak context' => [['streaks' => ['daily' => ['verbs' => ['a'], 'context' => ['x' => null]]]]];
        yield 'step' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 0]]]]];
        yield 'base' => [['tracks' => ['p' => ['curve' => ['type' => 'quadratic', 'base' => 0]]]]];
        yield 'levels' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 1, 'levels' => 1]]]]];
        yield 'cap amount' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 1], 'rules' => ['r' => ['verbs' => ['a'], 'experience' => 1, 'cap' => ['amount' => 0, 'period' => 'day']]]]]]];
        yield 'milestone experience' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 1], 'milestones' => ['m' => ['experience' => 0]]]]]];
        yield 'milestone level' => [['tracks' => ['p' => ['curve' => ['type' => 'linear', 'step' => 1], 'milestones' => ['m' => ['level' => 1]]]]]];
        yield 'grace window' => [['streaks' => ['daily' => ['verbs' => ['a'], 'grace' => ['allowed' => 1, 'window' => 1]]]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('wrongConfigurations')]
    public function testAWrongConfigurationIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    /**
     * The privacy module, registered when phil/privacy-bundle is installed.
     *
     * @return list<class-string>
     */
    private function privacy(): array
    {
        return interface_exists(PersonalDataEraser::class) ? [ProgressionPrivacy::class] : [];
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
        $extension = new TilttekProgressionBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
