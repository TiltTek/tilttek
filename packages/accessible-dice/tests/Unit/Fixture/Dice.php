<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Tests\Unit\Fixture;

use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Result\PoolResult;

/**
 * The dice the tests roll: a coin that counts, gems told by shape or image, and a translator in French.
 */
final class Dice
{
    public static function customDice(): CustomDice
    {
        return new CustomDice(
            new DieDefinition('coin', new Face('heads', 'Heads', 1, shape: Shape::Circle), new Face('tails', 'Tails', 0, shape: Shape::Square)),
            new DieDefinition('gem', new Face('ruby', 'gem.ruby', color: '#aa0000', shape: Shape::Diamond), new Face('pearl', 'Pearl', image: 'pearl.svg'), new Face('void', 'Void')),
        );
    }

    public static function roll(string $expression, int $seed = 7): PoolResult
    {
        $roller = new DiceRoller(customDice: self::customDice());

        return $roller->roll($roller->parse($expression), new Randomizer(new Mt19937($seed)));
    }

    public static function translator(): Translator
    {
        $translator = new Translator('fr');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', \dirname(__DIR__, 3).'/translations/tilttek_accessible_dice.fr.yaml', 'fr', 'tilttek_accessible_dice');
        $translator->addResource('yaml', \dirname(__DIR__, 3).'/translations/tilttek_accessible_dice.en.yaml', 'en', 'tilttek_accessible_dice');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Heads' => 'Pile', 'Tails' => 'Face', 'gem.ruby' => 'Rubis', 'coin' => 'pièce'], 'fr', 'tilttek_dice');
        $translator->addResource('array', ['gem.ruby' => 'Ruby'], 'en', 'tilttek_dice');

        return $translator;
    }
}
