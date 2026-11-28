<?php

declare(strict_types=1);

namespace Tilttek\SetsBundle;

use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Tilttek\Sets\DeliveryBackoff;
use Tilttek\Sets\Sets;
use Tilttek\SetsBundle\Infrastructure\DependencyInjection\SetsServices;
use Tilttek\SetsBundle\Infrastructure\Messenger\DeliverPendingCompletions;

/**
 * The sets of `tilttek/sets` in a Symfony application, with the delivery of the completions resumed:
 *
 * ```yaml
 * tilttek_sets:
 *     definition_store: App\Sets\DoctrineDefinitionStore
 *     completion_store: App\Sets\DoctrineCompletionStore
 *     backoff: { first_delay: 60, longest_delay: 86400, report_after: 3 }
 *     schedule: { frequency: 5 minutes, limit: 100, lock: app.sets_lock }
 * ```
 *
 * The configuration names the services that keep the definitions and the completions — memory, for tests, when it
 * names none —, the clock, the backoff of a holder whose delivery failed, and the schedule that tells the undelivered
 * completions again. The `Sets` service hears the application's event dispatcher; the command, the handler and the
 * schedule are registered when their Symfony components are installed.
 *
 * @phpstan-import-type SetsConfig from SetsServices
 */
final class TilttekSetsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_sets';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('definition_store')
                    ->info('The service id of the store of the definitions, a Tilttek\\Sets\\Contract\\DefinitionStore; in memory when null, for tests.')
                    ->defaultNull()
                ->end()
                ->scalarNode('completion_store')
                    ->info('The service id of the store of the completions, a Tilttek\\Sets\\Contract\\CompletionStore that also implements PendingDeliveries for the command and the schedule; in memory when null, for tests.')
                    ->defaultNull()
                ->end()
                ->scalarNode('clock')
                    ->info('The service id of the PSR-20 clock the completions are dated by; the clock in UTC of the library when null.')
                    ->defaultNull()
                ->end()
                ->arrayNode('backoff')
                    ->info('How long a holder whose delivery failed waits before it is told again, in seconds.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('first_delay')->info('The delay after a first failure, doubled after each failure that follows.')->min(1)->defaultValue(DeliveryBackoff::FIRST_DELAY)->end()
                        ->integerNode('longest_delay')->info('The longest delay, not under the first.')->min(1)->defaultValue(DeliveryBackoff::LONGEST_DELAY)->end()
                        ->integerNode('report_after')->info('The failures in a row from which each one is told by a DeliveryKeepsFailing event.')->min(1)->defaultValue(DeliveryBackoff::REPORT_AFTER)->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $backoff): bool => $backoff['longest_delay'] < $backoff['first_delay'])
                        ->thenInvalid('The longest delay of the backoff is not under the first.')
                    ->end()
                ->end()
                ->arrayNode('schedule')
                    ->info('The schedule "tilttek_sets", which tells the undelivered completions again, when symfony/scheduler is installed.')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('frequency')->info('The interval between two runs: a relative format such as "5 minutes", or seconds.')->cannotBeEmpty()->defaultValue('5 minutes')->end()
                        ->integerNode('limit')->info('The most holders a run tells.')->min(1)->max(Sets::PENDING_LIMIT)->defaultValue(DeliverPendingCompletions::LIMIT)->end()
                        ->scalarNode('lock')->info('The service id of a lock shared by the workers that consume the schedule.')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var SetsConfig $config */
        SetsServices::register($builder, $config, class_exists(Command::class), class_exists(AsMessageHandler::class), interface_exists(ScheduleProviderInterface::class));
    }
}
