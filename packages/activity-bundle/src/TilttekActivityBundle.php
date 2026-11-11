<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle;

use DateInterval;
use Exception;
use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Activity\Journal;
use Tilttek\ActivityBundle\Infrastructure\DependencyInjection\ActivityServices;
use Tilttek\ActivityBundle\Infrastructure\Privacy\ActivityPrivacy;

/**
 * The journal of the members' activity for a Symfony application, on `tilttek/activity`:
 *
 * ```yaml
 * tilttek_activity:
 *     verbs: ['news.commented', 'game.*', 'order.paid']
 * ```
 *
 * The configuration says where the journal is kept — a Doctrine DBAL connection, or memory for tests —, which verbs
 * the application declares, how many entries a projection reads at a time, how the projections follow the journal —
 * in the request, through Symfony Messenger, or by a command only —, the window a gesture's moment must fall in, and
 * under which kind a data subject is a member.
 * Projections and observers are found by the interfaces they implement.
 */
final class TilttekActivityBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_activity';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the journal and the checkpoints are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the journal.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->arrayNode('verbs')
                    ->info('The verbs the application records, as patterns such as "game.played" or "game.*"; empty lets every verb through.')
                    ->scalarPrototype()->end()
                ->end()
                ->integerNode('batch_size')
                    ->info('How many entries a projection reads at a time, and the export of a member.')
                    ->min(1)
                    ->defaultValue(500)
                ->end()
                ->enumNode('projections')
                    ->info('How the projections follow the journal: "live", each new entry catches up those that read it in the same request; "messenger", it sends them a message on the bus; "manual", only the catch-up command does.')
                    ->values([ActivityServices::LIVE, ActivityServices::MESSENGER, ActivityServices::MANUAL])
                    ->defaultValue(ActivityServices::LIVE)
                ->end()
                ->scalarNode('projection_bus')
                    ->info("The Messenger bus the projections' messages are sent on, when they follow the journal through Messenger.")
                    ->cannotBeEmpty()
                    ->defaultValue('messenger.bus.default')
                ->end()
                ->arrayNode('dates')
                    ->info("The window a gesture's moment must fall in, around the clock, as ISO 8601 durations.")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('past')
                            ->info('How long before now a gesture may be dated; null for no bound, to import a history.')
                            ->defaultValue(Journal::DEFAULT_PAST)
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => null !== $value && !self::isDuration($value))
                                ->thenInvalid('%s is not an ISO 8601 duration, such as "P30D".')
                            ->end()
                        ->end()
                        ->scalarNode('ahead')
                            ->info('How long after now a gesture may be dated.')
                            ->defaultValue(Journal::DEFAULT_AHEAD)
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => !self::isDuration($value))
                                ->thenInvalid('%s is not an ISO 8601 duration, such as "PT5M".')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('privacy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('member_kind')
                            ->info('The kind of the member reference a data subject of phil/privacy-bundle stands for.')
                            ->cannotBeEmpty()
                            ->defaultValue('member')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, connection: string, verbs: list<string>, batch_size: int, projections: string, projection_bus: string, dates: array{past: ?string, ahead: string}, privacy: array{member_kind: string}} $config */
        ActivityServices::register($builder, $config);

        // The privacy module, when `phil/privacy-bundle` is installed: a member's activity leaves with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(ActivityPrivacy::class, ActivityPrivacy::class)
                ->setArguments([new Reference(ActivityServices::JOURNAL), $config['privacy']['member_kind'], $config['batch_size']])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }

    private static function isDuration(mixed $value): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        try {
            new DateInterval($value);
        } catch (Exception) {
            return false;
        }

        return true;
    }
}
