<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Grammar\English;
use Tilttek\Generator\Grammar\French;
use Tilttek\Generator\Grammar\German;
use Tilttek\Generator\Grammar\Grammar;
use Tilttek\Generator\Grammar\Grammars;
use Tilttek\Generator\Grammar\Italian;
use Tilttek\Generator\Grammar\Portuguese;
use Tilttek\Generator\Grammar\Spanish;
use Tilttek\Generator\Grammar\Words;

#[CoversClass(Grammars::class)]
#[CoversClass(Words::class)]
final class GrammarsTest extends TestCase
{
    public function testSixLanguagesAreBuiltIn(): void
    {
        $grammars = new Grammars();

        self::assertInstanceOf(French::class, $grammars->for('fr'));
        self::assertInstanceOf(English::class, $grammars->for('en'));
        self::assertInstanceOf(Spanish::class, $grammars->for('es'));
        self::assertInstanceOf(German::class, $grammars->for('de'));
        self::assertInstanceOf(Italian::class, $grammars->for('it'));
        self::assertInstanceOf(Portuguese::class, $grammars->for('pt'));
        self::assertInstanceOf(French::class, $grammars->for('fr_CA'));
        self::assertInstanceOf(French::class, $grammars->for('FR-be'));
        self::assertInstanceOf(Spanish::class, $grammars->for('es_MX'));
        self::assertTrue($grammars->has('en_GB'));
        self::assertTrue($grammars->has('de_AT'));
        self::assertFalse($grammars->has('nl'));
        self::assertFalse($grammars->has(''));
    }

    public function testPortugueseOfPortugalHasItsOwnCount(): void
    {
        $grammars = new Grammars();

        self::assertFalse($grammars->for('pt')->isPlural(0));
        self::assertFalse($grammars->for('pt_BR')->isPlural(0));
        self::assertTrue($grammars->for('pt_PT')->isPlural(0));
        self::assertTrue($grammars->for('pt-pt')->isPlural(0));
        self::assertTrue($grammars->for('PT-PT')->isPlural(0));
        self::assertTrue($grammars->for('pt-PT-x-variant')->isPlural(0));
        self::assertSame('no castelo', $grammars->for('pt_PT')->elide('em o castelo'));
    }

    public function testAnApplicationAddsOrReplacesGrammars(): void
    {
        $belgian = new French(['hobgobelin']);
        $dutch = self::createStub(Grammar::class);
        $grammars = new Grammars(['fr_BE' => $belgian, 'nl' => $dutch, 'es' => new English()]);

        self::assertSame($belgian, $grammars->for('fr_BE'));
        self::assertSame($belgian, $grammars->for('fr-be'));
        self::assertNotSame($belgian, $grammars->for('fr_CA'));
        self::assertSame($dutch, $grammars->for('nl_BE'));
        self::assertInstanceOf(English::class, $grammars->for('es_AR'));
    }

    public function testALocaleWrittenAsGivenComesFirst(): void
    {
        $odd = new French();
        $canadian = new French();
        $grammars = new Grammars(['fr-ca' => $odd, 'fr_CA' => $canadian]);

        self::assertSame($odd, $grammars->for('fr-ca'));
        self::assertSame($canadian, $grammars->for('fr_CA'));
        self::assertSame($canadian, $grammars->for('FR-CA'));
    }

    public function testAnUnknownLanguageIsAnError(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('nl', 'no grammar is known for this language'));

        new Grammars()->for('nl');
    }

    public function testWordsAreCutAndGluedBack(): void
    {
        self::assertSame([['', 'le', 'roi', ''], [' ', '  ', "\n", '']], Words::split(" le  roi\n"));
        self::assertSame([['x'], ['']], Words::split('x'));
        self::assertSame([["le \xFF"], ['']], Words::split("le \xFF"));
        self::assertSame(" le  roi\n", Words::glue(...Words::split(" le  roi\n")));
        self::assertSame('Du', Words::sameCase('du', 'De'));
        self::assertSame('du', Words::sameCase('du', 'de'));
        self::assertSame('du', Words::sameCase('du', 'dE'));
        self::assertSame('Élan', Words::sameCase('élan', 'Été'));
        self::assertSame('x', Words::sameCase('x', '1'));
    }
}
