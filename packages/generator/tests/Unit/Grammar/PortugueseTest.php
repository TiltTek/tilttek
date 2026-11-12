<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Grammar\ArticleKind;
use Tilttek\Generator\Grammar\Gender;
use Tilttek\Generator\Grammar\Portuguese;
use Tilttek\Generator\Grammar\Words;

#[CoversClass(Portuguese::class)]
#[CoversClass(Words::class)]
final class PortugueseTest extends TestCase
{
    /**
     * @return iterable<array{string, string}>
     */
    public static function contractions(): iterable
    {
        yield 'de' => ['a espada de o rei, de a rainha, de os reis, de as torres', 'a espada do rei, da rainha, dos reis, das torres'];
        yield 'em' => ['em o castelo, em a torre, em os bosques, em as ilhas', 'no castelo, na torre, nos bosques, nas ilhas'];
        yield 'a' => ['vai a o castelo, a a cidade, a os reis, a as portas', 'vai ao castelo, à cidade, aos reis, às portas'];
        yield 'por' => ['por o rio, por a ponte, por os campos, por as ruas', 'pelo rio, pela ponte, pelos campos, pelas ruas'];
        yield 'demonstratives' => ['de este livro, em essa casa, de aquele homem, a aquele rei, a aqueles reis, a aquelas portas, a aquela porta, em isto e a aquilo também', 'deste livro, nessa casa, daquele homem, àquele rei, àqueles reis, àquelas portas, àquela porta, nisto e àquilo também'];
        yield 'capitals are kept' => ['Em o castelo, De a rainha, A a cidade, POR O rio', 'No castelo, Da rainha, À cidade, Pelo rio'];
        yield 'other words are left alone' => ['com o rei, para a torre, a espada', 'com o rei, para a torre, a espada'];
        yield 'spaces are kept' => ["  em  o\ncastelo ", "  no\ncastelo "];
        yield 'an empty text' => ['', ''];
        yield 'a word kept as written' => ['de ^o rei, ^em a torre', 'de o rei, em a torre'];
    }

    #[DataProvider('contractions')]
    public function testItContracts(string $text, string $expected): void
    {
        self::assertSame($expected, new Portuguese()->elide($text));
    }

    /**
     * @return iterable<array{string, ArticleKind, Gender, bool, string}>
     */
    public static function articles(): iterable
    {
        yield ['rei', ArticleKind::Definite, Gender::Masculine, false, 'o rei'];
        yield ['espada', ArticleKind::Definite, Gender::Feminine, false, 'a espada'];
        yield ['reis', ArticleKind::Definite, Gender::Masculine, true, 'os reis'];
        yield ['espadas', ArticleKind::Definite, Gender::Feminine, true, 'as espadas'];
        yield ['rei', ArticleKind::Indefinite, Gender::Masculine, false, 'um rei'];
        yield ['espada', ArticleKind::Indefinite, Gender::Feminine, false, 'uma espada'];
        yield ['reis', ArticleKind::Indefinite, Gender::Masculine, true, 'uns reis'];
        yield ['espadas', ArticleKind::Indefinite, Gender::Feminine, true, 'umas espadas'];
        yield ['pão', ArticleKind::Partitive, Gender::Masculine, false, 'pão'];
        yield ['castelo', ArticleKind::Demonstrative, Gender::Masculine, false, 'este castelo'];
        yield ['torre', ArticleKind::Demonstrative, Gender::Feminine, false, 'esta torre'];
        yield ['castelos', ArticleKind::Demonstrative, Gender::Masculine, true, 'estes castelos'];
        yield ['torres', ArticleKind::Demonstrative, Gender::Feminine, true, 'estas torres'];
    }

    #[DataProvider('articles')]
    public function testItPutsTheArticle(string $word, ArticleKind $kind, Gender $gender, bool $plural, string $expected): void
    {
        self::assertSame($expected, new Portuguese()->article($word, $kind, $gender, $plural));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function feminines(): iterable
    {
        yield ['velho', 'velha'];
        yield ['trabalhador', 'trabalhadora'];
        yield ['alemão', 'alemã'];
        yield ['português', 'portuguesa'];
        yield ['europeu', 'europeia'];
        yield ['cru', 'crua'];
        yield ['leão', 'leoa'];
        yield ['Ladrão', 'Ladra'];
        yield ['judeu', 'judia'];
        yield ['feliz', 'feliz'];
        yield ['grande', 'grande'];
        yield ['melhor', 'melhor'];
        yield ['Cortês', 'Cortês'];
        yield ['LADRÃO', 'Ladra'];
        yield ['VELHO', 'VELHa'];
    }

    #[DataProvider('feminines')]
    public function testItMakesTheFeminine(string $word, string $expected): void
    {
        self::assertSame($expected, new Portuguese()->feminine($word));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function plurals(): iterable
    {
        yield ['casa', 'casas'];
        yield ['irmã', 'irmãs'];
        yield ['mãe', 'mães'];
        yield ['pé', 'pés'];
        yield ['canção', 'canções'];
        yield ['homem', 'homens'];
        yield ['jardim', 'jardins'];
        yield ['mar', 'mares'];
        yield ['luz', 'luzes'];
        yield ['mês', 'meses'];
        yield ['português', 'portugueses'];
        yield ['gás', 'gases'];
        yield ['país', 'países'];
        yield ['deus', 'deuses'];
        yield ['lápis', 'lápis'];
        yield ['vírus', 'vírus'];
        yield ['animal', 'animais'];
        yield ['azul', 'azuis'];
        yield ['papel', 'papéis'];
        yield ['anzol', 'anzóis'];
        yield ['HOTEL', 'HOTÉis'];
        yield ['nível', 'níveis'];
        yield ['álcool', 'álcoois'];
        yield ['barril', 'barris'];
        yield ['fácil', 'fáceis'];
        yield ['tórax', 'tórax'];
        yield ['hífen', 'hífens'];
        yield ['clube', 'clubes'];
        yield ['robot', 'robots'];
        yield ['pão', 'pães'];
        yield ['Mão', 'Mãos'];
        yield ['alemão', 'alemães'];
        yield ['mal', 'males'];
        yield ['MÃO', 'Mãos'];
        yield ['obús', 'obuses'];
        yield ['PAÍS', 'PAÍSes'];
        yield ['pássaro', 'pássaros'];
        yield ['CASA', 'CASAs'];
        yield ['MAR', 'MARes'];
    }

    #[DataProvider('plurals')]
    public function testItMakesThePlural(string $word, string $expected): void
    {
        self::assertSame($expected, new Portuguese()->plural($word));
    }

    public function testZeroTakesTheSingularInBrazil(): void
    {
        $portuguese = new Portuguese();

        self::assertFalse($portuguese->isPlural(0));
        self::assertFalse($portuguese->isPlural(1));
        self::assertFalse($portuguese->isPlural(-1));
        self::assertTrue($portuguese->isPlural(2));
        self::assertTrue($portuguese->isPlural(-2));
    }

    public function testOnlyOneTakesTheSingularInPortugal(): void
    {
        $portuguese = new Portuguese(zeroIsSingular: false);

        self::assertTrue($portuguese->isPlural(0));
        self::assertFalse($portuguese->isPlural(1));
        self::assertFalse($portuguese->isPlural(-1));
        self::assertTrue($portuguese->isPlural(2));
    }
}
