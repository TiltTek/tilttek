<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Grammar\ArticleKind;
use Tilttek\Generator\Grammar\Gender;
use Tilttek\Generator\Grammar\German;
use Tilttek\Generator\Grammar\Words;

#[CoversClass(German::class)]
#[CoversClass(Words::class)]
final class GermanTest extends TestCase
{
    /**
     * @return iterable<array{string, string}>
     */
    public static function contractions(): iterable
    {
        yield 'with dem' => ['an dem Fluss, bei dem Schmied, in dem Wald, von dem Berg, zu dem Turm', 'am Fluss, beim Schmied, im Wald, vom Berg, zum Turm'];
        yield 'with das and der' => ['an das Tor, in das Dorf, zu der Burg', 'ans Tor, ins Dorf, zur Burg'];
        yield 'capitals are kept' => ['Zu dem Turm! IN DAS Dorf', 'Zum Turm! Ins Dorf'];
        yield 'other pairs are left alone' => ['mit dem Schwert, zu den Toren, von der Insel', 'mit dem Schwert, zu den Toren, von der Insel'];
        yield 'spaces are kept' => ["  in  dem\nWald ", "  im\nWald "];
        yield 'a pair alone' => ['zu dem', 'zum'];
        yield 'an empty text' => ['', ''];
        yield 'a word kept as written' => ['zu ^der Zeit, ^in das Haus', 'zu der Zeit, in das Haus'];
    }

    #[DataProvider('contractions')]
    public function testItContractsThePrepositionAndTheArticle(string $text, string $expected): void
    {
        self::assertSame($expected, new German()->elide($text));
    }

    /**
     * @return iterable<array{string, ArticleKind, Gender, bool, string}>
     */
    public static function articles(): iterable
    {
        yield ['Drache', ArticleKind::Definite, Gender::Masculine, false, 'der Drache'];
        yield ['Burg', ArticleKind::Definite, Gender::Feminine, false, 'die Burg'];
        yield ['Schwert', ArticleKind::Definite, Gender::Neuter, false, 'das Schwert'];
        yield ['Schwerter', ArticleKind::Definite, Gender::Neuter, true, 'die Schwerter'];
        yield ['Drache', ArticleKind::Indefinite, Gender::Masculine, false, 'ein Drache'];
        yield ['Burg', ArticleKind::Indefinite, Gender::Feminine, false, 'eine Burg'];
        yield ['Schwert', ArticleKind::Indefinite, Gender::Neuter, false, 'ein Schwert'];
        yield ['Burgen', ArticleKind::Indefinite, Gender::Feminine, true, 'Burgen'];
        yield ['Wasser', ArticleKind::Partitive, Gender::Neuter, false, 'Wasser'];
        yield ['Drache', ArticleKind::Demonstrative, Gender::Masculine, false, 'dieser Drache'];
        yield ['Burg', ArticleKind::Demonstrative, Gender::Feminine, false, 'diese Burg'];
        yield ['Schwert', ArticleKind::Demonstrative, Gender::Neuter, false, 'dieses Schwert'];
        yield ['Drachen', ArticleKind::Demonstrative, Gender::Masculine, true, 'diese Drachen'];
    }

    #[DataProvider('articles')]
    public function testItPutsTheArticle(string $word, ArticleKind $kind, Gender $gender, bool $plural, string $expected): void
    {
        self::assertSame($expected, new German()->article($word, $kind, $gender, $plural));
    }

    public function testAnAdjectiveKeepsItsForm(): void
    {
        self::assertSame('alt', new German()->feminine('alt'));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function plurals(): iterable
    {
        yield ['Blume', 'Blumen'];
        yield ['Lehrerin', 'Lehrerinnen'];
        yield ['Zeitung', 'Zeitungen'];
        yield ['Freiheit', 'Freiheiten'];
        yield ['Möglichkeit', 'Möglichkeiten'];
        yield ['Mannschaft', 'Mannschaften'];
        yield ['Legion', 'Legionen'];
        yield ['Universität', 'Universitäten'];
        yield ['Musik', 'Musiken'];
        yield ['Bäckerei', 'Bäckereien'];
        yield ['Ritter', 'Ritter'];
        yield ['Würfel', 'Würfel'];
        yield ['Wagen', 'Wagen'];
        yield ['Mädchen', 'Mädchen'];
        yield ['Fräulein', 'Fräulein'];
        yield ['Ergebnis', 'Ergebnisse'];
        yield ['Bus', 'Busse'];
        yield ['Museum', 'Museen'];
        yield ['Auto', 'Autos'];
        yield ['Oma', 'Omas'];
        yield ['Kiwi', 'Kiwis'];
        yield ['Uhu', 'Uhus'];
        yield ['Hobby', 'Hobbys'];
        yield ['Tag', 'Tage'];
        yield ['Zwerg', 'Zwerge'];
        yield ['Fuß', 'Füße'];
        yield ['Wolf', 'Wölfe'];
        yield ['Schwester', 'Schwestern'];
        yield ['KIND', 'Kinder'];
        yield ['TÜR', 'Türen'];
        yield ['Berg', 'Berge'];
        yield ['Ring', 'Ringe'];
        yield ['Stein', 'Steine'];
        yield ['Puma', 'Pumas'];
        yield ['Zentrum', 'Zentren'];
        yield ['Raum', 'Räume'];
        yield ['KÖNIGIN', 'KÖNIGINnen'];
        yield ['ZEITUNG', 'ZEITUNGen'];
    }

    #[DataProvider('plurals')]
    public function testItMakesThePlural(string $word, string $expected): void
    {
        self::assertSame($expected, new German()->plural($word));
    }

    public function testOnlyOneTakesTheSingular(): void
    {
        $german = new German();

        self::assertTrue($german->isPlural(0));
        self::assertFalse($german->isPlural(1));
        self::assertFalse($german->isPlural(-1));
        self::assertTrue($german->isPlural(2));
    }
}
