<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Secure;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\Dialect\Dialect;
use Tilttek\Dice\Dialect\Dialects;
use Tilttek\Dice\Dialect\Roll20;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Notation\Notations;
use Tilttek\DiceBundle\Application\DiceMacros;
use Tilttek\DiceBundle\Application\DiceRolls;
use Tilttek\DiceBundle\Application\NoDiceAppearance;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;
use Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\DependencyInjection\DiceServices;
use Tilttek\DiceBundle\Infrastructure\Doctrine\DoctrineDiceMacroRepository;
use Tilttek\DiceBundle\Infrastructure\Doctrine\DoctrineRecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Form\DiceExpressionType;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryDiceMacroRepository;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryRecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Twig\DiceExtension;
use Tilttek\DiceBundle\Infrastructure\Validator\DiceExpressionValidator;
use Tilttek\DiceBundle\TilttekDiceBundle;

#[CoversClass(TilttekDiceBundle::class)]
#[CoversClass(DiceServices::class)]
final class TilttekDiceBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('tilttek_dice', $this->extension()->getAlias());
        self::assertSame(['service_container', Limits::class, CustomDice::class, Notations::class, Dialects::class, DiceRoller::class, 'tilttek_dice.randomizer', DoctrineRecordedRollRepository::class, DoctrineDiceMacroRepository::class, NoDiceAppearance::class, DiceRolls::class, DiceMacros::class, DiceExpressionValidator::class, DiceExpressionType::class, DiceExtension::class], array_keys($container->getDefinitions()));
        self::assertSame([100, 1000, 100, 10, 256, 100, 100, 4096], $container->getDefinition(Limits::class)->getArguments());
        self::assertSame([DiceServices::class, 'customDice'], $container->getDefinition(CustomDice::class)->getFactory());
        self::assertSame([[]], $container->getDefinition(CustomDice::class)->getArguments());
        self::assertSame([DiceServices::class, 'notations'], $container->getDefinition(Notations::class)->getFactory());
        self::assertSame([[]], $container->getDefinition(Notations::class)->getArguments());
        self::assertSame([DiceServices::class, 'dialects'], $container->getDefinition(Dialects::class)->getFactory());
        self::assertEquals([new TaggedIteratorArgument('tilttek_dice.dialect')], $container->getDefinition(Dialects::class)->getArguments());
        self::assertSame(['tilttek_dice.dialect' => [[]]], $container->getAutoconfiguredInstanceof()[Dialect::class]->getTags());
        self::assertEquals([new Reference(Limits::class), new Reference(CustomDice::class), new Reference(Notations::class), new Reference(Dialects::class)], $container->getDefinition(DiceRoller::class)->getArguments());
        self::assertTrue($container->getDefinition(DiceRoller::class)->isPublic());
        self::assertEquals([new Definition(Secure::class)], $container->getDefinition('tilttek_dice.randomizer')->getArguments());
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineRecordedRollRepository::class)->getArguments());
        self::assertSame([DoctrineRecordedRollRepository::class, true], [(string) $container->getAlias(RecordedRollRepository::class), $container->getAlias(RecordedRollRepository::class)->isPublic()]);
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineDiceMacroRepository::class)->getArguments());
        self::assertSame([DoctrineDiceMacroRepository::class, true], [(string) $container->getAlias(DiceMacroRepository::class), $container->getAlias(DiceMacroRepository::class)->isPublic()]);
        self::assertEquals([new Reference(DiceRoller::class), new Reference(DiceMacroRepository::class)], $container->getDefinition(DiceMacros::class)->getArguments());
        self::assertTrue($container->getDefinition(DiceMacros::class)->isPublic());
        self::assertSame([NoDiceAppearance::class, false], [(string) $container->getAlias(DiceAppearance::class), $container->getAlias(DiceAppearance::class)->isPublic()]);
        self::assertEquals([new Reference(DiceRoller::class), new Reference('tilttek_dice.randomizer'), new Reference(RecordedRollRepository::class), new Reference('clock'), new Reference(DiceAppearance::class)], $container->getDefinition(DiceRolls::class)->getArguments());
        self::assertTrue($container->getDefinition(DiceRolls::class)->isPublic());
        self::assertEquals([new Reference(DiceRolls::class)], $container->getDefinition(DiceExpressionValidator::class)->getArguments());
        self::assertSame(['validator.constraint_validator' => [[]]], $container->getDefinition(DiceExpressionValidator::class)->getTags());
        self::assertSame(['form.type' => [[]]], $container->getDefinition(DiceExpressionType::class)->getTags());
        self::assertEquals([new Reference(DiceRolls::class), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'tilttek_dice'], $container->getDefinition(DiceExtension::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(DiceExtension::class)->getTags());
        self::assertTrue($container->getDefinition(DiceExtension::class)->isPublic());
        self::assertFalse($container->getDefinition(Limits::class)->isPublic());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'limits' => ['dice' => 5, 'sides' => 20, 'explosions' => 3, 'expressions' => 2, 'length' => 40, 'rerolls' => 7, 'repetitions' => 8, 'text' => 90],
            'custom_dice' => ['coin' => ['faces' => [['id' => 'heads', 'label' => 'Heads', 'value' => 1, 'shape' => 'circle'], ['id' => 'tails', 'label' => 'Tails', 'value' => 0, 'color' => '#00ff00', 'image' => 'tails.png']]]],
            'notations' => ['fi' => ['die' => 'n'], 'xx' => ['die' => 'q', 'advantage' => 'up', 'disadvantage' => 'down']],
            'appearance' => 'app.appearance',
            'translation_domain' => 'dice',
            'store' => 'memory',
        ]);

        self::assertSame([5, 20, 3, 2, 40, 7, 8, 90], $container->getDefinition(Limits::class)->getArguments());
        self::assertSame([InMemoryRecordedRollRepository::class, true], [(string) $container->getAlias(RecordedRollRepository::class), $container->getAlias(RecordedRollRepository::class)->isPublic()]);
        self::assertFalse($container->hasDefinition(DoctrineRecordedRollRepository::class));
        self::assertTrue($container->hasDefinition(InMemoryRecordedRollRepository::class));
        self::assertSame([InMemoryDiceMacroRepository::class, true], [(string) $container->getAlias(DiceMacroRepository::class), $container->getAlias(DiceMacroRepository::class)->isPublic()]);
        self::assertFalse($container->hasDefinition(DoctrineDiceMacroRepository::class));
        self::assertTrue($container->hasDefinition(InMemoryDiceMacroRepository::class));
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryRecordedRollRepository::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryDiceMacroRepository::class)->getTags());
        self::assertSame('app.appearance', (string) $container->getAlias(DiceAppearance::class));
        self::assertFalse($container->hasDefinition(NoDiceAppearance::class));
        self::assertSame('dice', $container->getDefinition(DiceExtension::class)->getArgument(2));

        $faces = ['coin' => ['faces' => [
            ['id' => 'heads', 'label' => 'Heads', 'value' => 1, 'shape' => 'circle', 'color' => null, 'image' => null],
            ['id' => 'tails', 'label' => 'Tails', 'value' => 0, 'color' => '#00ff00', 'image' => 'tails.png', 'shape' => null],
        ]]];
        self::assertSame($faces, $container->getDefinition(CustomDice::class)->getArgument(0));
        $dice = DiceServices::customDice($faces);
        $tails = $dice->get('coin')->faces[1];
        self::assertSame([Shape::Circle, 1, 'Tails', 0, '#00ff00', null, 'tails.png'], [$dice->get('coin')->faces[0]->shape, $dice->get('coin')->faces[0]->value, $tails->label, $tails->value, $tails->color, $tails->shape, $tails->image]);

        $letters = ['fi' => ['die' => 'n', 'advantage' => 'adv', 'disadvantage' => 'dis'], 'xx' => ['die' => 'q', 'advantage' => 'up', 'disadvantage' => 'down']];
        self::assertSame($letters, $container->getDefinition(Notations::class)->getArgument(0));
        $notations = DiceServices::notations($letters);
        self::assertSame(['n', 'adv', 'dis', 'q', 'up', 'down', 'W'], [$notations->for('fi')->die, $notations->for('fi')->advantage, $notations->for('fi')->disadvantage, $notations->for('xx')->die, $notations->for('xx')->advantage, $notations->for('xx')->disadvantage, $notations->for('de')->die]);

        $roll20 = new Roll20();
        self::assertSame($roll20, DiceServices::dialects([$roll20])->get('roll20'));
        self::assertSame(['roll20', 'foundry', 'avrae', 'diceparser'], DiceServices::dialects([])->names());
    }

    public function testAnotherEntityManagerKeepsTheJournal(): void
    {
        $container = $this->load(['entity_manager' => 'dice', 'store' => 'doctrine']);

        self::assertEquals([new Reference('doctrine.orm.dice_entity_manager')], $container->getDefinition(DoctrineRecordedRollRepository::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.dice_entity_manager')], $container->getDefinition(DoctrineDiceMacroRepository::class)->getArguments());
    }

    public function testNamesKeepTheirDashesAndAShapeMayBeNull(): void
    {
        $container = $this->load([
            'custom_dice' => ['my-die' => ['faces' => [['id' => 'a', 'label' => 'A', 'shape' => null], ['id' => 'b', 'label' => 'B']]]],
            'notations' => ['pt-BR' => ['die' => 'q']],
        ]);

        self::assertSame(['my-die' => ['faces' => [['id' => 'a', 'label' => 'A', 'shape' => null, 'value' => null, 'color' => null, 'image' => null], ['id' => 'b', 'label' => 'B', 'value' => null, 'color' => null, 'shape' => null, 'image' => null]]]], $container->getDefinition(CustomDice::class)->getArgument(0));
        self::assertSame(['pt-BR' => ['die' => 'q', 'advantage' => 'adv', 'disadvantage' => 'dis']], $container->getDefinition(Notations::class)->getArgument(0));
    }

    public function testAWrongDieFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('The face "red" is told by its colour alone: give it a shape or an image too.'));
        $this->load(['custom_dice' => ['colours' => ['faces' => [['id' => 'red', 'label' => 'Red', 'color' => '#ff0000'], ['id' => 'blue', 'label' => 'Blue', 'shape' => 'square']]]]]);
    }

    public function testAWrongNotationFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->load(['notations' => ['xx' => ['die' => 'k']]]);
    }

    public function testTheLimitsAreAtLeastOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load(['limits' => ['explosions' => 0]]);
    }

    public function testTheDefaultsOfTheLimitsAreEachTheirOwn(): void
    {
        self::assertSame([1, 1, 1, 1, 1, 1, 1, 1], $this->load(['limits' => ['dice' => 1, 'sides' => 1, 'explosions' => 1, 'expressions' => 1, 'length' => 1, 'rerolls' => 1, 'repetitions' => 1, 'text' => 1]])->getDefinition(Limits::class)->getArguments());

        foreach (['dice', 'sides', 'explosions', 'expressions', 'length', 'rerolls', 'repetitions', 'text'] as $limit) {
            try {
                $this->load(['limits' => [$limit => 0]]);
                self::fail(\sprintf('The limit of %s took 0.', $limit));
            } catch (InvalidConfigurationException) {
            }
        }
    }

    public function testTheJournalIsMappedForDoctrine(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $builder = new ContainerBuilder();
        $extension->prepend($builder);
        self::assertSame([], $builder->getExtensionConfig('doctrine'));

        $builder->registerExtension(new DoctrineExtension());
        $extension->prepend($builder);

        self::assertSame([['orm' => ['mappings' => ['TilttekDiceBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Tilttek\DiceBundle\Domain\Model',
        ]]]]], $builder->getExtensionConfig('doctrine'));
    }

    public function testAnUnknownStoreOrShapeIsRefused(): void
    {
        foreach ([['store' => 'redis'], ['custom_dice' => ['coin' => ['faces' => [['id' => 'a', 'label' => 'A', 'shape' => 'blob']]]]], ['translation_domain' => ''], ['entity_manager' => ''], ['notations' => ['xx' => ['die' => '']]], ['custom_dice' => ['coin' => ['faces' => [['id' => '', 'label' => 'A']]]]], ['custom_dice' => ['coin' => ['faces' => [['id' => 'a', 'label' => '']]]]], ['custom_dice' => ['coin' => []]], ['notations' => ['xx' => ['die' => 'q', 'advantage' => '']]], ['notations' => ['xx' => ['die' => 'q', 'disadvantage' => '']]], ['notations' => ['xx' => []]]] as $config) {
            try {
                $this->load($config);
                self::fail(\sprintf('%s was accepted.', json_encode($config, \JSON_THROW_ON_ERROR)));
            } catch (InvalidConfigurationException) {
            }
        }

        self::assertSame(['coin' => ['faces' => [['id' => 'a', 'label' => 'A', 'value' => null, 'color' => null, 'shape' => null, 'image' => null], ['id' => 'b', 'label' => 'B', 'shape' => 'star', 'value' => null, 'color' => null, 'image' => null]]]], $this->load(['custom_dice' => ['coin' => ['faces' => [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B', 'shape' => 'star']]]]])->getDefinition(CustomDice::class)->getArgument(0));
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
        $extension = new TilttekDiceBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
