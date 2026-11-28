<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Translations;
use Tilttek\RulesLookup\Rule;
use Tilttek\RulesLookup\RuleHit;
use Tilttek\RulesLookup\RuleId;
use Tilttek\RulesLookup\RuleQuery;
use Tilttek\RulesLookup\RulesFromSystem;
use Tilttek\RulesLookup\RulesLookup;
use Tilttek\RulesLookup\SearchResults;
use Tilttek\RulesLookup\Store\InMemoryRuleStore;

#[CoversClass(RulesLookup::class)]
#[CoversClass(RuleHit::class)]
#[CoversClass(SearchResults::class)]
#[CoversClass(RulesFromSystem::class)]
final class RulesLookupTest extends TestCase
{
    private RulesLookup $lookup;

    protected function setUp(): void
    {
        $system = new SystemReader()->system([
            'format' => SystemReader::FORMAT,
            'system' => ['key' => 'open-rules', 'names' => 'Open Rules', 'version' => '1.0'],
            'source' => ['title' => 'Open Rules', 'licence' => 'CC-BY-4.0', 'attribution' => 'Rules from Open Rules <by a publisher>, under CC BY 4.0.'],
            'attributes' => [['key' => 'grit', 'names' => ['en' => 'Grit', 'fr' => 'Cran'], 'descriptions' => 'Holding on.', 'minimum' => 1, 'maximum' => 6]],
            'dice' => [['key' => 'advantage', 'notation' => '2d20kh1', 'names' => ['en' => 'Advantage', 'de' => 'Vorteil']]],
            'terms' => [
                ['key' => 'condition.blinded', 'category' => 'condition', 'names' => ['en' => 'Blinded', 'fr' => 'Aveuglé'], 'descriptions' => 'Cannot see.'],
                ['key' => 'range.near', 'names' => 'Near', 'source' => ['title' => 'Our notes', 'licence' => 'owned']],
            ],
        ]);
        $rules = new RulesFromSystem()->rules($system);
        $source = $rules[0]->provenance;
        $rules[] = new Rule(new RuleId('open-rules', 'combat.cover', '1.0'), Translations::of(['en' => 'Cover']), $source, Translations::of(['en' => 'A blinded archer misses.']), 'combat', ['condition.blinded', 'condition.missing']);
        $this->lookup = new RulesLookup(new InMemoryRuleStore(...$rules));
    }

    public function testASystemGivesARuleForEachRecord(): void
    {
        $store = new InMemoryRuleStore(...new RulesFromSystem()->rules(new SystemReader()->system([
            'format' => SystemReader::FORMAT,
            'system' => ['key' => 'tiny', 'names' => 'Tiny'],
            'source' => ['title' => 'Tiny', 'licence' => 'owned'],
            'attributes' => [['key' => 'grit', 'names' => 'Grit']],
            'dice' => [['key' => 'check', 'notation' => '1d20', 'names' => ['en' => 'Check', 'de' => 'Probe']]],
            'terms' => [['key' => 'near', 'names' => 'Near']],
        ])));

        self::assertSame(['tiny:near', 'tiny:attribute.grit', 'tiny:dice.check'], array_map(static fn (Rule $rule): string => $rule->id->toString(), $store->all()));
        self::assertSame([null, 'attribute', 'dice'], array_map(static fn (Rule $rule): ?string => $rule->category, $store->all()));
        self::assertSame(['en' => '1d20', 'de' => '1W20'], $store->all()[2]->texts->texts);
        self::assertTrue($store->all()[1]->texts->isEmpty());
    }

    public function testEveryResultCarriesTheAttributionOfItsSource(): void
    {
        $results = $this->lookup->search(new RuleQuery('aveugle', 'fr'));

        self::assertSame(1, $results->total);
        self::assertFalse($results->isEmpty());
        self::assertFalse($results->hasMore());
        $hit = $results->hits[0];
        self::assertSame(['open-rules@1.0:condition.blinded', 'Aveuglé', 'Cannot see.', 110], [$hit->rule->id->toString(), $hit->title, $hit->text, $hit->score]);
        self::assertSame('Rules from Open Rules <by a publisher>, under CC BY 4.0. (Creative Commons Attribution 4.0 International: https://creativecommons.org/licenses/by/4.0/)', $hit->attribution);
        self::assertSame($hit->rule->provenance, $hit->provenance());
        self::assertSame([$hit->rule->provenance], $results->sources());
        self::assertSame($hit->attribution, $results->attributionText());
        self::assertSame(
            "<ul class=\"attribution\">\n<li>Rules from Open Rules &lt;by a publisher&gt;, under CC BY 4.0. (<a href=\"https://creativecommons.org/licenses/by/4.0/\" rel=\"license\">Creative Commons Attribution 4.0 International</a>)</li>\n</ul>",
            $results->attributionHtml(),
        );
    }

    public function testASourceThatAsksForNoAttributionIsStillNamed(): void
    {
        $results = $this->lookup->search(new RuleQuery('near'));

        self::assertSame('Our notes (All rights reserved)', $results->hits[0]->attribution);
        self::assertSame('Our notes (All rights reserved)', $results->attributionText());
        self::assertStringContainsString('Our notes', $results->attributionHtml());
    }

    public function testAllTheReferencesOfARuleAreFollowed(): void
    {
        $source = new Provenance('A', Licence::Cc0);
        $lookup = new RulesLookup(new InMemoryRuleStore(
            new Rule(new RuleId('a', 'one'), Translations::of(['en' => 'One']), $source, references: ['two', 'three']),
            new Rule(new RuleId('a', 'two'), Translations::of(['en' => 'Two']), $source),
            new Rule(new RuleId('a', 'three'), Translations::of(['en' => 'Three']), $source),
        ));

        self::assertSame(['Two', 'Three'], array_map(static fn (RuleHit $hit): string => $hit->title, $lookup->references(RuleId::fromString('a:one'))));
    }

    public function testThePageSaysWhetherAnotherFollows(): void
    {
        $first = $this->lookup->search(new RuleQuery('', category: 'condition', limit: 1));
        $results = $this->lookup->search(new RuleQuery('blinded', limit: 1));

        self::assertFalse($first->hasMore());
        self::assertSame(2, $results->total);
        self::assertTrue($results->hasMore());
        self::assertFalse($this->lookup->search(new RuleQuery('blinded', limit: 1, offset: 1))->hasMore());
        self::assertSame(2, \count($this->lookup->search(new RuleQuery('', category: 'attribute'))->hits) + \count($this->lookup->search(new RuleQuery('', category: 'dice'))->hits));
        $empty = $this->lookup->search(new RuleQuery('nothing'));
        self::assertTrue($empty->isEmpty());
        self::assertSame('', $empty->attributionHtml());
        self::assertSame([], $empty->sources());
    }

    public function testTheSourcesOfAPageAreEachNamedOnce(): void
    {
        $results = $this->lookup->search(new RuleQuery('', category: 'combat'))->hits;
        $page = $this->lookup->search(new RuleQuery('blinded'));

        self::assertCount(1, $results);
        self::assertCount(2, $page->hits);
        self::assertCount(1, $page->sources());
    }

    public function testARuleIsShownInTheReadersLocale(): void
    {
        $hit = $this->lookup->rule(RuleId::fromString('open-rules@1.0:attribute.grit'), 'fr');

        self::assertSame(['Cran', 'Holding on.', 0], [$hit?->title, $hit?->text, $hit?->score]);
        self::assertStringStartsWith('Rules from Open Rules', (string) $hit?->attribution);
        self::assertSame('Grit', $this->lookup->rule(RuleId::fromString('open-rules@1.0:attribute.grit'))?->title);
        self::assertSame(['Vorteil', '2W20kh1'], [$this->lookup->rule(RuleId::fromString('open-rules@1.0:dice.advantage'), 'de')?->title, $this->lookup->rule(RuleId::fromString('open-rules@1.0:dice.advantage'), 'de')?->text]);
        self::assertNull($this->lookup->rule(RuleId::fromString('open-rules@1.0:attribute.luck')));
    }

    public function testReferencesAreFollowedBothWays(): void
    {
        $cover = RuleId::fromString('open-rules@1.0:combat.cover');
        $blinded = RuleId::fromString('open-rules@1.0:condition.blinded');

        self::assertSame(['Aveuglé'], array_map(static fn (RuleHit $hit): string => $hit->title, $this->lookup->references($cover, 'fr')));
        self::assertSame(['Cover'], array_map(static fn (RuleHit $hit): string => $hit->title, $this->lookup->referencedBy($blinded, 'fr')));
        self::assertSame(['Blinded'], array_map(static fn (RuleHit $hit): string => $hit->title, $this->lookup->references($cover)));
        self::assertSame(['Cover'], array_map(static fn (RuleHit $hit): string => $hit->title, $this->lookup->referencedBy($blinded)));
        self::assertStringStartsWith('Rules from Open Rules', $this->lookup->referencedBy($blinded)[0]->attribution);
        self::assertStringStartsWith('Rules from Open Rules', $this->lookup->references($cover)[0]->attribution);
        self::assertSame([], $this->lookup->references($blinded));
        self::assertSame([], $this->lookup->references(RuleId::fromString('open-rules@1.0:combat.missing')));
        self::assertSame([], $this->lookup->referencedBy($cover));
    }

    public function testAHitKeepsWhatItIsGiven(): void
    {
        $rule = new Rule(new RuleId('a', 'b'), Translations::of(['en' => 'B']), new Provenance('A', Licence::Cc0));

        self::assertSame(0, new RuleHit($rule, 'B', '', 'A (Creative Commons Zero 1.0 Universal: https://creativecommons.org/publicdomain/zero/1.0/)')->score);
    }
}
