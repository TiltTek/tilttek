<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Infrastructure\DependencyInjection;

use Psr\EventDispatcher\EventDispatcherInterface;
use Random\Engine\Secure;
use Random\Randomizer;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\ConstraintValidator;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\Dialect\Dialect;
use Tilttek\Dice\Dialect\Dialects;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Notation\Notation;
use Tilttek\Dice\Notation\Notations;
use Tilttek\DiceBundle\Application\DiceMacros;
use Tilttek\DiceBundle\Application\DiceRolls;
use Tilttek\DiceBundle\Application\NoDiceAppearance;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;
use Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Doctrine\DoctrineDiceMacroRepository;
use Tilttek\DiceBundle\Infrastructure\Doctrine\DoctrineRecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Form\DiceExpressionType;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryDiceMacroRepository;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryRecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Twig\DiceExtension;
use Tilttek\DiceBundle\Infrastructure\Validator\DiceExpressionValidator;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the library's limits, custom dice, notations and dialects, its `DiceRoller`, a secure
 * randomizer, the journal, the macros, the appearance of a member's dice, `DiceRolls` — with the event dispatcher when
 * there is one —, `DiceMacros`, and — when their
 * components are installed — the constraint, the form type and the Twig extension. The custom dice and the notations are built once here, so
 * that a wrong definition fails when the container is compiled rather than at the first roll.
 */
final class DiceServices
{
    public const string DIALECT_TAG = 'tilttek_dice.dialect';

    private function __construct()
    {
    }

    /**
     * @param array{limits: array{dice: int, sides: int, explosions: int, expressions: int, length: int, rerolls: int, repetitions: int, text: int}, custom_dice: array<string, array{faces: list<array{id: string, label: string, value: ?int, color: ?string, shape: ?string, image: ?string}>}>, notations: array<string, array{die: string, advantage: string, disadvantage: string}>, appearance: ?string, translation_domain: string, store: string, entity_manager: string} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::customDice($config['custom_dice']);
        self::notations($config['notations']);

        $container->register(Limits::class, Limits::class)->setArguments(array_values($config['limits']));
        $container->register(CustomDice::class, CustomDice::class)->setFactory([self::class, 'customDice'])->setArguments([$config['custom_dice']]);
        $container->register(Notations::class, Notations::class)->setFactory([self::class, 'notations'])->setArguments([$config['notations']]);
        $container->registerForAutoconfiguration(Dialect::class)->addTag(self::DIALECT_TAG);
        $container->register(Dialects::class, Dialects::class)->setFactory([self::class, 'dialects'])->setArguments([new TaggedIteratorArgument(self::DIALECT_TAG)]);
        $container->register(DiceRoller::class, DiceRoller::class)
            ->setArguments([new Reference(Limits::class), new Reference(CustomDice::class), new Reference(Notations::class), new Reference(Dialects::class)])
            ->setPublic(true);
        $container->register('tilttek_dice.randomizer', Randomizer::class)->setArguments([new Definition(Secure::class)]);

        if ('memory' === $config['store']) {
            $container->register(InMemoryRecordedRollRepository::class, InMemoryRecordedRollRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(RecordedRollRepository::class, InMemoryRecordedRollRepository::class)->setPublic(true);
            $container->register(InMemoryDiceMacroRepository::class, InMemoryDiceMacroRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(DiceMacroRepository::class, InMemoryDiceMacroRepository::class)->setPublic(true);
        } else {
            $entityManager = new Reference(\sprintf('doctrine.orm.%s_entity_manager', $config['entity_manager']));
            $container->register(DoctrineRecordedRollRepository::class, DoctrineRecordedRollRepository::class)->setArguments([$entityManager]);
            $container->setAlias(RecordedRollRepository::class, DoctrineRecordedRollRepository::class)->setPublic(true);
            $container->register(DoctrineDiceMacroRepository::class, DoctrineDiceMacroRepository::class)->setArguments([$entityManager]);
            $container->setAlias(DiceMacroRepository::class, DoctrineDiceMacroRepository::class)->setPublic(true);
        }

        if (null === $config['appearance']) {
            $container->register(NoDiceAppearance::class, NoDiceAppearance::class);
            $container->setAlias(DiceAppearance::class, NoDiceAppearance::class);
        } else {
            $container->setAlias(DiceAppearance::class, $config['appearance']);
        }

        $container->register(DiceRolls::class, DiceRolls::class)
            ->setArguments([new Reference(DiceRoller::class), new Reference('tilttek_dice.randomizer'), new Reference(RecordedRollRepository::class), new Reference('clock'), new Reference(DiceAppearance::class), new Reference(EventDispatcherInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->setPublic(true);
        $container->register(DiceMacros::class, DiceMacros::class)
            ->setArguments([new Reference(DiceRoller::class), new Reference(DiceMacroRepository::class)])
            ->setPublic(true);

        if (class_exists(ConstraintValidator::class)) {
            $container->register(DiceExpressionValidator::class, DiceExpressionValidator::class)
                ->setArguments([new Reference(DiceRolls::class)])
                ->addTag('validator.constraint_validator');
        }

        if (class_exists(AbstractType::class)) {
            $container->register(DiceExpressionType::class, DiceExpressionType::class)->addTag('form.type');
        }

        if (class_exists(AsTwigFunction::class)) {
            $container->register(DiceExtension::class, DiceExtension::class)
                ->setArguments([new Reference(DiceRolls::class), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['translation_domain']])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }
    }

    /**
     * The custom dice of `tilttek_dice.custom_dice`.
     *
     * @param array<string, array{faces: list<array{id: string, label: string, value: ?int, color: ?string, shape: ?string, image: ?string}>}> $dice
     */
    public static function customDice(array $dice): CustomDice
    {
        $definitions = [];

        foreach ($dice as $name => $die) {
            $definitions[] = new DieDefinition($name, ...array_map(
                static fn (array $face): Face => new Face($face['id'], $face['label'], $face['value'], $face['color'], null === $face['shape'] ? null : Shape::from($face['shape']), $face['image']),
                $die['faces'],
            ));
        }

        return new CustomDice(...$definitions);
    }

    /**
     * The notations of `tilttek_dice.notations`, added to the built-in ones.
     *
     * @param array<string, array{die: string, advantage: string, disadvantage: string}> $notations
     */
    public static function notations(array $notations): Notations
    {
        return new Notations(array_map(static fn (array $notation): Notation => new Notation($notation['die'], $notation['advantage'], $notation['disadvantage']), $notations));
    }

    /**
     * The built-in dialects and those of the application, tagged `tilttek_dice.dialect`.
     *
     * @param iterable<Dialect> $dialects
     */
    public static function dialects(iterable $dialects): Dialects
    {
        return new Dialects(...$dialects);
    }
}
