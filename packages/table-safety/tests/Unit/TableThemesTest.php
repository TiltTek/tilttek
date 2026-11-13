<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\TableThemes;
use Tilttek\TableSafety\Tests\Unit\Fixture\Catalogues;

#[CoversClass(TableThemes::class)]
final class TableThemesTest extends TestCase
{
    public function testItKnowsTheCatalogueAndTheAddedThemes(): void
    {
        $themes = new TableThemes(Catalogues::small(), ['clowns' => 'Clowns', 'deep-water' => 'Deep water']);

        self::assertTrue($themes->has('gore'));
        self::assertTrue($themes->has('clowns'));
        self::assertFalse($themes->has('war'));
        self::assertTrue($themes->isCustom('clowns'));
        self::assertFalse($themes->isCustom('gore'));
        self::assertSame(['war', 'snakes'], $themes->unknown(['gore', 'war', 'clowns', 'snakes']));
        self::assertSame([], $themes->unknown([]));
    }

    public function testALabelComesFromTheTableTheCatalogueOrTheKey(): void
    {
        $themes = new TableThemes(Catalogues::small(), ['clowns' => 'Clowns']);

        self::assertSame('Araignées', $themes->label('spiders', 'fr'));
        self::assertSame('Spiders', $themes->label('spiders', 'de'));
        self::assertSame('Spiders', $themes->label('spiders', 'de', ['es', 'en']));
        self::assertSame('Clowns', $themes->label('clowns', 'fr'));
        self::assertSame('war', $themes->label('war', 'fr'));
    }

    public function testKnownAnswersPass(): void
    {
        $themes = new TableThemes(Catalogues::small(), ['clowns' => 'Clowns']);
        $themes->assertKnown(['gore' => 'line', 'clowns' => 'veil']);
        $themes->assertKnown([]);

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The table does not know the theme "war".');

        $themes->assertKnown(['gore' => 'line', 'war' => 'veil', 'snakes' => 'line']);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidThemes(): iterable
    {
        yield 'a key' => [['Clowns' => 'Clowns'], '"Clowns" is not a theme key.'];
        yield 'a theme of the catalogue' => [['gore' => 'Blood'], 'The theme "gore" is already in the catalogue.'];
        yield 'an empty label' => [['clowns' => '  '], 'The label of the theme "clowns" has 1 to 120 characters.'];
        yield 'a long label' => [['clowns' => str_repeat('é', 121)], 'The label of the theme "clowns" has 1 to 120 characters.'];
    }

    /**
     * @param array<string, string> $custom
     */
    #[DataProvider('invalidThemes')]
    public function testAnAddedThemeIsChecked(array $custom, string $message): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains($message);

        new TableThemes(Catalogues::small(), $custom);
    }

    public function testALabelOfTheLongestLengthIsTaken(): void
    {
        $themes = new TableThemes(Catalogues::small(), ['clowns' => str_repeat('é', 120), 'x' => 'X']);

        self::assertTrue($themes->has('x'));
    }

    public function testTheAddedThemesAreBounded(): void
    {
        $custom = [];

        for ($i = 0; $i < TableThemes::MAX_CUSTOM; ++$i) {
            $custom['t'.$i] = 'Theme '.$i;
        }

        self::assertTrue(new TableThemes(Catalogues::small(), $custom)->has('t99'));

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A table adds at most 100 themes.');

        new TableThemes(Catalogues::small(), [...$custom, 'one-more' => 'One more']);
    }
}
