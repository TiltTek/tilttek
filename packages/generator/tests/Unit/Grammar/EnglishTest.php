<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Grammar\ArticleKind;
use Tilttek\Generator\Grammar\English;
use Tilttek\Generator\Grammar\Gender;
use Tilttek\Generator\Grammar\Words;

#[CoversClass(English::class)]
#[CoversClass(Words::class)]
final class EnglishTest extends TestCase
{
    /**
     * @return iterable<array{string, string}>
     */
    public static function elisions(): iterable
    {
        yield ['a apple and an pear', 'an apple and a pear'];
        yield ['A owl, An bat', 'An owl, A bat'];
        yield ['a hour, a honest man, a heir, a honour', 'an hour, an honest man, an heir, an honour'];
        yield ['an unicorn, an user, an usual, an utility, an European, an ewe, an one-eyed, an once, an ubiquitous, an urea', 'a unicorn, a user, a usual, a utility, a European, a ewe, a one-eyed, a once, a ubiquitous, a urea'];
        yield ['a umbrella', 'an umbrella'];
        yield ['a Hour and a Apple', 'an Hour and an Apple'];
        yield ['a ', 'a '];
        yield ['a', 'a'];
        yield ['banana a', 'banana a'];
        yield ['an ^banana, a ^apple, ^a apple', 'an banana, a apple, a apple'];
    }

    #[DataProvider('elisions')]
    public function testItChoosesAOrAn(string $text, string $expected): void
    {
        self::assertSame($expected, new English()->elide($text));
    }

    public function testItPutsTheArticle(): void
    {
        $english = new English();

        self::assertSame('the sword', $english->article('sword', ArticleKind::Definite, Gender::Feminine, false));
        self::assertSame('an axe', $english->article('axe', ArticleKind::Indefinite, Gender::Masculine, false));
        self::assertSame('axes', $english->article('axes', ArticleKind::Indefinite, Gender::Masculine, true));
        self::assertSame('some bread', $english->article('bread', ArticleKind::Partitive, Gender::Masculine, false));
        self::assertSame('this sword', $english->article('sword', ArticleKind::Demonstrative, Gender::Masculine, false));
        self::assertSame('these swords', $english->article('swords', ArticleKind::Demonstrative, Gender::Masculine, true));
    }

    public function testItHasNoFeminine(): void
    {
        self::assertSame('old', new English()->feminine('old'));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function plurals(): iterable
    {
        yield ['sword', 'swords'];
        yield ['glass', 'glasses'];
        yield ['box', 'boxes'];
        yield ['waltz', 'waltzes'];
        yield ['church', 'churches'];
        yield ['wish', 'wishes'];
        yield ['city', 'cities'];
        yield ['day', 'days'];
        yield ['BOX', 'BOXes'];
        yield ['CITY', 'CITies'];
        yield ['pyre', 'pyres'];
        yield ['Elf', 'Elves'];
        yield ['mouse', 'mice'];
        yield ['sheep', 'sheep'];
    }

    #[DataProvider('plurals')]
    public function testItMakesThePlural(string $word, string $expected): void
    {
        self::assertSame($expected, new English()->plural($word));
    }

    public function testOnlyOneTakesTheSingular(): void
    {
        $english = new English();

        self::assertTrue($english->isPlural(0));
        self::assertFalse($english->isPlural(1));
        self::assertFalse($english->isPlural(-1));
        self::assertTrue($english->isPlural(2));
    }
}
