<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle;

use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tilttek\LootTablesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\LootTablesBundle\Infrastructure\DependencyInjection\LootServices;

/**
 * Loot tables for a Symfony application and its commerce orders, on `tilttek/loot-tables`:
 *
 * ```yaml
 * tilttek_loot_tables:
 *     tables:
 *         headgear:
 *             name: Headgear
 *             entries:
 *                 - { key: helmet, name: Helmet, image: /loot/helmet.png, slot: head }
 *                 - { key: hood, name: Hood, image: /loot/hood.png, slot: head }
 *     boxes:
 *         starter: { name: Starter box, tables: [headgear, headgear] }
 * ```
 *
 * The configuration declares the tables and the boxes, whether a draw must be committed before the payment, whether
 * the draws are committed when the order is placed, where the draws are kept, and where the recipes of the UX kit are.
 * The table of the draws is mapped for Doctrine.
 */
final class TilttekLootTablesBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_loot_tables';

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
                ->arrayNode('tables')
                    ->info('The tables, by key: a name and one entry per side of the die — a key, a name, and an image and a slot.')
                    ->useAttributeAsKey('key')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('entries')
                                ->isRequired()
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('key')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('image')->defaultNull()->end()
                                        ->scalarNode('slot')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('boxes')
                    ->info('The boxes, by key: a name and the keys of the tables a product rolls, in order. An option names one as its drawer, "loot-box:<key>".')
                    ->useAttributeAsKey('key')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('tables')
                                ->isRequired()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('require_commitment')
                    ->info('Whether a draw is refused unless it was committed when the order was placed; off, it is committed and revealed at once.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('commit_on_place')
                    ->info('Whether the draws of an order are committed as soon as the order workflow of the commerce core places it; off, the application calls LootCommitments::commit().')
                    ->defaultTrue()
                ->end()
                ->enumNode('store')
                    ->info('Where the draws are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the draws.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the UX kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['TilttekLootTablesBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Tilttek\LootTablesBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{tables: array<string, mixed>, boxes: array<string, mixed>, require_commitment: bool, commit_on_place: bool, store: string, entity_manager: string, kit_subdirectory: ?string} $config */
        LootServices::register($builder, $config);
    }
}
