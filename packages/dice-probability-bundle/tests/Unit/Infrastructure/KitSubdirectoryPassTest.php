<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tilttek\DiceProbabilityBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\KitComponents;

#[CoversClass(KitSubdirectoryPass::class)]
final class KitSubdirectoryPassTest extends TestCase
{
    public function testTheSubdirectoryOfTheToolkitIsTaken(): void
    {
        $container = $this->container(null);
        $container->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, 'ui');

        new KitSubdirectoryPass()->process($container);

        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testTheBundleSubdirectoryWins(): void
    {
        $container = $this->container('kit');
        $container->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, 'ui');

        new KitSubdirectoryPass()->process($container);

        self::assertSame('kit', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testWithoutTheToolkitTheComponentsAreAtTheRoot(): void
    {
        $container = $this->container(null);

        new KitSubdirectoryPass()->process($container);

        self::assertSame('', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testAToolkitWithoutSubdirectoryPutsThemAtTheRoot(): void
    {
        $container = $this->container(null);
        $container->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, null);

        new KitSubdirectoryPass()->process($container);

        self::assertSame('', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testWithoutTwigThereIsNothingToName(): void
    {
        $container = new ContainerBuilder();

        new KitSubdirectoryPass()->process($container);

        self::assertFalse($container->hasDefinition(KitComponents::class));
        self::assertSame('.ux_toolkit.components.subdirectory', KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY);
    }

    private function container(?string $subdirectory): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$subdirectory]);

        return $container;
    }
}
