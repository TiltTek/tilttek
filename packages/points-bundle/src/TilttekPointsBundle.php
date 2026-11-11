<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\PointsBundle\Infrastructure\DependencyInjection\PointsServices;
use Tilttek\PointsBundle\Infrastructure\Privacy\PointsPrivacy;

/**
 * Points for a Symfony application, on `tilttek/points`:
 *
 * ```yaml
 * tilttek_points:
 *     currencies:
 *         points: ~
 *         stars: P1Y
 *     rules:
 *         games: { verbs: ['game.played'], points: 10, cap: { amount: 50, period: week } }
 * ```
 *
 * The configuration says where the ledger is kept — a Doctrine DBAL connection, or memory for tests —, the currencies
 * and their lifetimes, the earning rules and the timezone their periods are counted in, the name of the projection
 * that `tilttek/activity-bundle` catches up, and under which kind a data subject is a member.
 */
final class TilttekPointsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_points';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the ledger is kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the ledger.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->arrayNode('currencies')
                    ->info('The currencies of points by code, each with the lifetime of its credits as an ISO 8601 duration, or null when they never expire.')
                    ->useAttributeAsKey('code')
                    ->requiresAtLeastOneElement()
                    ->defaultValue(['points' => ['lifetime' => null]])
                    ->arrayPrototype()
                        ->beforeNormalization()->ifString()->then(static fn (string $lifetime): array => ['lifetime' => $lifetime])->end()
                        ->children()
                            ->scalarNode('lifetime')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_currency')
                    ->info('The currency used where none is named; the first one when null.')
                    ->defaultNull()
                ->end()
                ->scalarNode('timezone')
                    ->info('The timezone the periods of the caps are counted in.')
                    ->cannotBeEmpty()
                    ->defaultValue('UTC')
                ->end()
                ->arrayNode('rules')
                    ->info('The earning rules by name: the verbs they read, the points per unit, the currency, the cap per member and period, the context the activity must hold.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('verbs')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->scalarPrototype()->end()
                            ->end()
                            ->integerNode('points')->isRequired()->min(1)->end()
                            ->scalarNode('currency')->defaultNull()->end()
                            ->booleanNode('per_quantity')
                                ->info("Whether the points are given per unit of the activity's quantity, or once per activity.")
                                ->defaultTrue()
                            ->end()
                            ->arrayNode('cap')
                                ->children()
                                    ->integerNode('amount')->isRequired()->min(1)->end()
                                    ->enumNode('period')->isRequired()->values(['day', 'week', 'month', 'year', 'ever'])->end()
                                ->end()
                            ->end()
                            ->arrayNode('context')
                                ->useAttributeAsKey('name')
                                ->scalarPrototype()->end()
                                ->validate()
                                    ->ifTrue(static fn (array $context): bool => array_any($context, static fn (mixed $value): bool => !\is_bool($value) && !\is_int($value) && !\is_string($value)))
                                    ->thenInvalid('A context condition is an integer, a boolean or a string.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('projection')
                    ->info('The name of the projection that turns the activity into points.')
                    ->cannotBeEmpty()
                    ->defaultValue(PointsServices::DEFAULT_PROJECTION)
                ->end()
                ->integerNode('batch_size')
                    ->info('How many movements the expiry and the export of a member read at a time.')
                    ->min(1)
                    ->defaultValue(500)
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
        /** @var array{store: string, connection: string, currencies: array<string, array{lifetime: ?string}>, default_currency: ?string, timezone: string, rules: array<string, array{verbs: list<string>, points: int, currency: ?string, per_quantity: bool, cap?: array{amount: int, period: string}, context: array<string, bool|int|string>}>, projection: string, batch_size: int, privacy: array{member_kind: string}} $config */
        PointsServices::register($builder, $config);

        // The privacy module, when `phil/privacy-bundle` is installed: a member's points leave with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(PointsPrivacy::class, PointsPrivacy::class)
                ->setArguments([new Reference(PointsServices::LEDGER), $config['privacy']['member_kind'], $config['batch_size']])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }
}
