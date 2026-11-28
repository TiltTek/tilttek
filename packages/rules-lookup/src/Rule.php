<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup;

use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Translations;
use Tilttek\RulesLookup\Exception\InvalidRule;

/**
 * A rule a reader looks up: its identifier, its titles and texts by locale, its category, the rules it refers to,
 * and its source — the title, licence and attribution every result shows.
 */
final readonly class Rule
{
    /** The most rules one rule refers to. */
    public const int MAX_REFERENCES = 64;

    /** @var list<RuleId> the rules it refers to, in the same system and version */
    public array $references;

    /**
     * @param Translations $titles     the rule's title by locale, `Blinded`
     * @param Translations $texts      the rule's text by locale
     * @param ?string      $category   the kind of rule, a key: `condition`, `combat`
     * @param list<string> $references the keys of the rules it refers to, in the same system and version
     *
     * @throws InvalidRule
     */
    public function __construct(
        public RuleId $id,
        public Translations $titles,
        public Provenance $provenance,
        public Translations $texts = new Translations(),
        public ?string $category = null,
        array $references = [],
    ) {
        if ($titles->isEmpty()) {
            throw InvalidRule::because(\sprintf('The rule "%s" has no title.', $id->toString()));
        }

        if (null !== $category && !Folding::isKey($category)) {
            throw InvalidRule::because(\sprintf('The category "%s" of the rule "%s" is not a key.', $category, $id->toString()));
        }

        if (\count($references) > self::MAX_REFERENCES) {
            throw InvalidRule::because(\sprintf('The rule "%s" refers to more than %d rules.', $id->toString(), self::MAX_REFERENCES));
        }

        $siblings = [];

        foreach ($references as $key) {
            $sibling = $id->sibling($key);

            if ($sibling->equals($id)) {
                throw InvalidRule::because(\sprintf('The rule "%s" refers to itself.', $id->toString()));
            }

            $siblings[$key] = $sibling;
        }

        $this->references = array_values($siblings);
    }

    /**
     * The title a reader of a locale sees, as `Translations::in()` finds it.
     */
    public function title(string $locale = 'en'): string
    {
        return (string) $this->titles->in($locale);
    }

    /**
     * The text a reader of a locale sees; an empty string for a rule without text.
     */
    public function text(string $locale = 'en'): string
    {
        return $this->texts->in($locale) ?? '';
    }
}
