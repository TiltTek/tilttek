<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup\Tests\Benchmark;

use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Translations;
use Tilttek\RulesLookup\Rule;
use Tilttek\RulesLookup\RuleId;
use Tilttek\RulesLookup\RuleQuery;
use Tilttek\RulesLookup\RulesLookup;
use Tilttek\RulesLookup\Store\InMemoryRuleStore;

/**
 * What an application does with the rules of a source the size of an SRD: build the store of 5 000 rules in two
 * languages at warm-up, search a word, search the start of a word as the reader types it, fold an accented query, and
 * list a category.
 */
final readonly class SearchBench
{
    /** @var list<Rule> */
    private array $rules;

    private RulesLookup $lookup;

    public function __construct()
    {
        $source = new Provenance('Bench rules', Licence::CcBy40, 'Bench rules, under CC BY 4.0.');
        // A vocabulary of 300 words, each text 60 of them: a word is in about a fifth of the rules, as in an SRD.
        $words = [...['attack', 'creature', 'spell', 'saving', 'throw', 'damage', 'armor', 'class', 'movement', 'action', 'bonus', 'reaction', 'concentration', 'advantage', 'cover'], ...array_map(static fn (int $index): string => \sprintf('word%03d', $index), range(1, 285))];
        $rules = [];

        foreach (range(1, 5000) as $index) {
            $text = implode(' ', array_map(static fn (int $offset): string => $words[($index * 7 + $offset * 13) % \count($words)], range(0, 59)));
            $rules[] = new Rule(
                new RuleId('bench', 'c'.($index % 20).'.r'.$index, '1.0'),
                Translations::of(['en' => \sprintf('Rule %d %s', $index, $words[$index % \count($words)]), 'fr' => \sprintf('Règle %d %s', $index, $words[$index % \count($words)])]),
                $source,
                Translations::of(['en' => $text.' '.$index, 'fr' => 'Une créature '.$text]),
                'c'.($index % 20),
            );
        }

        $this->rules = $rules;
        $this->lookup = new RulesLookup(new InMemoryRuleStore(...$rules));
    }

    /**
     * @Revs(2)
     *
     * @Iterations(3)
     */
    public function benchBuildTheStore(): void
    {
        new InMemoryRuleStore(...$this->rules);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchSearchTwoWords(): void
    {
        $this->lookup->search(new RuleQuery('concentration spell'));
    }

    /**
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchSearchTheStartOfAWord(): void
    {
        $this->lookup->search(new RuleQuery('creature conc', 'fr_CA'));
    }

    /**
     * @Revs(200)
     *
     * @Iterations(5)
     */
    public function benchFoldAnAccentedQuery(): void
    {
        new RuleQuery('ÉPÉE à deux mains, Créature Aveuglée', 'fr_CA');
    }

    /**
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchListACategory(): void
    {
        $this->lookup->search(new RuleQuery('', category: 'c7', limit: 100));
    }
}
