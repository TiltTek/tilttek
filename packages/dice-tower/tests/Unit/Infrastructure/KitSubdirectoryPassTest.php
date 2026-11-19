<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tilttek\DiceTower\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\DiceTower\Infrastructure\Twig\KitComponents;

#[CoversClass(KitSubdirectoryPass::class)]
final class KitSubdirectoryPassTest extends TestCase
{
    public function testTheSubdirectoryOfTheToolkitUnlessTheBundleSaysIt(): void
    {
        self::assertSame('ui', $this->process(null, 'ui'));
        self::assertSame('', $this->process(null, null));
        self::assertSame('', $this->process(null, false));
        self::assertSame('kit', $this->process('kit', 'ui'));

        $container = new ContainerBuilder();
        new KitSubdirectoryPass()->process($container);
        self::assertFalse($container->hasDefinition(KitComponents::class));
    }

    private function process(?string $configured, string|false|null $toolkit): mixed
    {
        $container = new ContainerBuilder();
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$configured]);

        if (false !== $toolkit) {
            $container->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, $toolkit);
        }

        new KitSubdirectoryPass()->process($container);

        return $container->getDefinition(KitComponents::class)->getArgument(0);
    }
}
