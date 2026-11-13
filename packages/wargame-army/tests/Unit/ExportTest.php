<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\ArmyValidator;
use Tilttek\WargameArmy\Catalog\InMemoryCatalogueProvider;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Contract\Constraint;
use Tilttek\WargameArmy\Costing;
use Tilttek\WargameArmy\Detachment;
use Tilttek\WargameArmy\DetachmentType;
use Tilttek\WargameArmy\Export\HtmlExport;
use Tilttek\WargameArmy\Export\Sheet;
use Tilttek\WargameArmy\Export\TextExport;
use Tilttek\WargameArmy\Option;
use Tilttek\WargameArmy\OptionGroup;
use Tilttek\WargameArmy\Rule;
use Tilttek\WargameArmy\Slot;
use Tilttek\WargameArmy\Tests\Unit\Fixture\Lists;
use Tilttek\WargameArmy\Tests\Unit\Fixture\Skirmish;
use Tilttek\WargameArmy\UnitEntry;
use Tilttek\WargameArmy\UnitSelection;
use Tilttek\WargameArmy\Validation;
use Tilttek\WargameArmy\Violation;

#[CoversClass(TextExport::class)]
#[CoversClass(HtmlExport::class)]
#[CoversClass(Sheet::class)]
final class ExportTest extends TestCase
{
    public function testAValidListAsText(): void
    {
        self::assertSame(<<<'TEXT'
            Ember Wardens
            Ember Vale Skirmish 2026.1 — 427 / 500 points

            Detachment 1: Wardens (Vanguard) — 347 points
              - Captain (1 model) — 75 points
                  Bow ×1
                  Banner ×1
              - Scouts (6 models) — 72 points
                  Long rifle ×1
              - Guards (5 models) — 80 points
              - Ogre (1 model) — 120 points

            Detachment 2: Raid — 80 points
              - Guards (5 models) — 80 points

            TEXT, new TextExport()->render(Lists::valid(), Skirmish::catalogue()));
    }

    public function testABrokenListAsTextWithItsViolations(): void
    {
        $catalogue = Skirmish::catalogue();
        $validation = new ArmyValidator(new InMemoryCatalogueProvider($catalogue))->validate(Lists::broken());
        $text = new TextExport()->render(Lists::broken(), $catalogue, $validation);

        self::assertStringStartsWith("Bad <list>\nEmber Vale Skirmish 2025.9 — 579 / 300 points\n\nDetachment 1: Vanguard — 345 points\n  - Captain (2 models) — 105 points\n      Banner ×3\n      Horse ×1\n      axe ×1\n", $text);
        self::assertStringContainsString("  - dragon (1 model) — 0 points\n\nDetachment 2: Second (Vanguard) — 154 points\n", $text);
        self::assertStringContainsString("Detachment 3: swamp — 80 points\n  - Guards (4 models) — 80 points\n\nViolations\n  - The list was built with version 2025.9", $text);
        self::assertStringEndsWith("  - Units of the category \"hero\" cost at most 25 % of the points limit, 75 points; the list spends 105.\n", $text);
    }

    public function testAnEmptyListAsText(): void
    {
        $list = ArmyList::for(Skirmish::catalogue(), 'l', 'Empty', 1);

        self::assertSame("Empty\nEmber Vale Skirmish 2026.1 — 0 / 1 points\n", new TextExport()->render($list, Skirmish::catalogue()));
    }

    public function testOnePointIsSingular(): void
    {
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('u', 'U', ['t'], 1)], [new DetachmentType('d', 'D', [new Slot('t')])]);
        $list = new ArmyList('l', 'L', 'c', '1', 5, [new Detachment('d', null, [new UnitSelection('u')])]);

        self::assertSame("L\nC 1 — 1 / 5 points\n\nDetachment 1: D — 1 point\n  - U (1 model) — 1 point\n", new TextExport()->render($list, $catalogue));
    }

    public function testAListAsEscapedHtml(): void
    {
        $catalogue = new Catalogue('c', 'Cat & "co"', '1', [new UnitEntry('u', 'U <b>', ['t'], 2, optionGroups: [new OptionGroup('g', 'G', [new Option('o', "O'<i>")])])], [new DetachmentType('d', 'D>', [new Slot('t')])]);
        $list = new ArmyList('l', 'L <script>', 'c', '1', 5, [new Detachment('d', 'N&M', [new UnitSelection('u', 1, ['o' => 1]), new UnitSelection('u')])]);
        $validation = new ArmyValidator(new InMemoryCatalogueProvider($catalogue))->validate($list->withPointsLimit(3));

        self::assertSame(<<<'HTML'
            <article class="army-list">
            <header><h1>L &lt;script&gt;</h1><p>Cat &amp; &quot;co&quot; 1 — 4 / 5 points</p></header>
            <section class="army-list__detachment"><h2>Detachment 1: N&amp;M (D&gt;) — 4 points</h2>
            <ul class="army-list__units">
            <li><span class="army-list__unit">U &lt;b&gt; (1 model)</span> — <span class="army-list__points">2 points</span><ul class="army-list__options"><li>O&apos;&lt;i&gt; ×1</li></ul></li>
            <li><span class="army-list__unit">U &lt;b&gt; (1 model)</span> — <span class="army-list__points">2 points</span></li>
            </ul></section>
            </article>

            HTML, new HtmlExport()->render($list, $catalogue));

        self::assertStringEndsWith(<<<'HTML'
            </ul></section>
            <section class="army-list__violations"><h2>Violations</h2><ul><li>The list costs 4 points; its limit is 3.</li></ul></section>
            </article>

            HTML, new HtmlExport()->render($list->withPointsLimit(3), $catalogue, $validation));
    }

    public function testAMessageOfInvalidUtf8IsSubstitutedInHtml(): void
    {
        $catalogue = Skirmish::catalogue();
        $list = ArmyList::for($catalogue, 'l', 'Empty', 1);
        $constraint = new class implements Constraint {
            public function check(ArmyList $list, Catalogue $catalogue, Costing $costing): array
            {
                return [new Violation(Rule::Constraint, "Bad \xFF byte")];
            }
        };
        $validation = new ArmyValidator(new InMemoryCatalogueProvider($catalogue), $constraint)->validate($list);

        self::assertStringContainsString("<li>Bad \u{FFFD} byte</li>", new HtmlExport()->render($list, $catalogue, $validation));
    }

    public function testAnEmptyListAsHtml(): void
    {
        $list = ArmyList::for(Skirmish::catalogue(), 'l', 'Empty', 1);

        self::assertSame("<article class=\"army-list\">\n<header><h1>Empty</h1><p>Ember Vale Skirmish 2026.1 — 0 / 1 points</p></header>\n</article>\n", new HtmlExport()->render($list, Skirmish::catalogue()));
    }

    public function testASheetPrintsTheCostingOfTheValidationItIsGiven(): void
    {
        $catalogue = Skirmish::catalogue();
        $list = ArmyList::for($catalogue, 'l', 'Empty', 500);
        $validation = new Validation($list, $catalogue, Costing::of(Lists::valid(), $catalogue), []);

        self::assertSame("Empty\nEmber Vale Skirmish 2026.1 — 427 / 500 points\n", new TextExport()->render($list, $catalogue, $validation));
    }

    public function testAnOptionOfAnUnknownUnitShowsItsCode(): void
    {
        $list = new ArmyList('l', 'L', 'ember-vale', '2026.1', 5, [new Detachment('vanguard', null, [new UnitSelection('dragon', 1, ['claw' => 2])])]);

        self::assertStringContainsString('claw ×2', new TextExport()->render($list, Skirmish::catalogue()));
    }

    public function testAnOptionTheCatalogueDoesNotHoldShowsItsCode(): void
    {
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('u', 'U', ['t'])], [new DetachmentType('d', 'D', [new Slot('t')])]);
        $list = new ArmyList('l', 'L', 'c', '1', 5, [new Detachment('d', null, [new UnitSelection('u', 1, ['x' => 1])])]);

        self::assertStringContainsString('<li>x ×1</li>', new HtmlExport()->render($list, $catalogue));
    }
}
