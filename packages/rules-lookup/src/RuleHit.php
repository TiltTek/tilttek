<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup;

use Tilttek\RpgSystems\Provenance;

/**
 * A rule as a reader sees it in the results: its title and text in the reader's locale, its score, and the attribution
 * of its source — always given, so that a page never shows a rule without where it comes from.
 */
final readonly class RuleHit
{
    /**
     * @param string $attribution the notice of the rule's source: its attribution, or its title, then its licence
     */
    public function __construct(
        public Rule $rule,
        public string $title,
        public string $text,
        public string $attribution,
        public int $score = 0,
    ) {
    }

    public function provenance(): Provenance
    {
        return $this->rule->provenance;
    }
}
