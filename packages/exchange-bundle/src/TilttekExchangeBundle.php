<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tilttek\ExchangeBundle\Infrastructure\DependencyInjection\ExchangeServices;
use Tilttek\ExchangeBundle\Infrastructure\Privacy\ExchangePrivacy;

/**
 * Exchanges for a Symfony application, on `tilttek/exchange` and the points of `tilttek/points-bundle`:
 *
 * ```yaml
 * tilttek_exchange:
 *     rewards:
 *         discount-10: { price: 100 }
 *         seat: { price: 250, stock: 30, per_member: 1, requires: ['badge:helper'] }
 *     transfers:
 *         enabled: true
 *         allowance: { limit: 500, period: week }
 * ```
 *
 * The configuration says where the exchanges are kept — a Doctrine DBAL connection, or memory for tests —, where the
 * catalogue comes from, how long a reservation waits for its confirmation, which services check the rewards'
 * conditions and tell when a member joined, whether members may give points and trade items and within which
 * guards, which service keeps the items, and under which kind a data subject is a member.
 */
final class TilttekExchangeBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_exchange';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $children
            ->enumNode('store')
                ->info('Where the exchanges are kept: "doctrine", or "memory" for tests.')
                ->values(['doctrine', 'memory'])
                ->defaultValue('doctrine')
            ->end()
            ->scalarNode('connection')
                ->info('The Doctrine DBAL connection of the exchanges; the ledger of points on the same one makes an exchange one transaction.')
                ->cannotBeEmpty()
                ->defaultValue('default')
            ->end()
            ->enumNode('catalog')
                ->info('Where the rewards come from: "configuration", below, or "doctrine", a table the application edits.')
                ->values(['configuration', 'doctrine'])
                ->defaultValue('configuration')
            ->end()
            ->arrayNode('rewards')
                ->info('The rewards by code: the price in points, the currency, the stock, the period of availability, the limit per member and the conditions.')
                ->useAttributeAsKey('code')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->children()
                        ->integerNode('price')->isRequired()->min(1)->end()
                        ->scalarNode('currency')->defaultNull()->end()
                        ->integerNode('stock')->defaultNull()->min(0)->end()
                        ->scalarNode('available_from')->defaultNull()->end()
                        ->scalarNode('available_until')->defaultNull()->end()
                        ->integerNode('per_member')->defaultNull()->min(1)->end()
                        ->arrayNode('requires')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
            ->end()
            ->scalarNode('reservation_lifetime')
                ->info('How long a reservation waits for its confirmation, as an ISO 8601 duration.')
                ->cannotBeEmpty()
                ->defaultValue(ExchangeServices::DEFAULT_RESERVATION_LIFETIME)
            ->end()
            ->scalarNode('requirements')
                ->info('The service that checks the rewards\' conditions, implementing Tilttek\Exchange\Reward\Requirements; without it, no condition is met.')
                ->defaultNull()
            ->end()
            ->scalarNode('member_since')
                ->info('The service that tells when a member joined, implementing Tilttek\Exchange\MemberSince; needed by a delay for new accounts.')
                ->defaultNull()
            ->end()
            ->scalarNode('timezone')
                ->info('The timezone the periods of the allowances are counted in.')
                ->cannotBeEmpty()
                ->defaultValue('UTC')
            ->end();

        $children->append($this->exchanges('transfers', 'Points members give each other.', 'points a member gives'));
        $children->append($this->exchanges('trades', 'Items members trade with each other.', 'offers a member makes'));
        $children
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
        /** @var array{store: string, connection: string, catalog: string, rewards: array<string, array{price: int, currency?: ?string, stock?: ?int, available_from?: ?string, available_until?: ?string, per_member?: ?int, requires?: list<string>}>, reservation_lifetime: string, requirements: ?string, member_since: ?string, timezone: string, transfers: array{enabled: bool, allowance?: array{limit: int, period: string}, new_member_delay: ?string}, trades: array{enabled: bool, inventory: ?string, offer_lifetime: string, allowance?: array{limit: int, period: string}, new_member_delay: ?string}, privacy: array{member_kind: string}} $config */
        ExchangeServices::register($builder, $config);

        // The privacy module, when `phil/privacy-bundle` is installed: a member's exchanges leave with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(ExchangePrivacy::class, ExchangePrivacy::class)
                ->setArguments(ExchangeServices::privacyArguments($config))
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }

    /**
     * The options of transfers or trades: whether the application enables them, the allowance per period and the delay
     * for new accounts; for trades, the inventory and the lifetime of an offer.
     */
    private function exchanges(string $name, string $info, string $what): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);
        $children = $node->info($info)->addDefaultsIfNotSet()->children();
        $children->booleanNode('enabled')->defaultFalse()->end();

        if ('trades' === $name) {
            $children
                ->scalarNode('inventory')
                    ->info('The service that keeps the members\' items, implementing Tilttek\Exchange\Trade\Inventory; needed when trades are enabled.')
                    ->defaultNull()
                ->end()
                ->scalarNode('offer_lifetime')
                    ->info('How long an offer waits for its answer, as an ISO 8601 duration.')
                    ->cannotBeEmpty()
                    ->defaultValue(ExchangeServices::DEFAULT_OFFER_LIFETIME)
                ->end();
        }

        $children
            ->arrayNode('allowance')
                ->info(\sprintf('How many %s per period, at most.', $what))
                ->children()
                    ->integerNode('limit')->isRequired()->min(1)->end()
                    ->enumNode('period')->isRequired()->values(['day', 'week', 'month', 'year', 'ever'])->end()
                ->end()
            ->end()
            ->scalarNode('new_member_delay')
                ->info('How long an account waits before it exchanges with others, as an ISO 8601 duration; none when null.')
                ->defaultNull()
            ->end();

        return $node;
    }
}
