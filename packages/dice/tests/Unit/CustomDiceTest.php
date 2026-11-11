<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\InvalidDefinition;

#[CoversClass(Face::class)]
#[CoversClass(DieDefinition::class)]
#[CoversClass(CustomDice::class)]
final class CustomDiceTest extends TestCase
{
    public function testAFaceKeepsHowItIsShown(): void
    {
        $face = new Face('star', 'dice.star', 2, '#A0b0C0', Shape::Star, 'star.svg');

        self::assertSame(['star', 'dice.star', 2, '#A0b0C0', Shape::Star, 'star.svg'], [$face->id, $face->label, $face->value, $face->color, $face->shape, $face->image]);
        self::assertSame('#ff0000', new Face('red', 'Red', color: '#ff0000', image: 'red.png')->color);
        self::assertSame(str_repeat('a', 32), new Face(str_repeat('a', 32), 'A')->id);
        self::assertSame('9-x_', new Face('9-x_', 'Nine')->id);
    }

    /**
     * @return iterable<string, array{callable(): Face, string}>
     */
    public static function invalidFaces(): iterable
    {
        yield 'upper case' => [static fn (): Face => new Face('Star', 'Star'), 'A face identifier is 1 to 32 lower-case letters, digits, "_" or "-"; "Star" is not.'];
        yield 'too long' => [static fn (): Face => new Face(str_repeat('a', 33), 'A'), \sprintf('A face identifier is 1 to 32 lower-case letters, digits, "_" or "-"; "%s" is not.', str_repeat('a', 33))];
        yield 'a leading dash' => [static fn (): Face => new Face('-a', 'A'), 'A face identifier is 1 to 32 lower-case letters, digits, "_" or "-"; "-a" is not.'];
        yield 'a trailing newline' => [static fn (): Face => new Face("a\n", 'A'), "A face identifier is 1 to 32 lower-case letters, digits, \"_\" or \"-\"; \"a\n\" is not."];
        yield 'a blank label' => [static fn (): Face => new Face('a', ' '), 'The face "a" has no label: a screen reader would have nothing to say.'];
        yield 'a short colour' => [static fn (): Face => new Face('a', 'A', color: '#fff', shape: Shape::Circle), 'The colour of the face "a" is written #rrggbb; "#fff" is not.'];
        yield 'a colour after a letter' => [static fn (): Face => new Face('a', 'A', color: 'x#ffffff', shape: Shape::Circle), 'The colour of the face "a" is written #rrggbb; "x#ffffff" is not.'];
        yield 'a colour and a newline' => [static fn (): Face => new Face('a', 'A', color: "#ffffff\n", shape: Shape::Circle), "The colour of the face \"a\" is written #rrggbb; \"#ffffff\n\" is not."];
        yield 'an empty image' => [static fn (): Face => new Face('a', 'A', image: ''), 'The image of the face "a" is empty.'];
        yield 'a colour alone' => [static fn (): Face => new Face('a', 'A', color: '#ff0000'), 'The face "a" is told by its colour alone: give it a shape or an image too.'];
    }

    /**
     * @param callable(): Face $face
     */
    #[DataProvider('invalidFaces')]
    public function testAnInvalidFaceIsRefused(callable $face, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));
        $face();
    }

    public function testADieHasNamedDistinctFacesAllValuedOrNone(): void
    {
        $coin = new DieDefinition('coin', new Face('heads', 'Heads', 1), new Face('tails', 'Tails', 0));
        $colours = new DieDefinition('colours', new Face('red', 'Red', shape: Shape::Circle), new Face('blue', 'Blue', shape: Shape::Square));

        self::assertSame(['coin', ['heads', 'tails'], true], [$coin->name, array_map(static fn (Face $face): string => $face->id, $coin->faces), $coin->numeric()]);
        self::assertFalse($colours->numeric());
        self::assertSame([0, 1], array_keys(new DieDefinition('keyed', ...['x' => new Face('a', 'A'), 'y' => new Face('b', 'B')])->faces));
    }

    /**
     * @return iterable<string, array{callable(): DieDefinition, string}>
     */
    public static function invalidDice(): iterable
    {
        yield 'a bad name' => [static fn (): DieDefinition => new DieDefinition('Coin', new Face('a', 'A'), new Face('b', 'B')), 'A die name is 1 to 32 lower-case letters, digits, "_" or "-", starting with a letter; "Coin" is not.'];
        yield 'one face' => [static fn (): DieDefinition => new DieDefinition('coin', new Face('a', 'A')), 'The die "coin" needs at least two faces.'];
        yield 'twin faces' => [static fn (): DieDefinition => new DieDefinition('coin', new Face('a', 'A'), new Face('b', 'B'), new Face('a', 'C')), 'Two faces of the die "coin" share an identifier.'];
        yield 'some values' => [static fn (): DieDefinition => new DieDefinition('coin', new Face('a', 'A', 1), new Face('b', 'B')), 'Either every face of the die "coin" carries a value, or none does.'];
        yield 'a value missing first' => [static fn (): DieDefinition => new DieDefinition('coin', new Face('a', 'A'), new Face('b', 'B', 1)), 'Either every face of the die "coin" carries a value, or none does.'];
    }

    /**
     * @param callable(): DieDefinition $die
     */
    #[DataProvider('invalidDice')]
    public function testAnInvalidDieIsRefused(callable $die, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));
        $die();
    }

    public function testTheCustomDiceAreFoundByName(): void
    {
        $coin = new DieDefinition('coin', new Face('heads', 'Heads'), new Face('tails', 'Tails'));
        $bone = new DieDefinition('bone', new Face('a', 'A'), new Face('b', 'B'));
        $dice = new CustomDice($coin, $bone);

        self::assertSame([$coin, true, false, [$coin, $bone]], [$dice->get('coin'), $dice->has('coin'), $dice->has('star'), $dice->all()]);
        self::assertSame([], new CustomDice()->all());

        $this->expectExceptionObject(CannotRoll::unknownDie('star'));
        $dice->get('star');
    }

    public function testTwoDiceCannotShareAName(): void
    {
        $coin = new DieDefinition('coin', new Face('heads', 'Heads'), new Face('tails', 'Tails'));

        $this->expectExceptionObject(InvalidDefinition::because('Two dice are named "coin".'));
        new CustomDice($coin, $coin);
    }
}
