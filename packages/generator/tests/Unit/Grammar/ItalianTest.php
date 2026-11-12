<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Grammar\ArticleKind;
use Tilttek\Generator\Grammar\Gender;
use Tilttek\Generator\Grammar\Italian;
use Tilttek\Generator\Grammar\Words;

#[CoversClass(Italian::class)]
#[CoversClass(Words::class)]
final class ItalianTest extends TestCase
{
    /**
     * @return iterable<array{string, string}>
     */
    public static function elisions(): iterable
    {
        yield 'elision before a vowel' => ['il albero, lo uomo, la isola, una amica', "l'albero, l'uomo, l'isola, un'amica"];
        yield 'before an h' => ['il hotel, i hotel', "l'hotel, gli hotel"];
        yield 'before an impure s, z, gn, ps, pn, x and y' => ['il studente, il zaino, il gnomo, il psicologo, il pneumatico, il xilofono, il yogurt', 'lo studente, lo zaino, lo gnomo, lo psicologo, lo pneumatico, lo xilofono, lo yogurt'];
        yield 'un before an impure s' => ['un spettro, un zaino, un amico, un cavallo', 'uno spettro, uno zaino, un amico, un cavallo'];
        yield 'gli' => ['i amici, i spiriti, i cavalli', 'gli amici, gli spiriti, i cavalli'];
        yield 'a pure s' => ['il sole, la strega', 'il sole, la strega'];
        yield 'articulated prepositions' => ['di il re, a lo zio, da la torre, in i boschi, su gli alberi, di le spade', 'del re, allo zio, dalla torre, nei boschi, sugli alberi, delle spade'];
        yield 'with an elided article' => ["di l'oro, a l'uomo, da l'isola, in l'antro, su l'altare", "dell'oro, all'uomo, dall'isola, nell'antro, sull'altare"];
        yield 'the forms first' => ['di il oro, a il studente, in i elmi', "dell'oro, allo studente, negli elmi"];
        yield 'capitals are kept' => ['Il albero, Di il re, In la casa, Una amica', "L'albero, Del re, Nella casa, Un'amica"];
        yield 'a name with its article' => ["di L'Aquila", "dell'Aquila"];
        yield 'a capital vowel' => ['la Italia, il Oceano', "l'Italia, l'Oceano"];
        yield 'a letter that is no vowel' => ['il ñandù', 'il ñandù'];
        yield 'other words are left alone' => ['con il re, per la via, il re', 'con il re, per la via, il re'];
        yield 'spaces are kept' => ["  di  il\nre ", "  del\nre "];
        yield 'a trailing word is left alone' => ['il', 'il'];
        yield 'an empty text' => ['', ''];
        yield 'a word kept as written' => ['il ^albero, di ^il re, un ^specchio', 'il albero, di il re, un specchio'];
    }

    #[DataProvider('elisions')]
    public function testItElidesAndContracts(string $text, string $expected): void
    {
        self::assertSame($expected, new Italian()->elide($text));
    }

    /**
     * @return iterable<array{string, ArticleKind, Gender, bool, string}>
     */
    public static function articles(): iterable
    {
        yield ['re', ArticleKind::Definite, Gender::Masculine, false, 'il re'];
        yield ['spettro', ArticleKind::Definite, Gender::Masculine, false, 'lo spettro'];
        yield ['elmo', ArticleKind::Definite, Gender::Masculine, false, "l'elmo"];
        yield ['spada', ArticleKind::Definite, Gender::Feminine, false, 'la spada'];
        yield ['ascia', ArticleKind::Definite, Gender::Feminine, false, "l'ascia"];
        yield ['cavalli', ArticleKind::Definite, Gender::Masculine, true, 'i cavalli'];
        yield ['elmi', ArticleKind::Definite, Gender::Masculine, true, 'gli elmi'];
        yield ['spade', ArticleKind::Definite, Gender::Feminine, true, 'le spade'];
        yield ['re', ArticleKind::Indefinite, Gender::Masculine, false, 'un re'];
        yield ['spettro', ArticleKind::Indefinite, Gender::Masculine, false, 'uno spettro'];
        yield ['spada', ArticleKind::Indefinite, Gender::Feminine, false, 'una spada'];
        yield ['ascia', ArticleKind::Indefinite, Gender::Feminine, false, "un'ascia"];
        yield ['cavalli', ArticleKind::Indefinite, Gender::Masculine, true, 'dei cavalli'];
        yield ['spettri', ArticleKind::Indefinite, Gender::Masculine, true, 'degli spettri'];
        yield ['spade', ArticleKind::Indefinite, Gender::Feminine, true, 'delle spade'];
        yield ['pane', ArticleKind::Partitive, Gender::Masculine, false, 'del pane'];
        yield ['oro', ArticleKind::Partitive, Gender::Masculine, false, "dell'oro"];
        yield ['zucchero', ArticleKind::Partitive, Gender::Masculine, false, 'dello zucchero'];
        yield ['birra', ArticleKind::Partitive, Gender::Feminine, false, 'della birra'];
        yield ['spezie', ArticleKind::Partitive, Gender::Feminine, true, 'delle spezie'];
        yield ['funghi', ArticleKind::Partitive, Gender::Neuter, true, 'dei funghi'];
        yield ['castello', ArticleKind::Demonstrative, Gender::Masculine, false, 'questo castello'];
        yield ['torre', ArticleKind::Demonstrative, Gender::Feminine, false, 'questa torre'];
        yield ['castelli', ArticleKind::Demonstrative, Gender::Masculine, true, 'questi castelli'];
        yield ['torri', ArticleKind::Demonstrative, Gender::Feminine, true, 'queste torri'];
    }

    #[DataProvider('articles')]
    public function testItPutsTheArticle(string $word, ArticleKind $kind, Gender $gender, bool $plural, string $expected): void
    {
        self::assertSame($expected, new Italian()->article($word, $kind, $gender, $plural));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function feminines(): iterable
    {
        yield ['vecchio', 'vecchia'];
        yield ['attore', 'attrice'];
        yield ['grande', 'grande'];
        yield ['migliore', 'migliore'];
        yield ['ottimista', 'ottimista'];
    }

    #[DataProvider('feminines')]
    public function testItMakesTheFeminine(string $word, string $expected): void
    {
        self::assertSame($expected, new Italian()->feminine($word));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function plurals(): iterable
    {
        yield ['libro', 'libri'];
        yield ['fiore', 'fiori'];
        yield ['spada', 'spade'];
        yield ['bosco', 'boschi'];
        yield ['lago', 'laghi'];
        yield ['magico', 'magici'];
        yield ['figlio', 'figli'];
        yield ['bacio', 'baci'];
        yield ['amica', 'amiche'];
        yield ['strega', 'streghe'];
        yield ['camicia', 'camicie'];
        yield ['valigia', 'valigie'];
        yield ['arancia', 'arance'];
        yield ['spiaggia', 'spiagge'];
        yield ['città', 'città'];
        yield ['virtù', 'virtù'];
        yield ['sport', 'sport'];
        yield ['crisi', 'crisi'];
        yield ['specie', 'specie'];
        yield ['Uomo', 'Uomini'];
        yield ['uovo', 'uova'];
        yield ['amico', 'amici'];
        yield ['problema', 'problemi'];
        yield ['zio', 'zii'];
        yield ['re', 're'];
        yield ['LIBRO', 'LIBRi'];
        yield ['cane', 'cani'];
        yield ['cicogna', 'cicogne'];
        yield ['cometa', 'comete'];
        yield ['socialismo', 'socialismi'];
        yield ['ciabatta', 'ciabatte'];
        yield ['BOSCO', 'BOSChi'];
    }

    #[DataProvider('plurals')]
    public function testItMakesThePlural(string $word, string $expected): void
    {
        self::assertSame($expected, new Italian()->plural($word));
    }

    public function testOnlyOneTakesTheSingular(): void
    {
        $italian = new Italian();

        self::assertTrue($italian->isPlural(0));
        self::assertFalse($italian->isPlural(1));
        self::assertFalse($italian->isPlural(-1));
        self::assertTrue($italian->isPlural(2));
    }
}
