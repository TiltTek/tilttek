<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup;

use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Translations;
use Tilttek\RulesLookup\Exception\InvalidRule;

/**
 * The rules of a system of `tilttek/rpg-systems`, ready to search: each term a rule of its category — `condition`,
 * `skill` —, each attribute a rule of the category `attribute`, each named roll a rule of the category `dice` whose
 * text is its notation. Every rule keeps the record's source.
 */
final readonly class RulesFromSystem
{
    /**
     * @return list<Rule>
     *
     * @throws InvalidRule
     */
    public function rules(GameSystem $system): array
    {
        $rules = [];

        foreach ($system->terms() as $term) {
            $rules[] = new Rule(new RuleId($system->key, $term->key, $system->version), $term->names, $term->provenance, $term->descriptions, $term->category);
        }

        foreach ($system->attributes() as $attribute) {
            $rules[] = new Rule(new RuleId($system->key, 'attribute.'.$attribute->key, $system->version), $attribute->names, $attribute->provenance, $attribute->descriptions, 'attribute');
        }

        foreach ($system->diceExpressions() as $dice) {
            $notations = [];

            foreach ($dice->names->locales() as $locale) {
                $notations[$locale] = $dice->notationIn($locale);
            }

            $rules[] = new Rule(new RuleId($system->key, 'dice.'.$dice->key, $system->version), $dice->names, $dice->provenance, Translations::of($notations), 'dice');
        }

        return $rules;
    }
}
