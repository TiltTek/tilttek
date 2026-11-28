<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Attribute;
use Tilttek\RpgSystems\DiceExpression;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Exception\UnknownRecord;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Localisation;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Systems;
use Tilttek\RpgSystems\Term;
use Tilttek\RpgSystems\Translations;

#[CoversClass(GameSystem::class)]
#[CoversClass(Systems::class)]
#[CoversClass(Localisation::class)]
#[CoversClass(UnknownRecord::class)]
final class GameSystemTest extends TestCase
{
    private Provenance $own;

    private Provenance $open;

    protected function setUp(): void
    {
        $this->own = new Provenance('Lanterns', Licence::Owned);
        $this->open = new Provenance('Open terms', Licence::CcBy40, 'Terms from Open terms, CC BY 4.0.');
    }

    public function testRecordsAreLookedUpByKeyInTheirOrder(): void
    {
        $system = $this->system();

        self::assertSame('lanterns', $system->key);
        self::assertSame('Lanterns', $system->name());
        self::assertSame('Lanternes', $system->name('fr_CA'));
        self::assertSame('1.0', $system->version);
        self::assertSame('Grit', $system->attribute('grit')->names->in('en'));
        self::assertTrue($system->hasAttribute('wits'));
        self::assertFalse($system->hasAttribute('glow'));
        self::assertSame(['grit', 'wits'], array_map(static fn (Attribute $attribute): string => $attribute->key, $system->attributes()));
        self::assertSame('2d6', $system->dice('action')->notation);
        self::assertTrue($system->hasDice('action'));
        self::assertFalse($system->hasDice('check'));
        self::assertSame(['action'], array_map(static fn (DiceExpression $dice): string => $dice->key, $system->diceExpressions()));
        self::assertSame('Shaken', $system->term('condition.shaken')->names->in('en'));
        self::assertTrue($system->hasTerm('range.near'));
        self::assertFalse($system->hasTerm('range.far'));
        self::assertSame(['condition.shaken', 'range.near', 'condition.lit', 'free'], $this->keys($system->terms()));
        self::assertSame(['condition.shaken', 'condition.lit'], $this->keys($system->terms('condition')));
        self::assertSame([], $system->terms('damage'));
        self::assertSame(['condition', 'range'], $system->categories());
    }

    public function testAnUnknownKeyIsRefusedWithTheSystemNamed(): void
    {
        $system = $this->system();

        foreach ([
            'attribute' => static fn (): Attribute => $system->attribute('glow'),
            'dice expression' => static fn (): DiceExpression => $system->dice('check'),
            'term' => static fn (): Term => $system->term('range.far'),
        ] as $kind => $lookup) {
            try {
                $lookup();
                self::fail('An unknown key was found.');
            } catch (UnknownRecord $exception) {
                self::assertMatchesRegularExpression(\sprintf('/^The system "lanterns" has no %s with the key "[a-z.]+"\.$/', $kind), $exception->getMessage());
            }
        }
    }

    public function testASystemRefusesTwoRecordsOfTheSameKey(): void
    {
        $grit = new Attribute('grit', Translations::of(['en' => 'Grit']), $this->own);

        foreach ([
            'attribute' => [[$grit, $grit], [], []],
            'dice expression' => [[], [new DiceExpression('a', '1d6', Translations::of(['en' => 'A']), $this->own), new DiceExpression('a', '1d8', Translations::of(['en' => 'B']), $this->own)], []],
            'term' => [[], [], [new Term('t', Translations::of(['en' => 'T']), $this->own), new Term('t', Translations::of(['en' => 'U']), $this->own)]],
        ] as $kind => [$attributes, $dice, $terms]) {
            try {
                new GameSystem('x', Translations::of(['en' => 'X']), $this->own, null, $attributes, $dice, $terms);
                self::fail('Two records of the same key were accepted.');
            } catch (InvalidRecord $exception) {
                self::assertMatchesRegularExpression(\sprintf('/^The system "x" holds two of the %s "[a-z]+"\.$/', $kind), $exception->getMessage());
            }
        }
    }

    public function testASystemHasANameAVersionAndBoundedRecords(): void
    {
        foreach ([
            [static fn (Provenance $own): GameSystem => new GameSystem('x', new Translations(), $own), 'The system "x" has no name.'],
            [static fn (Provenance $own): GameSystem => new GameSystem('x', Translations::of(['en' => 'X']), $own, ''), 'The version of the system "x" is required.'],
            [static fn (Provenance $own): GameSystem => new GameSystem('X', Translations::of(['en' => 'X']), $own), 'The key of a system "X" must be'],
        ] as [$make, $message]) {
            try {
                $make($this->own);
                self::fail('The system was accepted.');
            } catch (InvalidRecord $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }

        $term = new Term('t', Translations::of(['en' => 'T']), $this->own);
        $terms = array_map(static fn (int $index): Term => new Term('t'.$index, $term->names, $term->provenance), range(1, GameSystem::MAX_RECORDS - 1));
        $attribute = new Attribute('a', $term->names, $this->own);

        self::assertCount(GameSystem::MAX_RECORDS - 1, new GameSystem('x', $term->names, $this->own, null, [$attribute], [], $terms)->terms());

        $this->expectExceptionMessageIsOrContains('The system "x" holds more than 10000 records.');

        new GameSystem('x', $term->names, $this->own, null, [$attribute], [new DiceExpression('d', '1d6', $term->names, $this->own)], $terms);
    }

    public function testEverySourceIsListedOnceWithTheTranslationsOfALocale(): void
    {
        $spanish = new Provenance('Traducción', Licence::CcBy40, 'Spanish texts, CC BY 4.0.');
        $canadian = new Provenance('Traduction québécoise', Licence::CcBy40, 'Quebec texts, CC BY 4.0.');
        $system = $this->system()
            ->localised(new Localisation('es', $spanish, 'Linternas', ['grit' => ['name' => 'Temple']]))
            ->localised(new Localisation('fr-ca', $canadian, terms: ['range.near' => ['name' => 'Proche']]));

        self::assertSame([$this->own, $this->open], $this->system()->provenances());
        self::assertSame([$this->own, $this->open, $spanish, $canadian], $system->provenances());
        self::assertSame([$this->own, $this->open, $spanish], $system->provenances('es_MX'));
        self::assertSame([$this->own, $this->open, $canadian], $system->provenances('fr-CA'));
        self::assertSame([$this->own, $this->open], $system->provenances('fr'));
        self::assertSame(['es' => [$spanish], 'fr_CA' => [$canadian]], $system->translationSources);
        self::assertSame(['fr_CA' => [$canadian, $canadian]], new GameSystem('x', Translations::of(['en' => 'X']), $this->own, translationSources: ['fr-ca' => [$canadian], 'fr_CA' => [$canadian]])->translationSources);
    }

    public function testALocalisationLaysItsTextsOverTheSystem(): void
    {
        $source = new Provenance('Traducción', Licence::CcBy40, 'Spanish texts, CC BY 4.0.');
        $system = $this->system()->localised(new Localisation(
            'es',
            $source,
            'Linternas',
            ['grit' => ['name' => 'Temple', 'abbreviation' => 'TMP', 'description' => 'Aguantar.'], 'wits' => ['abbreviation' => 'ING']],
            ['action' => 'Tirada de acción'],
            ['condition.shaken' => ['name' => 'Conmocionado', 'description' => 'Inestable.'], 'range.near' => ['description' => 'Cerca.']],
        ))->localised(new Localisation('es', $source));

        self::assertSame('Linternas', $system->name('es'));
        self::assertSame('Lanterns', $system->name());
        self::assertSame('1.0', $system->version);
        $grit = $system->attribute('grit');
        self::assertSame(['en' => 'Grit', 'es' => 'Temple'], $grit->names->texts);
        self::assertSame(['es' => 'TMP'], $grit->abbreviations->texts);
        self::assertSame(['es' => 'Aguantar.'], $grit->descriptions->texts);
        self::assertSame([1, 6], [$grit->minimum, $grit->maximum]);
        self::assertSame($this->own, $grit->provenance);
        self::assertSame(['en' => 'Wits'], $system->attribute('wits')->names->texts);
        self::assertSame(['en' => 'WIT', 'es' => 'ING'], $system->attribute('wits')->abbreviations->texts);
        self::assertSame(['en' => 'Action', 'es' => 'Tirada de acción'], $system->dice('action')->names->texts);
        self::assertSame('2d6', $system->dice('action')->notation);
        self::assertSame($this->own, $system->dice('action')->provenance);
        $shaken = $system->term('condition.shaken');
        self::assertSame(['en' => 'Shaken', 'es' => 'Conmocionado'], $shaken->names->texts);
        self::assertSame(['es' => 'Inestable.'], $shaken->descriptions->texts);
        self::assertSame('condition', $shaken->category);
        self::assertSame($this->own, $shaken->provenance);
        self::assertSame(['en' => 'Near'], $system->term('range.near')->names->texts);
        self::assertSame(['es' => 'Cerca.'], $system->term('range.near')->descriptions->texts);
        self::assertSame($this->open, $system->term('range.near')->provenance);
        self::assertSame(['condition.shaken', 'range.near', 'condition.lit', 'free'], $this->keys($system->terms()));
        self::assertSame(['es' => [$source, $source]], $system->translationSources);

        $this->expectException(UnknownRecord::class);

        $system->localised(new Localisation('es', $source, dice: ['check' => 'Prueba']));
    }

    public function testALocalisationOfAnUnknownTermOrAttributeIsRefused(): void
    {
        $source = new Provenance('Traducción', Licence::CcBy40, 'Spanish texts, CC BY 4.0.');

        try {
            $this->system()->localised(new Localisation('es', $source, attributes: ['glow' => ['name' => 'Brillo']]));
            self::fail('An unknown attribute was translated.');
        } catch (UnknownRecord $unknownRecord) {
            self::assertSame('The system "lanterns" has no attribute with the key "glow".', $unknownRecord->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The system "lanterns" has no term with the key "far".');

        $this->system()->localised(new Localisation('es', $source, terms: ['far' => ['name' => 'Lejos']]));
    }

    public function testSystemsAreLookedUpByKey(): void
    {
        $lanterns = $this->system();
        $other = new GameSystem('other', Translations::of(['en' => 'Other']), $this->own);
        $systems = new Systems($lanterns);

        self::assertSame($lanterns, $systems->get('lanterns'));
        self::assertTrue($systems->has('lanterns'));
        self::assertFalse($systems->has('other'));
        self::assertSame([$lanterns, $other], $systems->with($other)->all());
        $replaced = new GameSystem('lanterns', Translations::of(['en' => 'Replaced']), $this->own);
        self::assertSame([$replaced, $other], $systems->with($other)->with($replaced)->all());
        self::assertSame([$lanterns], $systems->all());

        try {
            $systems->get('other');
            self::fail('An unknown system was found.');
        } catch (UnknownRecord $unknownRecord) {
            self::assertSame('No system has the key "other".', $unknownRecord->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('Two systems have the key "lanterns".');

        new Systems($lanterns, $replaced);
    }

    private function system(): GameSystem
    {
        return new GameSystem(
            'lanterns',
            Translations::of(['en' => 'Lanterns', 'fr' => 'Lanternes']),
            $this->own,
            '1.0',
            [
                new Attribute('grit', Translations::of(['en' => 'Grit']), $this->own, minimum: 1, maximum: 6),
                new Attribute('wits', Translations::of(['en' => 'Wits']), $this->own, Translations::of(['en' => 'WIT'])),
            ],
            [new DiceExpression('action', '2d6', Translations::of(['en' => 'Action']), $this->own)],
            [
                new Term('condition.shaken', Translations::of(['en' => 'Shaken']), $this->own, 'condition'),
                new Term('range.near', Translations::of(['en' => 'Near']), $this->open, 'range'),
                new Term('condition.lit', Translations::of(['en' => 'Lit']), $this->own, 'condition'),
                new Term('free', Translations::of(['en' => 'Free']), $this->own),
            ],
        );
    }

    /**
     * @param list<Term> $terms
     *
     * @return list<string>
     */
    private function keys(array $terms): array
    {
        return array_map(static fn (Term $term): string => $term->key, $terms);
    }
}
