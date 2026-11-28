<?php

declare(strict_types=1);

namespace Tilttek\SetsBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Sets\DeliveryBackoff;
use Tilttek\Sets\Sets;
use Tilttek\Sets\Store\InMemoryCompletionStore;
use Tilttek\Sets\Store\InMemoryDefinitionStore;
use Tilttek\SetsBundle\Infrastructure\Console\DeliverCommand;
use Tilttek\SetsBundle\Infrastructure\DependencyInjection\SetsServices;
use Tilttek\SetsBundle\Infrastructure\Messenger\DeliverPendingCompletionsHandler;
use Tilttek\SetsBundle\Infrastructure\Scheduler\DeliverySchedule;
use Tilttek\SetsBundle\TilttekSetsBundle;

#[CoversClass(TilttekSetsBundle::class)]
#[CoversClass(SetsServices::class)]
final class TilttekSetsBundleTest extends TestCase
{
    public function testTheDefaultsKeepTheSetsInMemoryAndRegisterEveryIntegration(): void
    {
        $container = $this->load([]);

        self::assertSame('tilttek_sets', $this->extension()->getAlias());
        self::assertSame(['service_container', InMemoryDefinitionStore::class, InMemoryCompletionStore::class, DeliveryBackoff::class, Sets::class, DeliverCommand::class, DeliverPendingCompletionsHandler::class, DeliverySchedule::class], array_keys($container->getDefinitions()));
        self::assertSame(InMemoryDefinitionStore::class, $container->getDefinition(InMemoryDefinitionStore::class)->getClass());
        self::assertSame(InMemoryCompletionStore::class, $container->getDefinition(InMemoryCompletionStore::class)->getClass());
        self::assertSame([60, 86_400, 3], $container->getDefinition(DeliveryBackoff::class)->getArguments());
        self::assertSame(DeliveryBackoff::class, $container->getDefinition(DeliveryBackoff::class)->getClass());
        self::assertEquals([
            new Reference(InMemoryDefinitionStore::class),
            new Reference(InMemoryCompletionStore::class),
            null,
            new Reference('event_dispatcher', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            new Reference(DeliveryBackoff::class),
        ], $container->getDefinition(Sets::class)->getArguments());
        $dispatcher = $container->getDefinition(Sets::class)->getArgument(3);
        self::assertInstanceOf(Reference::class, $dispatcher);
        self::assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $dispatcher->getInvalidBehavior());
        self::assertSame(Sets::class, $container->getDefinition(Sets::class)->getClass());
        self::assertTrue($container->getDefinition(Sets::class)->isPublic());

        foreach ([DeliverCommand::class, DeliverPendingCompletionsHandler::class, DeliverySchedule::class] as $id) {
            self::assertSame($id, $container->getDefinition($id)->getClass());
            self::assertTrue($container->getDefinition($id)->isAutoconfigured());
            self::assertFalse($container->getDefinition($id)->isPublic());
        }

        self::assertEquals([new Reference(Sets::class)], $container->getDefinition(DeliverCommand::class)->getArguments());
        self::assertEquals([new Reference(Sets::class)], $container->getDefinition(DeliverPendingCompletionsHandler::class)->getArguments());
        self::assertSame(['5 minutes', 100, null], $container->getDefinition(DeliverySchedule::class)->getArguments());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'definition_store' => 'app.definitions',
            'completion_store' => 'app.completions',
            'clock' => 'clock',
            'backoff' => ['first_delay' => 30, 'longest_delay' => 3_600, 'report_after' => 5],
            'schedule' => ['frequency' => '1 hour', 'limit' => 500, 'lock' => 'app.sets_lock'],
        ]);

        self::assertFalse($container->hasDefinition(InMemoryDefinitionStore::class));
        self::assertFalse($container->hasDefinition(InMemoryCompletionStore::class));
        self::assertEquals([new Reference('app.definitions'), new Reference('app.completions'), new Reference('clock')], \array_slice($container->getDefinition(Sets::class)->getArguments(), 0, 3));
        self::assertSame([30, 3_600, 5], $container->getDefinition(DeliveryBackoff::class)->getArguments());
        self::assertEquals(['1 hour', 500, new Reference('app.sets_lock')], $container->getDefinition(DeliverySchedule::class)->getArguments());
    }

    public function testTheScheduleCanBeDisabled(): void
    {
        $container = $this->load(['schedule' => false]);

        self::assertFalse($container->hasDefinition(DeliverySchedule::class));
        self::assertTrue($container->hasDefinition(DeliverPendingCompletionsHandler::class));
    }

    public function testAnIntegrationWhoseComponentIsMissingIsLeftOut(): void
    {
        $config = ['definition_store' => null, 'completion_store' => null, 'clock' => null, 'backoff' => ['first_delay' => 60, 'longest_delay' => 86_400, 'report_after' => 3], 'schedule' => ['enabled' => true, 'frequency' => '5 minutes', 'limit' => 100, 'lock' => null]];

        $none = new ContainerBuilder();
        SetsServices::register($none, $config, false, false, false);
        self::assertSame(['service_container', InMemoryDefinitionStore::class, InMemoryCompletionStore::class, DeliveryBackoff::class, Sets::class], array_keys($none->getDefinitions()));

        $console = new ContainerBuilder();
        SetsServices::register($console, $config, true, false, false);
        self::assertTrue($console->hasDefinition(DeliverCommand::class));
        self::assertFalse($console->hasDefinition(DeliverPendingCompletionsHandler::class));

        $messenger = new ContainerBuilder();
        SetsServices::register($messenger, $config, false, true, false);
        self::assertTrue($messenger->hasDefinition(DeliverPendingCompletionsHandler::class));
        self::assertFalse($messenger->hasDefinition(DeliverySchedule::class));

        $scheduler = new ContainerBuilder();
        SetsServices::register($scheduler, $config, false, false, true);
        self::assertTrue($scheduler->hasDefinition(DeliverySchedule::class));
        self::assertFalse($scheduler->hasDefinition(DeliverCommand::class));
    }

    public function testTheLongestDelayIsNotUnderTheFirst(): void
    {
        self::assertSame([7, 7, 1], $this->load(['backoff' => ['first_delay' => 7, 'longest_delay' => 7, 'report_after' => 1]])->getDefinition(DeliveryBackoff::class)->getArguments());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The longest delay of the backoff is not under the first.');

        $this->load(['backoff' => ['first_delay' => 60, 'longest_delay' => 59]]);
    }

    public function testTheSmallestValuesAreAccepted(): void
    {
        $container = $this->load(['backoff' => ['first_delay' => 1, 'longest_delay' => 1, 'report_after' => 1], 'schedule' => ['limit' => 1]]);

        self::assertSame([1, 1, 1], $container->getDefinition(DeliveryBackoff::class)->getArguments());
        self::assertSame(1, $container->getDefinition(DeliverySchedule::class)->getArgument(1));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('underTheirMinimum')]
    public function testAValueUnderItsMinimumIsRefused(array $config, string $path): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The value 0 is too small for path "tilttek_sets.%s". Should be greater than or equal to 1', $path));

        $this->load($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function underTheirMinimum(): iterable
    {
        yield 'first delay' => [['backoff' => ['first_delay' => 0]], 'backoff.first_delay'];
        yield 'longest delay' => [['backoff' => ['first_delay' => 1, 'longest_delay' => 0]], 'backoff.longest_delay'];
        yield 'report after' => [['backoff' => ['report_after' => 0]], 'backoff.report_after'];
        yield 'limit' => [['schedule' => ['limit' => 0]], 'schedule.limit'];
    }

    public function testTheStoresAreDescribed(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $reference = new YamlReferenceDumper()->dump($configuration);

        self::assertStringContainsString('# The service id of the store of the definitions, a Tilttek\\Sets\\Contract\\DefinitionStore; in memory when null, for tests.', $reference);
        self::assertStringContainsString('# The service id of the store of the completions, a Tilttek\\Sets\\Contract\\CompletionStore that also implements PendingDeliveries', $reference);
    }

    public function testTheLimitOfTheScheduleIsBounded(): void
    {
        self::assertSame(Sets::PENDING_LIMIT, $this->load(['schedule' => ['limit' => Sets::PENDING_LIMIT]])->getDefinition(DeliverySchedule::class)->getArgument(1));

        $this->expectException(InvalidConfigurationException::class);

        $this->load(['schedule' => ['limit' => Sets::PENDING_LIMIT + 1]]);
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
        $extension = new TilttekSetsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
