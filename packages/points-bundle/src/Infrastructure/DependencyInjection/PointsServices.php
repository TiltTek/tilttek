<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Points\Currencies;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Ledger\Ledger;
use Tilttek\Points\Points;
use Tilttek\Points\PointsProjection;
use Tilttek\Points\Rule\Rules;
use Tilttek\PointsBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\PointsBundle\Infrastructure\Console\HistoryCommand;
use Tilttek\PointsBundle\Infrastructure\Doctrine\DbalLedger;
use Tilttek\PointsBundle\Infrastructure\Doctrine\PointsSchemaListener;

/**
 * The bundle's services: the ledger on Doctrine DBAL or in memory, the currencies and the rules from the
 * configuration — checked when the container is compiled —, `Points`, the projection tagged for
 * `tilttek/activity-bundle`, and — when their components are installed — the schema listener and the commands.
 */
final class PointsServices
{
    /**
     * The tag under which `tilttek/activity-bundle` finds its projections.
     */
    public const string PROJECTION_TAG = 'tilttek_activity.projection';

    /**
     * The name the projection's checkpoint is kept under, unless the configuration names another.
     */
    public const string DEFAULT_PROJECTION = PointsProjection::NAME;

    /**
     * The service of the ledger, for the privacy module the bundle class registers.
     */
    public const string LEDGER = Ledger::class;

    private function __construct()
    {
    }

    /**
     * @param array{store: string, connection: string, currencies: array<string, array{lifetime: ?string}>, default_currency: ?string, timezone: string, rules: array<string, array{verbs: list<string>, points: int, currency: ?string, per_quantity: bool, cap?: array{amount: int, period: string}, context: array<string, bool|int|string>}>, projection: string, batch_size: int, privacy: array{member_kind: string}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $lifetimes = array_map(static fn (array $currency): ?string => $currency['lifetime'], $config['currencies']);
        $currencies = Currencies::fromArray($lifetimes, $config['default_currency']);

        foreach (Rules::fromArray($config['rules'], $config['timezone'])->all() as $rule) {
            $currencies->get($rule->currency);
        }

        if ('memory' === $config['store']) {
            $container->register(InMemoryLedger::class, InMemoryLedger::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(Ledger::class, InMemoryLedger::class)->setPublic(true);
        } else {
            $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
            $container->register(DbalLedger::class, DbalLedger::class)->setArguments([$connection]);
            $container->setAlias(Ledger::class, DbalLedger::class)->setPublic(true);

            if (class_exists(GenerateSchemaEventArgs::class)) {
                $container->register(PointsSchemaListener::class, PointsSchemaListener::class)
                    ->setArguments([$connection])
                    ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
            }
        }

        $container->register(Currencies::class, Currencies::class)
            ->setFactory([Currencies::class, 'fromArray'])
            ->setArguments([$lifetimes, $config['default_currency']]);
        $container->register(Rules::class, Rules::class)
            ->setFactory([Rules::class, 'fromArray'])
            ->setArguments([$config['rules'], $config['timezone']]);
        $container->register(Points::class, Points::class)
            ->setArguments([new Reference(Ledger::class), new Reference('clock'), new Reference(Currencies::class), $config['batch_size']])
            ->setPublic(true);
        $container->register(PointsProjection::class, PointsProjection::class)
            ->setArguments([new Reference(Ledger::class), new Reference(Currencies::class), new Reference(Rules::class), new Reference('clock'), $config['projection']])
            ->addTag(self::PROJECTION_TAG)
            ->setPublic(true);

        if (class_exists(Command::class)) {
            foreach ([ExpireCommand::class, HistoryCommand::class] as $command) {
                $container->register($command, $command)
                    ->setArguments([new Reference(Points::class)])
                    ->addTag('console.command');
            }
        }
    }
}
