<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Translations;
use Tilttek\RulesLookup\Exception\InvalidRule;
use Tilttek\RulesLookup\Rule;
use Tilttek\RulesLookup\RuleId;
use Tilttek\RulesLookup\RuleMatch;
use Tilttek\RulesLookup\RuleMatches;
use Tilttek\RulesLookup\RuleQuery;
use Tilttek\RulesLookup\Store\InMemoryRuleStore;

#[CoversClass(InMemoryRuleStore::class)]
#[CoversClass(RuleMatch::class)]
#[CoversClass(RuleMatches::class)]
final class InMemoryRuleStoreTest extends TestCase
{
    private Provenance $source;

    private InMemoryRuleStore $store;

    protected function setUp(): void
    {
        $this->source = new Provenance('Open Rules', Licence::CcBy40, 'Rules from Open Rules, under CC BY 4.0.');
        $this->store = new InMemoryRuleStore(
            $this->rule('condition.blinded', ['en' => 'Blinded', 'fr' => 'Aveuglé'], ['en' => 'A blinded creature cannot see.', 'fr' => 'Une créature aveuglée ne voit rien.'], 'condition', ['condition.prone']),
            $this->rule('condition.prone', ['en' => 'Prone'], ['en' => 'The creature lies on the ground; standing up costs movement.'], 'condition'),
            $this->rule('combat.cover', ['en' => 'Cover', 'fr' => 'Abri'], ['en' => 'Walls give cover against a blinded archer.'], 'combat', ['condition.blinded']),
            $this->rule('combat.blind-fighting', ['en' => 'Blind Fighting'], ['en' => 'Fighting what you cannot see.'], 'combat', ['condition.blinded']),
            $this->rule('spell.epee', ['fr' => 'Épée de lumière'], [], 'spell'),
            $this->rule('condition.blinded', ['en' => 'Blinded (old)'], ['en' => 'An older wording.'], 'condition', version: '5.1'),
            $this->rule('range.near', ['en' => 'Near'], ['en' => 'Within 2 steps.'], 'range', system: 'lanterns', version: null),
        );
    }

    public function testWordsInTheTitleCountMoreThanWordsInTheText(): void
    {
        $matches = $this->store->search(new RuleQuery('blinded', system: 'srd'));

        self::assertSame(3, $matches->total);
        self::assertSame(
            [['srd@5.2:condition.blinded', 110], ['srd@5.1:condition.blinded', 30], ['srd@5.2:combat.cover', 3]],
            $this->ids($matches),
        );
    }

    public function testTheLastWordTypedFindsTheWordsItStarts(): void
    {
        self::assertSame([['srd@5.2:combat.blind-fighting', 30], ['srd@5.2:condition.blinded', 6], ['srd@5.1:condition.blinded', 6], ['srd@5.2:combat.cover', 1]], $this->ids($this->store->search(new RuleQuery('blind'))));
        self::assertSame([['srd@5.2:combat.blind-fighting', 120]], $this->ids($this->store->search(new RuleQuery('blind fighting'))));
        self::assertSame([['srd@5.2:combat.blind-fighting', 16]], $this->ids($this->store->search(new RuleQuery('blind fi'))));
        self::assertSame([], $this->ids($this->store->search(new RuleQuery('b'))), 'a single character starts no word');
        self::assertSame([], $this->ids($this->store->search(new RuleQuery('blind zzz'))), 'every word must be found');
        self::assertSame([], $this->ids($this->store->search(new RuleQuery('blin fighting'))), 'only the last word stands for the words it starts');
        self::assertSame([['srd@5.2:condition.prone', 1]], $this->ids($this->store->search(new RuleQuery('stand'))));
    }

    public function testTheTextsSearchedAreThoseTheReaderSees(): void
    {
        self::assertSame([['srd@5.2:condition.blinded', 110]], $this->ids($this->store->search(new RuleQuery('AVEUGLÉ', 'fr_CA'))));
        self::assertSame([], $this->ids($this->store->search(new RuleQuery('aveugle', 'en'))));
        self::assertSame([['srd@5.2:condition.blinded', 3], ['srd@5.2:condition.prone', 3]], $this->ids($this->store->search(new RuleQuery('creature', 'fr'))), 'the French text of one, the English text of the other');
        self::assertSame([['srd@5.2:spell.epee', 20]], $this->ids($this->store->search(new RuleQuery('epee lumiere', 'de'))), 'the first text when neither the locale nor English has one');
        self::assertSame([['srd@5.2:condition.prone', 110]], $this->ids($this->store->search(new RuleQuery('prone', 'fr'))), 'English when the locale has no text');
    }

    public function testAccentsAndCaseAreIgnoredOnBothSides(): void
    {
        $blinded = [['srd@5.2:condition.blinded', 110]];

        foreach (['aveugle', 'AVEUGLE', 'Aveuglé', 'aveuglé', 'AVEUGLÉ', "aveugle\u{0301}", 'aVeUgLe'] as $query) {
            self::assertSame($blinded, $this->ids($this->store->search(new RuleQuery($query, 'fr'))), $query);
        }

        foreach (['épée lumière', 'EPEE LUMIERE', "e\u{0301}pe\u{0301}e lumie\u{0300}re"] as $query) {
            self::assertSame([['srd@5.2:spell.epee', 20]], $this->ids($this->store->search(new RuleQuery($query, 'fr'))), $query);
        }

        foreach (['Épée De Lumière', 'EPEE DE LUMIERE', 'épée de lumière'] as $query) {
            self::assertSame([['srd@5.2:spell.epee', 130]], $this->ids($this->store->search(new RuleQuery($query, 'fr'))), 'the whole title: '.$query);
        }

        self::assertSame([['srd@5.2:spell.epee', 16]], $this->ids($this->store->search(new RuleQuery('ÉPÉE LUM', 'fr'))), 'the start of an accented word');
    }

    public function testFiltersNarrowTheSearch(): void
    {
        self::assertSame(['srd@5.2:condition.blinded', 'srd@5.2:combat.cover'], array_column($this->ids($this->store->search(new RuleQuery('blinded', version: '5.2'))), 0));
        self::assertSame(['srd@5.2:combat.cover'], array_column($this->ids($this->store->search(new RuleQuery('blinded', category: 'combat'))), 0));
        self::assertSame(['lanterns:range.near'], array_column($this->ids($this->store->search(new RuleQuery('near', system: 'lanterns'))), 0));
        self::assertSame([], $this->ids($this->store->search(new RuleQuery('near', system: 'srd'))));
        self::assertSame(
            [['srd@5.2:condition.blinded', 0], ['srd@5.1:condition.blinded', 0], ['srd@5.2:condition.prone', 0]],
            $this->ids($this->store->search(new RuleQuery('', category: 'condition'))),
            'a category alone lists its rules by title',
        );
    }

    public function testResultsComeOnePageAtATime(): void
    {
        $page = $this->store->search(new RuleQuery('', category: 'condition', limit: 1, offset: 1));

        self::assertSame(3, $page->total);
        self::assertSame([['srd@5.1:condition.blinded', 0]], $this->ids($page));
        self::assertSame([], $this->store->search(new RuleQuery('', category: 'condition', offset: 3))->matches);
        self::assertSame([['srd@5.1:condition.blinded', 30]], $this->ids($this->store->search(new RuleQuery('blinded', limit: 1, offset: 1))), 'each rule keeps its own score on a later page');
    }

    public function testATitleThatStartsWithTheWordsComesFirst(): void
    {
        $store = new InMemoryRuleStore(
            $this->rule('a', ['en' => 'Light cover'], [], 'x'),
            $this->rule('b', ['en' => 'Cover light'], [], 'x'),
            $this->rule('c', ['en' => 'Lighter'], ['en' => 'Light cover.'], 'x'),
            $this->rule('d', ['en' => 'Light'], [], 'x'),
        );

        self::assertSame([['srd@5.2:d', 110], ['srd@5.2:a', 30], ['srd@5.2:b', 10], ['srd@5.2:c', 6]], $this->ids($store->search(new RuleQuery('light'))));
        self::assertSame([['srd@5.2:a', 120], ['srd@5.2:b', 20], ['srd@5.2:c', 6]], $this->ids($store->search(new RuleQuery('light cover'))));
    }

    public function testTiesGoToTheTitleThenToTheIdentifier(): void
    {
        $store = new InMemoryRuleStore(
            $this->rule('z', ['en' => 'Beta step'], [], 'x'),
            $this->rule('y', ['en' => 'Alpha step'], [], 'x'),
            $this->rule('b', ['en' => 'Alpha step'], [], 'x'),
        );

        self::assertSame(['srd@5.2:b', 'srd@5.2:y', 'srd@5.2:z'], array_column($this->ids($store->search(new RuleQuery('step'))), 0));
    }

    public function testTheStartOfAWordIsLookedUpInTheSortedVocabulary(): void
    {
        $rules = [];

        foreach (range(1, 80) as $index) {
            $rules[] = $this->rule('r'.$index, ['en' => \sprintf('Word%03d', $index)], [], 'x');
        }

        $rules[] = $this->rule('aa', ['en' => 'Aardvark'], [], 'x');
        $rules[] = $this->rule('zz', ['en' => 'Zebra'], [], 'x');
        $store = new InMemoryRuleStore(...$rules);

        self::assertSame(64, $store->search(new RuleQuery('word'))->total, 'the start of a word stands for 64 words at most');
        self::assertSame(['srd@5.2:aa'], array_column($this->ids($store->search(new RuleQuery('aa'))), 0));
        self::assertSame(['srd@5.2:zz'], array_column($this->ids($store->search(new RuleQuery('ze'))), 0));
        self::assertSame(['srd@5.2:r42'], array_column($this->ids($store->search(new RuleQuery('word042'))), 0));
        self::assertSame([], $store->search(new RuleQuery('zzz'))->matches);
    }

    public function testRulesAreFoundByIdentifierAndByWhatReferToThem(): void
    {
        self::assertSame('Blinded', $this->store->get(new RuleId('srd', 'condition.blinded', '5.2'))?->title());
        self::assertNull($this->store->get(new RuleId('srd', 'condition.deafened', '5.2')));
        self::assertSame(['combat.cover', 'combat.blind-fighting'], array_map(static fn (Rule $rule): string => $rule->id->key, $this->store->referencing(new RuleId('srd', 'condition.blinded', '5.2'))));
        self::assertSame([], $this->store->referencing(new RuleId('srd', 'condition.blinded', '5.1')));
        self::assertCount(7, $this->store->all());
    }

    public function testAStoreWithMoreRulesReplacesThoseOfTheSameIdentifier(): void
    {
        $store = $this->store->with(
            $this->rule('condition.prone', ['en' => 'Lying down'], [], 'condition'),
            $this->rule('condition.deafened', ['en' => 'Deafened'], [], 'condition'),
        );

        self::assertSame('Lying down', $store->get(new RuleId('srd', 'condition.prone', '5.2'))?->title());
        self::assertSame('Prone', $this->store->get(new RuleId('srd', 'condition.prone', '5.2'))?->title(), 'a store never changes');
        self::assertCount(8, $store->all());
        self::assertSame([], $store->search(new RuleQuery('prone'))->matches);
        self::assertSame(1, $store->search(new RuleQuery('lying'))->total);
    }

    public function testTwoRulesWithOneIdentifierAreRefused(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('Two rules have the identifier "srd@5.2:a".');

        new InMemoryRuleStore($this->rule('a', ['en' => 'A'], [], 'x'), $this->rule('a', ['en' => 'B'], [], 'x'));
    }

    public function testAStoreHoldsAHundredThousandRulesAtMost(): void
    {
        $rule = $this->rule('a', ['en' => 'A'], [], 'x');

        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('A store holds at most 100000 rules.');

        new InMemoryRuleStore(...array_fill(0, 100_001, $rule));
    }

    public function testAHundredThousandRulesAreNotTooMany(): void
    {
        $rule = $this->rule('a', ['en' => 'A'], [], 'x');

        try {
            new InMemoryRuleStore(...array_fill(0, 100_000, $rule));
            self::fail('A hundred thousand copies of one rule are kept.');
        } catch (InvalidRule $invalidRule) {
            self::assertSame('Two rules have the identifier "srd@5.2:a".', $invalidRule->getMessage(), 'refused for the copies, not for their number');
        }
    }

    public function testATitleWithoutWordsIsNeverEqualToTheWordsSearched(): void
    {
        $store = new InMemoryRuleStore($this->rule('dash', ['en' => '—'], [], 'x'), $this->rule('b', ['en' => 'B'], [], 'x'));

        self::assertSame([['srd@5.2:dash', 0], ['srd@5.2:b', 0]], $this->ids($store->search(new RuleQuery('', category: 'x'))));
    }

    public function testTheListOfRulesIsAList(): void
    {
        self::assertSame(range(0, 6), array_keys($this->store->all()));
        self::assertSame(range(0, 7), array_keys($this->store->with($this->rule('new', ['en' => 'New'], [], 'x'))->all()));
    }

    public function testTheLocaleOfARegionIsChosenBeforeItsLanguage(): void
    {
        $store = new InMemoryRuleStore($this->rule('a', ['fr' => 'Alpha', 'fr_CA' => 'Beta'], [], 'x'));

        self::assertSame([['srd@5.2:a', 110]], $this->ids($store->search(new RuleQuery('beta', 'fr_CA'))));
        self::assertSame([], $this->ids($store->search(new RuleQuery('alpha', 'fr_CA'))));
        self::assertSame([['srd@5.2:a', 110]], $this->ids($store->search(new RuleQuery('alpha', 'fr_BE'))));
    }

    public function testEnglishIsChosenBeforeTheFirstText(): void
    {
        $store = new InMemoryRuleStore($this->rule('a', ['fr' => 'Alpha', 'en' => 'Beta'], [], 'x'));

        self::assertSame([['srd@5.2:a', 110]], $this->ids($store->search(new RuleQuery('beta', 'de'))));
        self::assertSame([], $this->ids($store->search(new RuleQuery('alpha', 'de'))));
    }

    public function testAWordOfOneCharacterStartsNoWordWhateverItsLength(): void
    {
        $store = new InMemoryRuleStore($this->rule('a', ['en' => 'Яблоко'], [], 'x'));

        self::assertSame([], $this->ids($store->search(new RuleQuery('я'))));
        self::assertSame([['srd@5.2:a', 6]], $this->ids($store->search(new RuleQuery('ябл'))));
    }

    public function testNumbersAreWordsToo(): void
    {
        $store = new InMemoryRuleStore($this->rule('a', ['en' => 'Roll 1d20'], ['en' => 'Add 2.'], 'x'));

        self::assertSame([['srd@5.2:a', 13]], $this->ids($store->search(new RuleQuery('1d20 2'))));
        self::assertSame([['srd@5.2:a', 6]], $this->ids($store->search(new RuleQuery('1d'))));
    }

    /**
     * @param array<string, string> $titles
     * @param array<string, string> $texts
     * @param list<string>          $references
     */
    private function rule(string $key, array $titles, array $texts, string $category, array $references = [], string $system = 'srd', ?string $version = '5.2'): Rule
    {
        return new Rule(new RuleId($system, $key, $version), Translations::of($titles), $this->source, Translations::of($texts), $category, $references);
    }

    /**
     * @return list<array{string, int}>
     */
    private function ids(RuleMatches $matches): array
    {
        return array_map(static fn (RuleMatch $match): array => [$match->rule->id->toString(), $match->score], $matches->matches);
    }
}
