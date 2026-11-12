<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Infrastructure\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\AccessibleDice\Application\AccessibleDice;
use Tilttek\AccessibleDice\Application\FaceDescriber;
use Tilttek\AccessibleDice\Application\Politeness;
use Tilttek\AccessibleDice\Application\RollAnnouncer;
use Tilttek\AccessibleDice\Application\Words;
use Tilttek\AccessibleDice\Infrastructure\Twig\AccessibleDiceExtension;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Limits;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the words through the application's translator, the descriptions of the faces of the custom
 * dice of `TilttekDiceBundle`, the announcer, the dice offered — as many of one at once as its limits allow — and, with
 * Twig, the form.
 */
final class AccessibleDiceServices
{
    private function __construct()
    {
    }

    /**
     * @param array{sides: list<int>, custom_dice: ?list<string>, politeness: string, dice_translation_domain: string} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->register(Words::class, Words::class)
            ->setArguments([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['dice_translation_domain']]);
        $container->register(FaceDescriber::class, FaceDescriber::class)
            ->setArguments([new Reference(CustomDice::class), new Reference(Words::class)])
            ->setPublic(true);
        $container->register(RollAnnouncer::class, RollAnnouncer::class)
            ->setArguments([new Reference(Words::class)])
            ->setPublic(true);
        $container->register(AccessibleDice::class, AccessibleDice::class)
            ->setArguments([
                new Reference(CustomDice::class),
                new Reference(FaceDescriber::class),
                new Reference(Words::class),
                $config['sides'],
                $config['custom_dice'],
                new Definition('int')->setFactory([self::class, 'most'])->setArguments([new Reference(Limits::class)]),
            ])
            ->setPublic(true);

        if (class_exists(AsTwigFunction::class)) {
            $container->register(AccessibleDiceExtension::class, AccessibleDiceExtension::class)
                ->setArguments([
                    new Reference(AccessibleDice::class),
                    new Reference(FaceDescriber::class),
                    new Reference(RollAnnouncer::class),
                    new Reference(Words::class),
                    Politeness::from($config['politeness']),
                ])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }
    }

    /**
     * How many dice one choice rolls at most: the dice limit of `TilttekDiceBundle`.
     */
    public static function most(Limits $limits): int
    {
        return $limits->dice;
    }
}
