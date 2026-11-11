<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Tests\Unit;

use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Points\Currencies;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Exception\UnknownCurrency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Ledger\Ledger;
use Tilttek\Points\Points;
use Tilttek\Points\PointsProjection;
use Tilttek\Points\Rule\Rules;
use Tilttek\PointsBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\PointsBundle\Infrastructure\Console\HistoryCommand;
use Tilttek\PointsBundle\Infrastructure\DependencyInjection\PointsServices;
use Tilttek\PointsBundle\Infrastructure\Doctrine\DbalLedger;
use Tilttek\PointsBundle\Infrastructure\Doctrine\PointsSchemaListener;
use Tilttek\PointsBundle\Infrastructure\Privacy\PointsPrivacy;
use Tilttek\PointsBundle\TilttekPointsBundle;

#[CoversClass(TilttekPointsBundle::class)]
#[CoversClass(PointsServices::class)]
final class TilttekPointsBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertSame('tilttek_points', $this->extension()->getAlias());
        self::assertSame(['service_container', DbalLedger::class, PointsSchemaListener::class, Currencies::class, Rules::class, Points::class, PointsProjection::class, ExpireCommand::class, HistoryCommand::class, ...$this->privacy()], array_keys($container->getDefinitions()));
        self::assertEquals([$connection], $container->getDefinition(DbalLedger::class)->getArguments());
        self::assertFalse($container->getDefinition(DbalLedger::class)->isPublic());
        self::assertSame([DbalLedger::class, true], [(string) $container->getAlias(Ledger::class), $container->getAlias(Ledger::class)->isPublic()]);
        self::assertEquals([$connection], $container->getDefinition(PointsSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(PointsSchemaListener::class)->getTags());
        self::assertSame([Currencies::class, 'fromArray'], $container->getDefinition(Currencies::class)->getFactory());
        self::assertSame([['points' => null], null], $container->getDefinition(Currencies::class)->getArguments());
        self::assertFalse($container->getDefinition(Currencies::class)->isPublic());
        self::assertSame([Rules::class, 'fromArray'], $container->getDefinition(Rules::class)->getFactory());
        self::assertSame([[], 'UTC'], $container->getDefinition(Rules::class)->getArguments());
        self::assertEquals([new Reference(Ledger::class), new Reference('clock'), new Reference(Currencies::class), 500], $container->getDefinition(Points::class)->getArguments());
        self::assertTrue($container->getDefinition(Points::class)->isPublic());
        self::assertEquals([new Reference(Ledger::class), new Reference(Currencies::class), new Reference(Rules::class), new Reference('clock'), 'points'], $container->getDefinition(PointsProjection::class)->getArguments());
        self::assertSame(['tilttek_activity.projection' => [[]]], $container->getDefinition(PointsProjection::class)->getTags());
        self::assertTrue($container->getDefinition(PointsProjection::class)->isPublic());

        foreach ([ExpireCommand::class, HistoryCommand::class] as $command) {
            self::assertEquals([new Reference(Points::class)], $container->getDefinition($command)->getArguments());
            self::assertSame(['console.command' => [[]]], $container->getDefinition($command)->getTags());
        }
    }

    public function testThePrivacyModuleWhenPhilPrivacyBundleIsInstalled(): void
    {
        if (!interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $container = $this->load([]);
        self::assertEquals([new Reference(Ledger::class), 'member', 500], $container->getDefinition(PointsPrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(PointsPrivacy::class)->getTags());
        self::assertEquals([new Reference(Ledger::class), 'user', 50], $this->load(['batch_size' => 50, 'privacy' => ['member_kind' => 'user']])->getDefinition(PointsPrivacy::class)->getArguments());
    }

    public function testNoPrivacyModuleWithoutPhilPrivacyBundle(): void
    {
        if (interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is installed: its absence is checked where it is missing.');
        }

        self::assertFalse($this->load([])->hasDefinition(PointsPrivacy::class));
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'store' => 'memory',
            'currencies' => ['points' => null, 'stars' => 'P1Y', 'crowns' => ['lifetime' => 'P6M']],
            'default_currency' => 'stars',
            'timezone' => 'America/Toronto',
            'rules' => [
                'games' => ['verbs' => ['game.played'], 'points' => 10, 'cap' => ['amount' => 50, 'period' => 'week']],
                'orders' => ['verbs' => ['order.paid'], 'points' => 1, 'currency' => 'points', 'per_quantity' => false, 'context' => ['online' => true, 'level' => 2, 'system' => 'dnd5e']],
            ],
            'projection' => 'loyalty',
            'batch_size' => 50,
        ]);

        self::assertSame(['service_container', InMemoryLedger::class, Currencies::class, Rules::class, Points::class, PointsProjection::class, ExpireCommand::class, HistoryCommand::class, ...$this->privacy()], array_keys($container->getDefinitions()));
        self::assertSame([InMemoryLedger::class, true], [(string) $container->getAlias(Ledger::class), $container->getAlias(Ledger::class)->isPublic()]);
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryLedger::class)->getTags());
        self::assertSame([['points' => null, 'stars' => 'P1Y', 'crowns' => 'P6M'], 'stars'], $container->getDefinition(Currencies::class)->getArguments());
        self::assertSame([[
            'games' => ['verbs' => ['game.played'], 'points' => 10, 'cap' => ['amount' => 50, 'period' => 'week'], 'currency' => null, 'per_quantity' => true, 'context' => []],
            'orders' => ['verbs' => ['order.paid'], 'points' => 1, 'currency' => 'points', 'per_quantity' => false, 'context' => ['online' => true, 'level' => 2, 'system' => 'dnd5e']],
        ], 'America/Toronto'], $container->getDefinition(Rules::class)->getArguments());
        self::assertSame(50, $container->getDefinition(Points::class)->getArgument(3));
        self::assertSame('loyalty', $container->getDefinition(PointsProjection::class)->getArgument(4));

        self::assertTrue($this->load(['store' => 'doctrine'])->hasDefinition(DbalLedger::class));
        self::assertSame(1, $this->load(['batch_size' => 1])->getDefinition(Points::class)->getArgument(3));
        self::assertSame([['points' => null, 'stars' => null], null], $this->load(['currencies' => ['points' => null, 'stars' => []]])->getDefinition(Currencies::class)->getArguments());
        $rules = $this->load(['rules' => ['games' => ['verbs' => ['a'], 'points' => 1, 'cap' => ['amount' => 1, 'period' => 'day']]]])->getDefinition(Rules::class)->getArgument(0);
        self::assertIsArray($rules);
        self::assertIsArray($rules['games'] ?? null);
        self::assertSame(['amount' => 1, 'period' => 'day'], $rules['games']['cap'] ?? null);

        $other = $this->load(['connection' => 'loyalty']);
        self::assertEquals([new Reference('doctrine.dbal.loyalty_connection')], $other->getDefinition(DbalLedger::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'loyalty']]], $other->getDefinition(PointsSchemaListener::class)->getTags());
    }

    public function testAWrongRuleFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectException(InvalidPoints::class);
        $this->expectExceptionMessageIsOrContains('"Games" is not a rule name');

        $this->load(['rules' => ['Games' => ['verbs' => ['game.played'], 'points' => 10]]]);
    }

    public function testATimezoneThatIsNoneFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidPoints::because('"Mars/Olympus" is not a timezone.'));

        $this->load(['timezone' => 'Mars/Olympus']);
    }

    public function testARuleInAnUnknownCurrencyFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(UnknownCurrency::named('stars'));

        $this->load(['rules' => ['games' => ['verbs' => ['game.played'], 'points' => 10, 'currency' => 'stars']]]);
    }

    public function testAnUnknownDefaultCurrencyFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(UnknownCurrency::named('stars'));

        $this->load(['default_currency' => 'stars']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function wrongConfigurations(): iterable
    {
        yield 'store' => [['store' => 'redis']];
        yield 'batch size' => [['batch_size' => 0]];
        yield 'no currency' => [['currencies' => []]];
        yield 'rule without verbs' => [['rules' => ['games' => ['verbs' => [], 'points' => 1]]]];
        yield 'rule without points' => [['rules' => ['games' => ['verbs' => ['a']]]]];
        yield 'rule with no points' => [['rules' => ['games' => ['verbs' => ['a'], 'points' => 0]]]];
        yield 'cap of nothing' => [['rules' => ['games' => ['verbs' => ['a'], 'points' => 1, 'cap' => ['amount' => 0, 'period' => 'day']]]]];
        yield 'cap of no period' => [['rules' => ['games' => ['verbs' => ['a'], 'points' => 1, 'cap' => ['amount' => 1, 'period' => 'fortnight']]]]];
        yield 'context of a float' => [['rules' => ['games' => ['verbs' => ['a'], 'points' => 1, 'context' => ['ratio' => 1.5]]]]];
        yield 'context of null' => [['rules' => ['games' => ['verbs' => ['a'], 'points' => 1, 'context' => ['none' => null]]]]];
        yield 'empty projection' => [['projection' => '']];
        yield 'empty member kind' => [['privacy' => ['member_kind' => '']]];
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

    public function testAContextConditionMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('A context condition is an integer, a boolean or a string.');

        $this->load(['rules' => ['games' => ['verbs' => ['a'], 'points' => 1, 'context' => ['ok' => 'yes', 'ratio' => 1.5]]]]);
    }

    /**
     * The privacy module, registered when phil/privacy-bundle is installed.
     *
     * @return list<class-string>
     */
    private function privacy(): array
    {
        return interface_exists(PersonalDataEraser::class) ? [PointsPrivacy::class] : [];
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
        $extension = new TilttekPointsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
