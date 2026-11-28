<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup;

use Tilttek\RpgSystems\Attribution\AttributionRenderer;
use Tilttek\RulesLookup\Contract\RuleStore;

/**
 * Looks rules up in a store: a search by words, category, system and language, a rule by its identifier, and the
 * rules one refers to or is referred to by — each result with the attribution of its source.
 */
final readonly class RulesLookup
{
    public function __construct(
        private RuleStore $store,
        private AttributionRenderer $renderer = new AttributionRenderer(),
    ) {
    }

    public function search(RuleQuery $query): SearchResults
    {
        $matches = $this->store->search($query);

        return new SearchResults(
            $query,
            array_map(fn (RuleMatch $match): RuleHit => $this->hit($match->rule, $query->locale, $match->score), $matches->matches),
            $matches->total,
            $this->renderer,
        );
    }

    /**
     * A rule as a reader of a locale sees it; null when the store holds none.
     */
    public function rule(RuleId $id, string $locale = 'en'): ?RuleHit
    {
        $rule = $this->store->get($id);

        return $rule instanceof Rule ? $this->hit($rule, $locale) : null;
    }

    /**
     * The rules a rule refers to, in its order; a reference the store does not hold is left out.
     *
     * @return list<RuleHit>
     */
    public function references(RuleId $id, string $locale = 'en'): array
    {
        $rule = $this->store->get($id);
        $hits = [];

        foreach ($rule->references ?? [] as $reference) {
            $target = $this->store->get($reference);

            if ($target instanceof Rule) {
                $hits[] = $this->hit($target, $locale);
            }
        }

        return $hits;
    }

    /**
     * The rules that refer to a rule.
     *
     * @return list<RuleHit>
     */
    public function referencedBy(RuleId $id, string $locale = 'en'): array
    {
        return array_map(fn (Rule $rule): RuleHit => $this->hit($rule, $locale), $this->store->referencing($id));
    }

    private function hit(Rule $rule, string $locale, int $score = 0): RuleHit
    {
        return new RuleHit($rule, $rule->title($locale), $rule->text($locale), $this->renderer->text([$rule->provenance], all: true), $score);
    }
}
