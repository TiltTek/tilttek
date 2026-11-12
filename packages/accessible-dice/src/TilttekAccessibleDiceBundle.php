<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice;

use LogicException;
use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tilttek\AccessibleDice\Application\AccessibleDice;
use Tilttek\AccessibleDice\Application\Politeness;
use Tilttek\AccessibleDice\Infrastructure\DependencyInjection\AccessibleDiceServices;

/**
 * An accessible dice interface in a Symfony application:
 *
 * ```yaml
 * tilttek_accessible_dice:
 *     sides: [4, 6, 8, 10, 12, 20, 100]
 *     custom_dice: ~
 *     politeness: polite
 * ```
 *
 * The custom dice, their faces and the limits are those of `TilttekDiceBundle`: register it too. The configuration
 * sets the standard dice offered, the custom dice offered — every one when null —, how a result is announced, and the
 * translation domain of the labels of the faces.
 */
final class TilttekAccessibleDiceBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_accessible_dice';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('sides')
                    ->info('The standard dice offered, by their number of sides.')
                    ->integerPrototype()->min(2)->end()
                    ->defaultValue(AccessibleDice::SIDES)
                ->end()
                ->arrayNode('custom_dice')
                    ->info('The custom dice of TilttekDiceBundle offered, by name; every one when null.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultNull()
                ->end()
                ->enumNode('politeness')
                    ->info('How a screen reader announces a result: "polite", when it has finished what it is saying, or "assertive", at once.')
                    ->values(array_map(static fn (Politeness $politeness): string => $politeness->value, Politeness::cases()))
                    ->defaultValue(Politeness::Polite->value)
                ->end()
                ->scalarNode('dice_translation_domain')
                    ->info('The translation domain of the labels of the faces: that of TilttekDiceBundle.')
                    ->cannotBeEmpty()
                    ->defaultValue('tilttek_dice')
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('tilttek_dice')) {
            throw new LogicException('TilttekAccessibleDiceBundle reads the custom dice and the limits of TilttekDiceBundle, from tilttek/dice-bundle: register it too.');
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{sides: list<int>, custom_dice: ?list<string>, politeness: string, dice_translation_domain: string} $config */
        AccessibleDiceServices::register($builder, $config);
    }
}
