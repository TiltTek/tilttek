<?php

declare(strict_types=1);

namespace Tilttek\DiceTower;

use LogicException;
use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\HubInterface;
use Tilttek\DiceTower\Application\DiceTower;
use Tilttek\DiceTower\Application\TowerTopics;
use Tilttek\DiceTower\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\DiceTower\Infrastructure\DependencyInjection\TowerServices;

/**
 * The shared dice tower of each table, in a Symfony application:
 *
 * ```yaml
 * tilttek_dice_tower:
 *     table_access: App\Dice\TableMembers
 *     hub: Symfony\Component\Mercure\HubInterface
 *     history: 20
 * ```
 *
 * The rolls are those of the journal of `TilttekDiceBundle`: register it too. The configuration names the service that
 * says who may watch a table, the Mercure hub each new entry is pushed to and its topics, the message bus the relays
 * are reached through, how many entries a viewer reads at once, where the log of the towers is kept, and where the
 * recipes of the UX kit the overlay draws with are installed. The table of the log is mapped for Doctrine.
 */
final class TilttekDiceTowerBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_dice_tower';

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
                ->integerNode('history')
                    ->info('How many entries a viewer reads at once: the last ones when the overlay is drawn, and those missed when the connection comes back.')
                    ->min(1)
                    ->max(DiceTower::MAX_HISTORY)
                    ->defaultValue(DiceTower::HISTORY)
                ->end()
                ->scalarNode('topic_prefix')
                    ->info("What comes before a table's reference in the topic of its tower.")
                    ->cannotBeEmpty()
                    ->defaultValue(TowerTopics::PREFIX)
                ->end()
                ->scalarNode('table_access')
                    ->info('The service, a TableAccess, that says whether the current viewer may watch a table; nobody may without it.')
                    ->defaultNull()
                ->end()
                ->scalarNode('hub')
                    ->info('The service of the Mercure hub each new entry is pushed to; null, or a service that does not exist, pushes nothing.')
                    ->defaultValue(HubInterface::class)
                ->end()
                ->scalarNode('hub_name')
                    ->info('The name of the hub the authorization cookie is for, when the application has several.')
                    ->defaultNull()
                ->end()
                ->scalarNode('message_bus')
                    ->info('The message bus the relays are reached through; they are called at once when null.')
                    ->defaultNull()
                ->end()
                ->enumNode('store')
                    ->info('Where the log of the towers is kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the log.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the UX kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('tilttek_dice')) {
            throw new LogicException('TilttekDiceTowerBundle reads the rolls of the journal of TilttekDiceBundle, from tilttek/dice-bundle: register it too.');
        }

        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['TilttekDiceTower' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Tilttek\DiceTower\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{history: int, topic_prefix: string, table_access: ?string, hub: ?string, hub_name: ?string, message_bus: ?string, store: string, entity_manager: string, kit_subdirectory: ?string} $config */
        TowerServices::register($builder, $config);
    }
}
