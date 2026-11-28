<?php

declare(strict_types=1);

/*
 * The fuzz target of the queries, run by `phil-dev fuzz`: any text is either refused with the library's own exceptions
 * or gives a query whose words are folded once and for all, and a search that returns a page within its bounds.
 */

use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Translations;
use Tilttek\RulesLookup\Exception\RulesLookupException;
use Tilttek\RulesLookup\Rule;
use Tilttek\RulesLookup\RuleId;
use Tilttek\RulesLookup\RuleQuery;
use Tilttek\RulesLookup\RulesLookup;
use Tilttek\RulesLookup\Store\InMemoryRuleStore;

require getenv('PHIL_DEV_AUTOLOAD');

$source = new Provenance('Fuzz', Licence::Owned);
$lookup = new RulesLookup(new InMemoryRuleStore(
    new Rule(new RuleId('fuzz', 'a'), Translations::of(['en' => 'Épée à deux mains', 'fr' => 'Arme']), $source, Translations::of(['en' => 'Roll 1d20.'])),
    new Rule(new RuleId('fuzz', 'b'), Translations::of(['el' => 'Ελληνικά']), $source),
));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($lookup): void {
    try {
        $query = new RuleQuery($input, 'fr');
    } catch (RulesLookupException) {
        // The refusals the query promises for a text it cannot search.
        return;
    }

    foreach ($query->words as $word) {
        if (new RuleQuery($word)->words !== [$word]) {
            throw new LogicException(sprintf('The word "%s" folds again into something else.', $word));
        }
    }

    $results = $lookup->search($query);

    if (count($results->hits) > $query->limit || $results->total < count($results->hits)) {
        throw new LogicException('A page holds more than its bounds.');
    }
});
$config->setMaxLen(256);
