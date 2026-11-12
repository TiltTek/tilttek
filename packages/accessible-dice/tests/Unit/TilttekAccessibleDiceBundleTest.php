<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Tests\Unit;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\AccessibleDice\Application\AccessibleDice;
use Tilttek\AccessibleDice\Application\FaceDescriber;
use Tilttek\AccessibleDice\Application\Politeness;
use Tilttek\AccessibleDice\Application\RollAnnouncer;
use Tilttek\AccessibleDice\Application\Words;
use Tilttek\AccessibleDice\Infrastructure\DependencyInjection\AccessibleDiceServices;
use Tilttek\AccessibleDice\Infrastructure\Twig\AccessibleDiceExtension;
use Tilttek\AccessibleDice\TilttekAccessibleDiceBundle;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Limits;

#[CoversClass(TilttekAccessibleDiceBundle::class)]
#[CoversClass(AccessibleDiceServices::class)]
final class TilttekAccessibleDiceBundleTest extends TestCase
{
    public function testTheServicesByDefault(): void
    {
        $container = $this->load([]);

        self::assertSame('tilttek_accessible_dice', $this->extension()->getAlias());
        self::assertSame(['service_container', Words::class, FaceDescriber::class, RollAnnouncer::class, AccessibleDice::class, AccessibleDiceExtension::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'tilttek_dice'], $container->getDefinition(Words::class)->getArguments());
        self::assertEquals([new Reference(CustomDice::class), new Reference(Words::class)], $container->getDefinition(FaceDescriber::class)->getArguments());
        self::assertEquals([new Reference(Words::class)], $container->getDefinition(RollAnnouncer::class)->getArguments());
        self::assertTrue($container->getDefinition(FaceDescriber::class)->isPublic());
        self::assertTrue($container->getDefinition(RollAnnouncer::class)->isPublic());
        self::assertTrue($container->getDefinition(AccessibleDice::class)->isPublic());

        $arguments = $container->getDefinition(AccessibleDice::class)->getArguments();
        self::assertEquals([new Reference(CustomDice::class), new Reference(FaceDescriber::class), new Reference(Words::class), [4, 6, 8, 10, 12, 20, 100], null], \array_slice($arguments, 0, 5));
        self::assertInstanceOf(Definition::class, $arguments[5]);
        self::assertSame([AccessibleDiceServices::class, 'most'], $arguments[5]->getFactory());
        self::assertEquals([new Reference(Limits::class)], $arguments[5]->getArguments());

        self::assertSame(Politeness::Polite, $container->getDefinition(AccessibleDiceExtension::class)->getArgument(4));
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(AccessibleDiceExtension::class)->getTags());
        self::assertTrue($container->getDefinition(AccessibleDiceExtension::class)->isPublic());
        self::assertSame(42, AccessibleDiceServices::most(new Limits(dice: 42)));
    }

    public function testTheConfiguration(): void
    {
        $container = $this->load(['sides' => [6, 20], 'custom_dice' => ['coin'], 'politeness' => 'assertive', 'dice_translation_domain' => 'game']);

        self::assertSame([6, 20], $container->getDefinition(AccessibleDice::class)->getArgument(3));
        self::assertSame(['coin'], $container->getDefinition(AccessibleDice::class)->getArgument(4));
        self::assertSame(Politeness::Assertive, $container->getDefinition(AccessibleDiceExtension::class)->getArgument(4));
        self::assertSame('game', $container->getDefinition(Words::class)->getArgument(1));
    }

    public function testADieHasTwoSidesAtLeast(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['sides' => [1]]);
    }

    public function testADieOfTwoSidesIsOffered(): void
    {
        $container = $this->load(['sides' => [2]]);

        self::assertSame([2], $container->getDefinition(AccessibleDice::class)->getArgument(3));
    }

    public function testThePolitenessIsPoliteOrAssertive(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['politeness' => 'off']);
    }

    public function testTheDiceBundleIsRequired(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            /**
             * @param array<array-key, mixed> $configs
             */
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'tilttek_dice';
            }
        });
        $extension->prepend($container);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('TilttekAccessibleDiceBundle reads the custom dice and the limits of TilttekDiceBundle, from tilttek/dice-bundle: register it too.');
        $extension->prepend(new ContainerBuilder());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekAccessibleDiceBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
