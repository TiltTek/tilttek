<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\AccessibleDice\Application\AccessibleDice;
use Tilttek\AccessibleDice\Application\DieChoice;
use Tilttek\AccessibleDice\Application\Exception\InvalidChoice;
use Tilttek\AccessibleDice\Application\FaceDescriber;
use Tilttek\AccessibleDice\Application\Words;
use Tilttek\AccessibleDice\Tests\Unit\Fixture\Dice;

#[CoversClass(AccessibleDice::class)]
#[CoversClass(DieChoice::class)]
#[CoversClass(InvalidChoice::class)]
final class AccessibleDiceTest extends TestCase
{
    public function testTheStandardDiceThenEveryCustomDie(): void
    {
        $dice = $this->dice();
        $choices = $dice->choices();

        self::assertSame(['d4', 'd6', 'd8', 'd10', 'd12', 'd20', 'd100', 'coin', 'gem'], array_map(static fn (DieChoice $choice): string => $choice->name, $choices));
        self::assertSame(['d4', 'd6', 'd8', 'd10', 'd12', 'd20', 'd100', 'd{coin}', 'd{gem}'], array_map(static fn (DieChoice $choice): string => $choice->notation, $choices));
        self::assertSame('4-sided die', $choices[0]->label);
        self::assertSame([], $choices[0]->faces);
        self::assertSame('coin', $choices[7]->label);
        self::assertSame('Heads: a circle, worth 1', $choices[7]->faces[0]->description);
        self::assertSame(100, $dice->most());
    }

    public function testTheDiceTheConfigurationNamesInTheLanguageOfTheReader(): void
    {
        $words = new Words(Dice::translator());
        $dice = new AccessibleDice(Dice::customDice(), new FaceDescriber(Dice::customDice(), $words), $words, [6, 20], ['coin'], 12);
        $choices = $dice->choices();

        self::assertSame(['d6', 'd20', 'coin'], array_map(static fn (DieChoice $choice): string => $choice->name, $choices));
        self::assertSame(['dé à 6 faces', 'dé à 20 faces', 'pièce'], array_map(static fn (DieChoice $choice): string => $choice->label, $choices));
        self::assertSame('20-sided die', $dice->choices('en')[1]->label);
        self::assertSame(12, $dice->most());
        self::assertSame('12d{coin}', $dice->expression(['die' => 'coin', 'count' => '12']));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function choices(): iterable
    {
        yield 'a die' => [['die' => 'd6'], '1d6'];
        yield 'a count and a modifier' => [['die' => 'd20', 'count' => '2', 'modifier' => '5'], '2d20+5'];
        yield 'a negative modifier' => [['die' => 'd8', 'count' => 3, 'modifier' => -2], '3d8-2'];
        yield 'no modifier' => [['die' => 'd100', 'count' => '1', 'modifier' => '0'], '1d100'];
        yield 'a custom die' => [['die' => 'gem', 'count' => '4'], '4d{gem}'];
        yield 'the most dice and the largest modifiers' => [['die' => 'd4', 'count' => '100', 'modifier' => '-1000'], '100d4-1000'];
        yield 'the largest modifier' => [['die' => 'd4', 'modifier' => '1000'], '1d4+1000'];
    }

    /**
     * @param array<array-key, mixed> $choice
     */
    #[DataProvider('choices')]
    public function testTheExpressionOfAChoice(array $choice, string $expression): void
    {
        self::assertSame($expression, $this->dice()->expression($choice));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidChoices(): iterable
    {
        yield 'no die' => [[], 'The die is not one the interface offers.'];
        yield 'a die not offered' => [['die' => 'd7'], 'The die is not one the interface offers.'];
        yield 'a die written as an expression' => [['die' => 'd{coin}'], 'The die is not one the interface offers.'];
        yield 'a die that is no text' => [['die' => 6], 'The die is not one the interface offers.'];
        yield 'no dice' => [['die' => 'd6', 'count' => '0'], 'The count is a whole number from 1 to 100.'];
        yield 'too many dice' => [['die' => 'd6', 'count' => '101'], 'The count is a whole number from 1 to 100.'];
        yield 'a count that is no number' => [['die' => 'd6', 'count' => '2d6'], 'The count is a whole number from 1 to 100.'];
        yield 'a count that is a list' => [['die' => 'd6', 'count' => ['2']], 'The count is a whole number from 1 to 100.'];
        yield 'a modifier too large' => [['die' => 'd6', 'modifier' => '1001'], 'The modifier is a whole number from -1000 to 1000.'];
        yield 'a modifier too small' => [['die' => 'd6', 'modifier' => '-1001'], 'The modifier is a whole number from -1000 to 1000.'];
        yield 'a count that is a whole float' => [['die' => 'd6', 'count' => 2.0], 'The count is a whole number from 1 to 100.'];
        yield 'a count that is a boolean' => [['die' => 'd6', 'count' => true], 'The count is a whole number from 1 to 100.'];
        yield 'a modifier that is a fraction' => [['die' => 'd6', 'modifier' => 1.5], 'The modifier is a whole number from -1000 to 1000.'];
    }

    /**
     * @param array<array-key, mixed> $choice
     */
    #[DataProvider('invalidChoices')]
    public function testAChoiceTheInterfaceDoesNotOfferIsRefused(array $choice, string $message): void
    {
        $this->expectException(InvalidChoice::class);
        $this->expectExceptionMessageIs($message);

        $this->dice()->expression($choice);
    }

    public function testADieThatCannotBeOfferedIsRefused(): void
    {
        $this->assertRefused(static fn (): AccessibleDice => new AccessibleDice(Dice::customDice(), new FaceDescriber(Dice::customDice()), sides: [6, 1]), 'A standard die has at least 2 sides; 1 is too few.');
        $this->assertRefused(static fn (): AccessibleDice => new AccessibleDice(Dice::customDice(), new FaceDescriber(Dice::customDice()), custom: ['coin', 'wand']), 'No custom die is named "wand".');
        $this->assertRefused(static fn (): AccessibleDice => new AccessibleDice(Dice::customDice(), new FaceDescriber(Dice::customDice()), most: 0), 'A choice rolls at least one die.');
        self::assertSame(1, new AccessibleDice(Dice::customDice(), new FaceDescriber(Dice::customDice()), sides: [2], custom: [], most: 1)->most());
    }

    private function dice(): AccessibleDice
    {
        return new AccessibleDice(Dice::customDice(), new FaceDescriber(Dice::customDice()));
    }

    /**
     * @param callable(): AccessibleDice $create
     */
    private function assertRefused(callable $create, string $message): void
    {
        try {
            $create();
            self::fail('The die was offered.');
        } catch (InvalidChoice $invalidChoice) {
            self::assertSame($message, $invalidChoice->getMessage());
        }
    }
}
