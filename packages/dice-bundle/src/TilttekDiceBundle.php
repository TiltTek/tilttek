<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle;

use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tilttek\DiceBundle\Infrastructure\DependencyInjection\DiceServices;

/**
 * Dice for a Symfony application, on `tilttek/dice`:
 *
 * ```yaml
 * tilttek_dice:
 *     limits: { dice: 100, sides: 1000, explosions: 100, rerolls: 100 }
 *     custom_dice:
 *         coin:
 *             faces:
 *                 - { id: heads, label: 'Heads', value: 1, shape: circle }
 *                 - { id: tails, label: 'Tails', value: 0, shape: square }
 * ```
 *
 * The configuration sets the limits, declares the custom dice and the notations of more languages, names the service
 * that dresses a member's dice, the translation domain of the words and labels, and where the journal of replayable
 * rolls and the macros are kept. Their tables are mapped for Doctrine.
 */
final class TilttekDiceBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_dice';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('limits')
                    ->info('The bounds against abuse; an expression or a roll past one is refused.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('dice')->min(1)->defaultValue(100)->end()
                        ->integerNode('sides')->min(1)->defaultValue(1000)->end()
                        ->integerNode('explosions')->min(1)->defaultValue(100)->end()
                        ->integerNode('expressions')->min(1)->defaultValue(10)->end()
                        ->integerNode('length')->min(1)->defaultValue(256)->end()
                        ->integerNode('rerolls')->min(1)->defaultValue(100)->end()
                        ->integerNode('repetitions')->min(1)->defaultValue(100)->end()
                        ->integerNode('text')->min(1)->defaultValue(4096)->end()
                    ->end()
                ->end()
                ->arrayNode('custom_dice')
                    ->info('The dice of the application, by name: each face has an identifier and a label, and a value, a colour, a shape or an image.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('faces')
                                ->isRequired()
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('label')->info('A translation key or a text: what a screen reader says.')->isRequired()->cannotBeEmpty()->end()
                                        ->integerNode('value')->defaultNull()->end()
                                        ->scalarNode('color')->info('#rrggbb; a face with a colour also has a shape or an image.')->defaultNull()->end()
                                        ->enumNode('shape')->values([null, 'circle', 'square', 'triangle', 'diamond', 'pentagon', 'hexagon', 'star', 'cross', 'heart', 'crescent', 'blank'])->defaultNull()->end()
                                        ->scalarNode('image')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('notations')
                    ->info('The letters of more languages, by locale, added to the built-in German, Danish, Norwegian and Swedish.')
                    ->useAttributeAsKey('locale')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('die')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('advantage')->cannotBeEmpty()->defaultValue('adv')->end()
                            ->scalarNode('disadvantage')->cannotBeEmpty()->defaultValue('dis')->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('appearance')
                    ->info("The service, a DiceAppearance, that dresses a member's dice.")
                    ->defaultNull()
                ->end()
                ->scalarNode('translation_domain')
                    ->info('The translation domain of the words of a roll and of the labels of the faces.')
                    ->cannotBeEmpty()
                    ->defaultValue('tilttek_dice')
                ->end()
                ->enumNode('store')
                    ->info('Where the journal of replayable rolls and the macros are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the journal and the macros.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['TilttekDiceBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Tilttek\DiceBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{limits: array{dice: int, sides: int, explosions: int, expressions: int, length: int, rerolls: int, repetitions: int, text: int}, custom_dice: array<string, array{faces: list<array{id: string, label: string, value: ?int, color: ?string, shape: ?string, image: ?string}>}>, notations: array<string, array{die: string, advantage: string, disadvantage: string}>, appearance: ?string, translation_domain: string, store: string, entity_manager: string} $config */
        DiceServices::register($builder, $config);
    }
}
