<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle;

use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tilttek\TableSafety\Feedback\FeedbackSummary;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\SessionZero\Templates;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafetyBundle\Application\SignalTopics;
use Tilttek\TableSafetyBundle\Infrastructure\Console\PurgeSignalsCommand;
use Tilttek\TableSafetyBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\TableSafetyBundle\Infrastructure\DependencyInjection\TableSafetyServices;

/**
 * The safety tools of the tables in a Symfony application, on `tilttek/table-safety`:
 *
 * ```yaml
 * tilttek_table_safety:
 *     table_access: App\Table\TableSafetyAccess
 *     layout: base.html.twig
 *     defaults: { signals_seen_by: game-master, template: one-shot }
 *     mercure: { hub: mercure.hub.default }
 * ```
 *
 * The configuration says where the answers and the signals are kept — a Doctrine DBAL connection, or memory for
 * tests —, which service tells who takes part in a table and who runs it, the layout the screens extend, what a table
 * that chose nothing uses, the Mercure hub the signals are pushed to, how long a signal is kept, where the recipes of
 * the UX kit are, and the kind of reference a data subject of `phil/privacy-bundle` stands for.
 *
 * @phpstan-import-type TableSafetyConfig from TableSafetyServices
 */
final class TilttekTableSafetyBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_table_safety';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the settings, answers and signals are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the stores.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('table_access')
                    ->info('The service implementing TableAccess, which says who takes part in a table and who runs it; without it, every screen is refused.')
                    ->defaultNull()
                ->end()
                ->scalarNode('layout')
                    ->info('The template the screens extend; they fill its block "body".')
                    ->cannotBeEmpty()
                    ->defaultValue('base.html.twig')
                ->end()
                ->arrayNode('defaults')
                    ->info('What a table that chose nothing uses.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('tools')
                            ->info('The tools, by name; all of them by default.')
                            ->scalarPrototype()->end()
                            ->defaultValue(array_map(static fn (SafetyTool $tool): string => $tool->value, SafetyTool::cases()))
                            ->validate()
                                ->ifTrue(static fn (array $tools): bool => array_any($tools, static fn (mixed $tool): bool => !\is_string($tool) || !SafetyTool::tryFrom($tool) instanceof SafetyTool))
                                ->thenInvalid('A tool is one of "'.implode('", "', array_map(static fn (SafetyTool $tool): string => $tool->value, SafetyTool::cases())).'".')
                            ->end()
                        ->end()
                        ->booleanNode('named_signals')
                            ->info('Whether a signal says who raised it; anonymous by default.')
                            ->defaultFalse()
                        ->end()
                        ->enumNode('signals_seen_by')
                            ->info('Who sees the X-card and Script Change: the whole "table", or the "game-master" alone.')
                            ->values(array_map(static fn (Audience $audience): string => $audience->value, Audience::cases()))
                            ->defaultValue(Audience::Table->value)
                        ->end()
                        ->enumNode('template')
                            ->info('The built-in template the session zero starts from.')
                            ->values(Templates::keys())
                            ->defaultValue(Templates::DEFAULT)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('mercure')
                    ->info('The Mercure hub each signal is pushed to, as it is raised and acknowledged; without a hub, the screens show the signals when they load.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('hub')->info('The service of the hub, such as "mercure.hub.default".')->defaultNull()->end()
                        ->scalarNode('hub_name')->info('The name of the hub whose authorization cookie the viewers are given.')->defaultNull()->end()
                        ->scalarNode('topic_prefix')->info("The prefix of the tables' topics.")->cannotBeEmpty()->defaultValue(SignalTopics::PREFIX)->end()
                    ->end()
                ->end()
                ->scalarNode('retention')
                    ->info('How long a signal is kept before the purge command deletes it, such as "30 days".')
                    ->defaultValue('30 days')
                    ->validate()
                        ->ifTrue(static fn (mixed $retention): bool => !\is_string($retention) || 1 !== preg_match(PurgeSignalsCommand::RETENTION, $retention))
                        ->thenInvalid('The retention is a number and a unit, such as "30 days".')
                    ->end()
                ->end()
                ->integerNode('feedback_threshold')
                    ->info("How many participants' stars and wishes a summary waits for, and gathers in whole groups of.")
                    ->min(1)
                    ->defaultValue(FeedbackSummary::DEFAULT_THRESHOLD)
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')
                    ->defaultNull()
                ->end()
                ->arrayNode('privacy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('participant_kind')
                            ->info('The kind of the participant reference a data subject of phil/privacy-bundle stands for.')
                            ->cannotBeEmpty()
                            ->defaultValue('member')
                        ->end()
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
        /** @var TableSafetyConfig $config */
        TableSafetyServices::register($builder, $config);
    }
}
