<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Grammar\ArticleKind;
use Tilttek\Generator\Grammar\Gender;
use Tilttek\Generator\Grammar\Spanish;
use Tilttek\Generator\Grammar\Words;

#[CoversClass(Spanish::class)]
#[CoversClass(Words::class)]
final class SpanishTest extends TestCase
{
    /**
     * @return iterable<array{string, string}>
     */
    public static function elisions(): iterable
    {
        yield 'contractions' => ['la espada de el rey va a el castillo', 'la espada del rey va al castillo'];
        yield 'a stressed a' => ['la agua, una hacha, la águila', 'el agua, un hacha, el águila'];
        yield 'a stressed a before punctuation' => ['bebe la agua.', 'bebe el agua.'];
        yield 'then the contraction' => ['de la agua, a la alma', 'del agua, al alma'];
        yield 'an unstressed a' => ['la armadura, una amiga, las aguas', 'la armadura, una amiga, las aguas'];
        yield 'capitals are kept' => ['De el rey, A el castillo, La agua, Una hacha', 'Del rey, Al castillo, El agua, Un hacha'];
        yield 'a name that starts with El' => ['de El Dorado, De El Dorado', 'de El Dorado, Del Dorado'];
        yield 'the article before a name' => ['la espada de el Cid', 'la espada del Cid'];
        yield 'a pair alone' => ['de el', 'del'];
        yield 'a word in capitals' => ['la ÁGUILA', 'el ÁGUILA'];
        yield 'the pronoun' => ['de él, a él', 'de él, a él'];
        yield 'spaces are kept' => ["  de  el\nrey ", "  del\nrey "];
        yield 'a trailing word is left alone' => ['la', 'la'];
        yield 'an empty text' => ['', ''];
        yield 'a word kept as written' => ['de ^el rey, la ^agua, ^de el', 'de el rey, la agua, de el'];
    }

    #[DataProvider('elisions')]
    public function testItContractsAndChoosesTheArticle(string $text, string $expected): void
    {
        self::assertSame($expected, new Spanish()->elide($text));
    }

    public function testAnApplicationAddsWordsWithAStressedA(): void
    {
        self::assertSame('el ánima', new Spanish(['ÁNIMA'])->elide('la ánima'));
        self::assertSame('la ánima, el agua', new Spanish()->elide('la ánima, la agua'));
    }

    /**
     * @return iterable<array{string, ArticleKind, Gender, bool, string}>
     */
    public static function articles(): iterable
    {
        yield ['rey', ArticleKind::Definite, Gender::Masculine, false, 'el rey'];
        yield ['reina', ArticleKind::Definite, Gender::Feminine, false, 'la reina'];
        yield ['agua', ArticleKind::Definite, Gender::Feminine, false, 'el agua'];
        yield ['bueno', ArticleKind::Definite, Gender::Neuter, false, 'lo bueno'];
        yield ['reyes', ArticleKind::Definite, Gender::Masculine, true, 'los reyes'];
        yield ['reinas', ArticleKind::Definite, Gender::Feminine, true, 'las reinas'];
        yield ['rey', ArticleKind::Indefinite, Gender::Masculine, false, 'un rey'];
        yield ['espada', ArticleKind::Indefinite, Gender::Feminine, false, 'una espada'];
        yield ['hacha', ArticleKind::Indefinite, Gender::Feminine, false, 'un hacha'];
        yield ['reyes', ArticleKind::Indefinite, Gender::Masculine, true, 'unos reyes'];
        yield ['espadas', ArticleKind::Indefinite, Gender::Feminine, true, 'unas espadas'];
        yield ['pan', ArticleKind::Partitive, Gender::Masculine, false, 'pan'];
        yield ['castillo', ArticleKind::Demonstrative, Gender::Masculine, false, 'este castillo'];
        yield ['torre', ArticleKind::Demonstrative, Gender::Feminine, false, 'esta torre'];
        yield ['castillos', ArticleKind::Demonstrative, Gender::Masculine, true, 'estos castillos'];
        yield ['torres', ArticleKind::Demonstrative, Gender::Feminine, true, 'estas torres'];
    }

    #[DataProvider('articles')]
    public function testItPutsTheArticle(string $word, ArticleKind $kind, Gender $gender, bool $plural, string $expected): void
    {
        self::assertSame($expected, new Spanish()->article($word, $kind, $gender, $plural));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function feminines(): iterable
    {
        yield ['viejo', 'vieja'];
        yield ['trabajador', 'trabajadora'];
        yield ['alemán', 'alemana'];
        yield ['inglés', 'inglesa'];
        yield ['glotón', 'glotona'];
        yield ['bailarín', 'bailarina'];
        yield ['grande', 'grande'];
        yield ['feliz', 'feliz'];
        yield ['mejor', 'mejor'];
        yield ['Superior', 'Superior'];
        yield ['cortés', 'cortés'];
        yield ['común', 'común'];
        yield ['estándar', 'estándar'];
        yield ['INGLÉS', 'INGLESa'];
        yield ['VIEJO', 'VIEJa'];
    }

    #[DataProvider('feminines')]
    public function testItMakesTheFeminine(string $word, string $expected): void
    {
        self::assertSame($expected, new Spanish()->feminine($word));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function plurals(): iterable
    {
        yield ['casa', 'casas'];
        yield ['café', 'cafés'];
        yield ['sofá', 'sofás'];
        yield ['rubí', 'rubíes'];
        yield ['tabú', 'tabúes'];
        yield ['luz', 'luces'];
        yield ['lápiz', 'lápices'];
        yield ['LUZ', 'LUces'];
        yield ['canción', 'canciones'];
        yield ['alemán', 'alemanes'];
        yield ['autobús', 'autobuses'];
        yield ['inglés', 'ingleses'];
        yield ['país', 'países'];
        yield ['baúl', 'baúles'];
        yield ['NACIÓN', 'NACIONes'];
        yield ['lunes', 'lunes'];
        yield ['crisis', 'crisis'];
        yield ['tórax', 'tórax'];
        yield ['mes', 'meses'];
        yield ['dios', 'dioses'];
        yield ['fax', 'faxes'];
        yield ['joven', 'jóvenes'];
        yield ['examen', 'exámenes'];
        yield ['origen', 'orígenes'];
        yield ['Imagen', 'Imágenes'];
        yield ['JOVEN', 'JÓVENes'];
        yield ['pan', 'panes'];
        yield ['flor', 'flores'];
        yield ['ciudad', 'ciudades'];
        yield ['árbol', 'árboles'];
        yield ['rey', 'reyes'];
        yield ['carácter', 'caracteres'];
        yield ['Régimen', 'Regímenes'];
        yield ['RÉGIMEN', 'Regímenes'];
        yield ['PAÍS', 'PAÍSes'];
        yield ['césped', 'céspedes'];
        yield ['liquen', 'líquenes'];
        yield ['orden', 'órdenes'];
        yield ['cárdigan', 'cárdiganes'];
        yield ['RUBÍ', 'RUBÍes'];
    }

    #[DataProvider('plurals')]
    public function testItMakesThePlural(string $word, string $expected): void
    {
        self::assertSame($expected, new Spanish()->plural($word));
    }

    public function testOnlyOneTakesTheSingular(): void
    {
        $spanish = new Spanish();

        self::assertTrue($spanish->isPlural(0));
        self::assertFalse($spanish->isPlural(1));
        self::assertFalse($spanish->isPlural(-1));
        self::assertTrue($spanish->isPlural(2));
    }
}
