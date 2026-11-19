<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Infrastructure\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\Authorization;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\DiceBundle\Application\DiceRolls;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Twig\DiceExtension;
use Tilttek\DiceTower\Application\Contract\TableAccess;
use Tilttek\DiceTower\Application\Contract\TowerPublisher;
use Tilttek\DiceTower\Application\Contract\TowerRelay;
use Tilttek\DiceTower\Application\DiceTower;
use Tilttek\DiceTower\Application\Message\RelayDrop;
use Tilttek\DiceTower\Application\NoTableAccess;
use Tilttek\DiceTower\Application\RelayDropHandler;
use Tilttek\DiceTower\Application\TowerRelays;
use Tilttek\DiceTower\Application\TowerTopics;
use Tilttek\DiceTower\Domain\Contract\TowerDropRepository;
use Tilttek\DiceTower\Infrastructure\Doctrine\DoctrineTowerDropRepository;
use Tilttek\DiceTower\Infrastructure\Http\TowerEntriesController;
use Tilttek\DiceTower\Infrastructure\Memory\InMemoryTowerDropRepository;
use Tilttek\DiceTower\Infrastructure\Mercure\MercureTowerPublisher;
use Tilttek\DiceTower\Infrastructure\Mercure\TowerSubscriptions;
use Tilttek\DiceTower\Infrastructure\Twig\DiceTowerExtension;
use Tilttek\DiceTower\Infrastructure\Twig\KitComponents;
use Tilttek\DiceTower\Infrastructure\Twig\TowerView;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the log of the towers, the topics, the publisher on the configured Mercure hub, the relays
 * tagged `tilttek_dice_tower.relay` — through the message bus when one is named —, `DiceTower` on the journal and the
 * `DiceRolls` of `TilttekDiceBundle`, the access to the tables, the subscriptions, the controller of the entries and,
 * with Twig, the overlay.
 */
final class TowerServices
{
    public const string RELAY_TAG = 'tilttek_dice_tower.relay';

    private function __construct()
    {
    }

    /**
     * @param array{history: int, topic_prefix: string, table_access: ?string, hub: ?string, hub_name: ?string, message_bus: ?string, store: string, entity_manager: string, kit_subdirectory: ?string} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        if ('memory' === $config['store']) {
            $container->register(InMemoryTowerDropRepository::class, InMemoryTowerDropRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(TowerDropRepository::class, InMemoryTowerDropRepository::class)->setPublic(true);
        } else {
            $container->register(DoctrineTowerDropRepository::class, DoctrineTowerDropRepository::class)
                ->setArguments([new Reference(\sprintf('doctrine.orm.%s_entity_manager', $config['entity_manager']))]);
            $container->setAlias(TowerDropRepository::class, DoctrineTowerDropRepository::class)->setPublic(true);
        }

        if (null === $config['table_access']) {
            $container->register(NoTableAccess::class, NoTableAccess::class);
            $container->setAlias(TableAccess::class, NoTableAccess::class);
        } else {
            $container->setAlias(TableAccess::class, $config['table_access']);
        }

        $hub = null === $config['hub'] ? null : new Reference($config['hub'], ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $container->register(TowerTopics::class, TowerTopics::class)->setArguments([$config['topic_prefix']])->setPublic(true);
        $container->register(MercureTowerPublisher::class, MercureTowerPublisher::class)->setArguments([$hub, new Reference(TowerTopics::class), $logger]);
        $container->setAlias(TowerPublisher::class, MercureTowerPublisher::class);

        $container->registerForAutoconfiguration(TowerRelay::class)->addTag(self::RELAY_TAG);
        $bus = null === $config['message_bus'] ? null : new Reference($config['message_bus']);
        $container->register(TowerRelays::class, TowerRelays::class)->setArguments([new TaggedIteratorArgument(self::RELAY_TAG), $bus, $logger]);

        $container->register(DiceTower::class, DiceTower::class)
            ->setArguments([
                new Reference(TowerDropRepository::class),
                new Reference(RecordedRollRepository::class),
                new Reference(DiceRolls::class),
                new Reference('clock'),
                new Reference(TowerPublisher::class),
                new Reference(TowerRelays::class),
                $config['history'],
            ])
            ->setPublic(true);

        if (null !== $config['message_bus']) {
            $container->register(RelayDropHandler::class, RelayDropHandler::class)
                ->setArguments([new Reference(DiceTower::class), new Reference(TowerRelays::class)])
                ->addTag('messenger.message_handler', ['handles' => RelayDrop::class, 'bus' => $config['message_bus']]);
        }

        $container->register(TowerSubscriptions::class, TowerSubscriptions::class)
            ->setArguments([
                new Reference(TableAccess::class),
                new Reference(TowerTopics::class),
                $hub,
                class_exists(Authorization::class) ? new Reference(Authorization::class, ContainerInterface::NULL_ON_INVALID_REFERENCE) : null,
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['hub_name'],
            ])
            ->setPublic(true);

        $container->register(TowerEntriesController::class, TowerEntriesController::class)
            ->setArguments([new Reference(DiceTower::class), new Reference(TableAccess::class)])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        if (class_exists(AsTwigFunction::class)) {
            $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
            $container->register(TowerView::class, TowerView::class)
                ->setArguments([new Reference(CustomDice::class), new Reference(DiceExtension::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
            $container->register(DiceTowerExtension::class, DiceTowerExtension::class)
                ->setArguments([
                    new Reference(DiceTower::class),
                    new Reference(TableAccess::class),
                    new Reference(TowerTopics::class),
                    new Reference(TowerSubscriptions::class),
                    new Reference(TowerView::class),
                    new Reference(KitComponents::class),
                    new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }
    }
}
