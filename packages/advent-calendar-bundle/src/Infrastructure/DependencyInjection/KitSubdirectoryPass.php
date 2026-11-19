<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\DependencyInjection;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tilttek\AdventCalendarBundle\Infrastructure\Twig\KitComponents;

/**
 * Names the components of the UX kit after the components subdirectory of the Symfony UX Toolkit, unless the bundle's
 * own `kit_subdirectory` says it.
 */
final readonly class KitSubdirectoryPass implements CompilerPassInterface
{
    public const string TOOLKIT_SUBDIRECTORY = '.ux_toolkit.components.subdirectory';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(KitComponents::class)) {
            return;
        }

        $definition = $container->getDefinition(KitComponents::class);

        if (null !== $definition->getArgument(0)) {
            return;
        }

        $subdirectory = $container->hasParameter(self::TOOLKIT_SUBDIRECTORY) ? $container->getParameter(self::TOOLKIT_SUBDIRECTORY) : null;
        $definition->setArgument(0, \is_string($subdirectory) ? $subdirectory : '');
    }
}
