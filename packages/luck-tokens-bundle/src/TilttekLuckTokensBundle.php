<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;
use Tilttek\LuckTokensBundle\Application\LockScope;
use Tilttek\LuckTokensBundle\Infrastructure\DependencyInjection\LuckTokensServices;
use Tilttek\LuckTokensBundle\Infrastructure\Privacy\LuckTokensPrivacy;

/**
 * The luck, inspiration and fate tokens of `tilttek/luck-tokens` for a Symfony application:
 *
 * ```yaml
 * tilttek_luck_tokens:
 *     tables:
 *         'table:12':
 *             game_masters: ['member:7']
 *             kinds:
 *                 - { key: inspiration, name: Inspiration, cap: 1, transferable: true }
 *     lock:
 *         enabled: true
 * ```
 *
 * The configuration says where the ledgers are kept — a Doctrine DBAL connection, or memory for tests —, where the
 * rules of the tables come from — the configuration, the database or a service of the application —, which clock dates
 * the entries, who controls the tokens of whom, whether a Symfony lock serializes the operations of a holder or a
 * table, and under which kind a data subject of `phil/privacy-bundle` is a member.
 *
 * @phpstan-import-type Config from LuckTokensServices
 */
final class TilttekLuckTokensBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_luck_tokens';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the ledgers are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the ledgers and of the rules kept in the database.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->enumNode('rules')
                    ->info('Where the rules of the tables come from: "configuration", under "tables"; "doctrine", a table the application edits; or "service", "rules_service".')
                    ->values(['configuration', 'doctrine', 'service'])
                    ->defaultValue('configuration')
                ->end()
                ->scalarNode('rules_service')
                    ->info('The service that gives the rules, implementing Tilttek\LuckTokens\Contract\TokenRules; with "rules: service".')
                    ->defaultNull()
                ->end()
                ->arrayNode('tables')
                    ->info('The rules of each table, by its reference: its game masters and the kinds of tokens it hands out.')
                    ->useAttributeAsKey('table')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('game_masters')->scalarPrototype()->end()->end()
                            ->arrayNode('kinds')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('key')->isRequired()->end()
                                        ->scalarNode('name')->isRequired()->end()
                                        ->integerNode('cap')->isRequired()->end()
                                        ->enumNode('scope')->values(['character', 'player'])->end()
                                        ->enumNode('refresh')->values(['never', 'each-session'])->end()
                                        ->integerNode('refresh_to')->end()
                                        ->integerNode('carry_over')->end()
                                        ->enumNode('grants')->values(['game-masters', 'anyone'])->end()
                                        ->booleanNode('transferable')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('clock')
                    ->info('The service of the clock that dates the entries, implementing Psr\Clock\ClockInterface.')
                    ->cannotBeEmpty()
                    ->defaultValue('clock')
                ->end()
                ->integerNode('max_entries')
                    ->info('The most entries the ledger of a table takes.')
                    ->min(1)
                    ->defaultValue(LuckTokens::MAX_ENTRIES)
                ->end()
                ->enumNode('holder_access')
                    ->info('Who spends and passes the tokens of whom, besides the game masters: "self", each holder their own; "security", the security voters; or "service", "holder_access_service".')
                    ->values(['self', 'security', 'service'])
                    ->defaultValue('self')
                ->end()
                ->scalarNode('holder_access_service')
                    ->info('The service that tells who controls whom, implementing Tilttek\LuckTokens\Contract\HolderAccess; with "holder_access: service".')
                    ->defaultNull()
                ->end()
                ->arrayNode('lock')
                    ->info('A Symfony lock around each operation, so that the operations of a holder, or of a table, run one after the other.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('factory')
                            ->info('The service of the Symfony\Component\Lock\LockFactory.')
                            ->cannotBeEmpty()
                            ->defaultValue('lock.factory')
                        ->end()
                        ->enumNode('scope')
                            ->info('What one lock covers: "holder" or "table".')
                            ->values(array_map(static fn (LockScope $scope): string => $scope->value, LockScope::cases()))
                            ->defaultValue(LockScope::Holder->value)
                        ->end()
                        ->floatNode('ttl')
                            ->info('How long a lock lives at most, in seconds, should its process die holding it.')
                            ->min(1.0)
                            ->defaultValue(LockedLuckTokens::DEFAULT_TTL)
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
        /** @var Config $config */
        LuckTokensServices::register($builder, $config);

        // The erasure module, when `phil/privacy-bundle` is installed: a member's tokens leave with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(LuckTokensPrivacy::class, LuckTokensPrivacy::class)
                ->setArguments(LuckTokensServices::privacyArguments($config))
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }
}
