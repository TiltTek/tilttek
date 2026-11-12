<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Grammar\ArticleKind;
use Tilttek\Generator\Grammar\French;
use Tilttek\Generator\Grammar\Gender;
use Tilttek\Generator\Grammar\Words;

#[CoversClass(French::class)]
#[CoversClass(Words::class)]
final class FrenchTest extends TestCase
{
    /**
     * @return iterable<array{string, string}>
     */
    public static function elisions(): iterable
    {
        yield 'article before a vowel' => ['le arbre et la épée', "l'arbre et l'épée"];
        yield 'before a mute h' => ['la herbe de homme', "l'herbe d'homme"];
        yield 'before an aspirated h' => ['le héros et la hache', 'le héros et la hache'];
        yield 'aspirated h in the plural' => ['de haches', 'de haches'];
        yield 'aspirated h before punctuation' => ['la hache, le hibou.', 'la hache, le hibou.'];
        yield 'aspirated h before several marks' => ['la hache?!', 'la hache?!'];
        yield 'a consonant with a cedilla' => ['le ça', 'le ça'];
        yield 'words that take no elision' => ['le onze et le yacht', 'le onze et le yacht'];
        yield 'conjunctions' => ['que il vienne lorsque elle part puisque on dort', "qu'il vienne lorsqu'elle part puisqu'on dort"];
        yield 'other short words' => ['je ai, me appelle, te aime, se use, ne ose, jusque ici, quoique on dise', "j'ai, m'appelle, t'aime, s'use, n'ose, jusqu'ici, quoiqu'on dise"];
        yield 'si before il' => ['si il pleut, si ils viennent, si elle part', "s'il pleut, s'ils viennent, si elle part"];
        yield 'words in capitals' => ['si IL pleut, de LE roi, à LES rois', "s'IL pleut, du roi, aux rois"];
        yield 'forms before a vowel' => ['ce arbre, ma épée, ta amie, sa histoire, ce hibou', 'cet arbre, mon épée, ton amie, son histoire, ce hibou'];
        yield 'contractions' => ['de le roi, de les rois, à le roi, à les rois', 'du roi, des rois, au roi, aux rois'];
        yield 'elision before contraction' => ['de le arbre, à le homme', "de l'arbre, à l'homme"];
        yield 'contraction before an aspirated h' => ['de le héros, à le hibou', 'du héros, au hibou'];
        yield 'capitals are kept' => ['Le arbre, De le roi, À les rois, Ce arbre, Ma épée, Que il, Si il', "L'arbre, Du roi, Aux rois, Cet arbre, Mon épée, Qu'il, S'il"];
        yield 'capital vowel' => ['la Épée', "l'Épée"];
        yield 'spaces are kept' => ["  le  arbre\nde le roi ", "  l'arbre\ndu roi "];
        yield 'a trailing word is left alone' => ['le ', 'le '];
        yield 'a word followed by nothing' => ['de le', 'du'];
        yield 'a consonant' => ['le roi de la ville', 'le roi de la ville'];
        yield 'an empty text' => ['', ''];
        yield 'a word kept as written' => ['de ^le roi, le ^onze, ^de le roi, ce ^arbre', 'de le roi, le onze, de le roi, ce arbre'];
        yield 'each word once' => ['que le arbre', "que l'arbre"];
    }

    #[DataProvider('elisions')]
    public function testItElidesAndContracts(string $text, string $expected): void
    {
        self::assertSame($expected, new French()->elide($text));
    }

    public function testAnApplicationAddsAspiratedWords(): void
    {
        $french = new French(['Hobgobelin']);

        self::assertSame('le hobgobelin et le héros', $french->elide('le hobgobelin et le héros'));
        self::assertSame("l'hobgobelin", new French()->elide('le hobgobelin'));
        self::assertSame('le hâbleur', new French(['HÂBLEUR'])->elide('le hâbleur'));
    }

    /**
     * @return iterable<array{string, ArticleKind, Gender, bool, string}>
     */
    public static function articles(): iterable
    {
        yield ['roi', ArticleKind::Definite, Gender::Masculine, false, 'le roi'];
        yield ['reine', ArticleKind::Definite, Gender::Feminine, false, 'la reine'];
        yield ['épée', ArticleKind::Definite, Gender::Feminine, false, "l'épée"];
        yield ['rois', ArticleKind::Definite, Gender::Masculine, true, 'les rois'];
        yield ['roi', ArticleKind::Indefinite, Gender::Masculine, false, 'un roi'];
        yield ['reine', ArticleKind::Indefinite, Gender::Feminine, false, 'une reine'];
        yield ['rois', ArticleKind::Indefinite, Gender::Feminine, true, 'des rois'];
        yield ['pain', ArticleKind::Partitive, Gender::Masculine, false, 'du pain'];
        yield ['eau', ArticleKind::Partitive, Gender::Feminine, false, "de l'eau"];
        yield ['or', ArticleKind::Partitive, Gender::Masculine, false, "de l'or"];
        yield ['bière', ArticleKind::Partitive, Gender::Feminine, false, 'de la bière'];
        yield ['épices', ArticleKind::Partitive, Gender::Feminine, true, 'des épices'];
        yield ['roi', ArticleKind::Demonstrative, Gender::Masculine, false, 'ce roi'];
        yield ['arbre', ArticleKind::Demonstrative, Gender::Neuter, false, 'cet arbre'];
        yield ['reine', ArticleKind::Demonstrative, Gender::Feminine, false, 'cette reine'];
        yield ['rois', ArticleKind::Demonstrative, Gender::Masculine, true, 'ces rois'];
    }

    #[DataProvider('articles')]
    public function testItPutsTheArticle(string $word, ArticleKind $kind, Gender $gender, bool $plural, string $expected): void
    {
        self::assertSame($expected, new French()->article($word, $kind, $gender, $plural));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function feminines(): iterable
    {
        yield ['grande', 'grande'];
        yield ['heureux', 'heureuse'];
        yield ['menteur', 'menteuse'];
        yield ['meilleur', 'meilleure'];
        yield ['Supérieur', 'Supérieure'];
        yield ['vif', 'vive'];
        yield ['fier', 'fière'];
        yield ['cruel', 'cruelle'];
        yield ['pareil', 'pareille'];
        yield ['ancien', 'ancienne'];
        yield ['bon', 'bonne'];
        yield ['muet', 'muette'];
        yield ['grand', 'grande'];
    }

    #[DataProvider('feminines')]
    public function testItMakesTheFeminine(string $word, string $expected): void
    {
        self::assertSame($expected, new French()->feminine($word));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function plurals(): iterable
    {
        yield ['bras', 'bras'];
        yield ['prix', 'prix'];
        yield ['nez', 'nez'];
        yield ['cheval', 'chevaux'];
        yield ['bal', 'bals'];
        yield ['Festival', 'Festivals'];
        yield ['travail', 'travaux'];
        yield ['détail', 'détails'];
        yield ['Émail', 'Émaux'];
        yield ['château', 'châteaux'];
        yield ['tuyau', 'tuyaux'];
        yield ['feu', 'feux'];
        yield ['pneu', 'pneus'];
        yield ['landau', 'landaus'];
        yield ['bijou', 'bijoux'];
        yield ['trou', 'trous'];
        yield ['roi', 'rois'];
    }

    #[DataProvider('plurals')]
    public function testItMakesThePlural(string $word, string $expected): void
    {
        self::assertSame($expected, new French()->plural($word));
    }

    public function testTwoAndMoreTakeThePlural(): void
    {
        $french = new French();

        self::assertFalse($french->isPlural(0));
        self::assertFalse($french->isPlural(1));
        self::assertFalse($french->isPlural(-1));
        self::assertTrue($french->isPlural(2));
        self::assertTrue($french->isPlural(-2));
    }
}
