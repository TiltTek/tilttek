<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenPublisher;
use Tilttek\EscapeRoomBundle\Application\EscapeRoomTopics;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Tilttek\EscapeRoomBundle\Infrastructure\Mercure\MercureScreenPublisher;
use Tilttek\EscapeRoomBundle\Infrastructure\Mercure\ScreenSubscriptions;

/**
 * Wires the Mercure hub the screens are told of each move through: the hub the configuration names, or — when it names
 * none — the application's default hub, found under `HubInterface` once MercureBundle registered it. Without a hub, the
 * screens read the game again at intervals.
 */
final readonly class MercureHubPass implements CompilerPassInterface
{
    /** The service of the hub, or null when Mercure is disabled. */
    public const string HUB = '.tilttek_escape_room.mercure.hub';

    /** Whether the configuration named the hub: a hub named and missing is an error, the default one missing is not. */
    public const string NAMED = '.tilttek_escape_room.mercure.named';

    public const string DEFAULT_HUB = HubInterface::class;

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $hub = $container->hasParameter(self::HUB) ? $container->getParameter(self::HUB) : null;

        if (!\is_string($hub) || !$container->hasDefinition(GameDesk::class)) {
            return;
        }

        if (true !== $container->getParameter(self::NAMED) && !$container->has($hub)) {
            return;
        }

        $reference = new Reference($hub);
        $container->register(MercureScreenPublisher::class, MercureScreenPublisher::class)
            ->setArguments([$reference, new Reference(EscapeRoomTopics::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $container->setAlias(ScreenPublisher::class, MercureScreenPublisher::class);
        $container->getDefinition(GameDesk::class)->replaceArgument(7, new Reference(ScreenPublisher::class));
        $container->getDefinition(ScreenSubscriptions::class)
            ->replaceArgument(1, $reference)
            ->replaceArgument(2, class_exists(Authorization::class) ? new Reference(Authorization::class, ContainerInterface::NULL_ON_INVALID_REFERENCE) : null);
    }
}
