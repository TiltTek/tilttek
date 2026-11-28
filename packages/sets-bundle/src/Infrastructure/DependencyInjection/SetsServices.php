<?php

declare(strict_types=1);

namespace Tilttek\SetsBundle\Infrastructure\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Sets\DeliveryBackoff;
use Tilttek\Sets\Sets;
use Tilttek\Sets\Store\InMemoryCompletionStore;
use Tilttek\Sets\Store\InMemoryDefinitionStore;
use Tilttek\SetsBundle\Infrastructure\Console\DeliverCommand;
use Tilttek\SetsBundle\Infrastructure\Messenger\DeliverPendingCompletionsHandler;
use Tilttek\SetsBundle\Infrastructure\Scheduler\DeliverySchedule;

/**
 * The services of the bundle: the stores in memory when the configuration names none, the backoff, `Sets` on the
 * application's event dispatcher, and the command, the handler and the schedule when their components are installed.
 *
 * @phpstan-type SetsConfig array{definition_store: ?string, completion_store: ?string, clock: ?string, backoff: array{first_delay: int, longest_delay: int, report_after: int}, schedule: array{enabled: bool, frequency: string, limit: int, lock: ?string}}
 */
final class SetsServices
{
    private function __construct()
    {
    }

    /**
     * @param SetsConfig $config
     */
    public static function register(ContainerBuilder $builder, array $config, bool $console, bool $messenger, bool $scheduler): void
    {
        $definitions = $config['definition_store'] ?? self::inMemory($builder, InMemoryDefinitionStore::class);
        $completions = $config['completion_store'] ?? self::inMemory($builder, InMemoryCompletionStore::class);

        $builder->register(DeliveryBackoff::class, DeliveryBackoff::class)
            ->setArguments([$config['backoff']['first_delay'], $config['backoff']['longest_delay'], $config['backoff']['report_after']]);

        $builder->register(Sets::class, Sets::class)
            ->setArguments([
                new Reference($definitions),
                new Reference($completions),
                null === $config['clock'] ? null : new Reference($config['clock']),
                new Reference('event_dispatcher', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                new Reference(DeliveryBackoff::class),
            ])
            ->setPublic(true);

        if ($console) {
            $builder->register(DeliverCommand::class, DeliverCommand::class)
                ->setArguments([new Reference(Sets::class)])
                ->setAutoconfigured(true);
        }

        if ($messenger) {
            $builder->register(DeliverPendingCompletionsHandler::class, DeliverPendingCompletionsHandler::class)
                ->setArguments([new Reference(Sets::class)])
                ->setAutoconfigured(true);
        }

        if ($scheduler && $config['schedule']['enabled']) {
            $builder->register(DeliverySchedule::class, DeliverySchedule::class)
                ->setArguments([
                    $config['schedule']['frequency'],
                    $config['schedule']['limit'],
                    null === $config['schedule']['lock'] ? null : new Reference($config['schedule']['lock']),
                ])
                ->setAutoconfigured(true);
        }
    }

    /**
     * Registers a store in memory; returns its service id.
     *
     * @param class-string $class
     */
    private static function inMemory(ContainerBuilder $builder, string $class): string
    {
        $builder->register($class, $class);

        return $class;
    }
}
