<?php

declare(strict_types=1);

namespace Tilttek\TableRules;

use DateTimeImmutable;
use Tilttek\TableRules\Exception\InvalidRules;
use Tilttek\TableRules\Internal\Identifier;
use Tilttek\TableRules\Internal\Text;

/**
 * One published version of a table's rules, never changed once published: its number, its house rules in the order
 * the table reads them, the rule variants of the game system the table plays with, who published it and when, a note
 * on what changed, and whether every player accepts it anew.
 */
final readonly class RulebookVersion
{
    /** The highest number of a version. */
    public const int MAX_NUMBER = 10_000;

    /** The most rules of a version. */
    public const int MAX_RULES = 200;

    /** The most variants of a version. */
    public const int MAX_VARIANTS = 100;

    /** The most characters of the texts of all the rules of a version together. */
    public const int MAX_TOTAL_TEXT = 100_000;

    /** The most characters of a note. */
    public const int MAX_NOTE = 2_000;

    /** The most lines of a note. */
    public const int MAX_NOTE_LINES = 50;

    /** @var list<Rule> */
    public array $rules;

    /** @var list<string> the identifiers of the variants, sorted */
    public array $variants;

    public ?string $note;

    /**
     * @param int          $number             1 for the first version, then one more for each
     * @param list<Rule>   $rules              in the order the table reads them, each identifier once
     * @param string       $publishedBy        the identifier of who published it
     * @param list<string> $variants           the identifiers of the rule variants of the game system the table plays
     *                                         with, each once
     * @param ?string      $system             the identifier of the game system the variants belong to
     * @param ?string      $note               what changed, for the players; an empty note is none
     * @param bool         $requiresAcceptance whether every player accepts this version anew; the first version always
     *                                         requires it
     *
     * @throws InvalidRules when a value is out of its shape or its bounds, a rule or a variant is given twice, the version
     *                      holds neither rule nor variant, or the first version does not require acceptance
     */
    public function __construct(
        public int $number,
        array $rules,
        public DateTimeImmutable $publishedAt,
        public string $publishedBy,
        array $variants = [],
        public ?string $system = null,
        ?string $note = null,
        public bool $requiresAcceptance = true,
    ) {
        if ($number < 1 || $number > self::MAX_NUMBER) {
            throw InvalidRules::because(\sprintf('The number of a version is 1 to %d.', self::MAX_NUMBER));
        }

        if (1 === $number && !$requiresAcceptance) {
            throw InvalidRules::because('The first version of the rules requires acceptance.');
        }

        Identifier::check($publishedBy, 'who published the rules');
        if (null !== $system) {
            Identifier::check($system, 'a game system');
        }

        if (\count($rules) > self::MAX_RULES) {
            throw InvalidRules::because(\sprintf('A version holds %d rules at most.', self::MAX_RULES));
        }

        if (\count($variants) > self::MAX_VARIANTS) {
            throw InvalidRules::because(\sprintf('A version holds %d variants at most.', self::MAX_VARIANTS));
        }

        if ([] === $rules && [] === $variants) {
            throw InvalidRules::because('A version holds at least one rule or one variant.');
        }

        $seen = [];
        $total = 0;
        foreach ($rules as $rule) {
            if (isset($seen[$rule->id])) {
                throw InvalidRules::because(\sprintf('The rule %s is given twice.', $rule->id));
            }

            $seen[$rule->id] = $rule;
            $total += Text::length($rule->text);
        }

        if ($total > self::MAX_TOTAL_TEXT) {
            throw InvalidRules::because(\sprintf('The texts of the rules of a version are %d characters at most together.', self::MAX_TOTAL_TEXT));
        }

        foreach ($variants as $variant) {
            Identifier::check($variant, 'a variant');
        }

        $sorted = array_unique($variants);
        if (\count($sorted) !== \count($variants)) {
            throw InvalidRules::because('A variant is given twice.');
        }

        sort($sorted, \SORT_STRING);

        $this->rules = $rules;
        $this->variants = $sorted;
        $this->note = null === $note || '' === trim($note) ? null : Text::block($note, self::MAX_NOTE, self::MAX_NOTE_LINES, 'the note of a version');
    }

    /**
     * The rule of the identifier, or null when the version has none.
     */
    public function rule(string $id): ?Rule
    {
        foreach ($this->rules as $rule) {
            if ($rule->id === $id) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Whether the other version holds the same rules in the same order, the same variants and the same system; the
     * number, the note and who published it do not count.
     */
    public function sameRulesAs(self $other): bool
    {
        if ($this->system !== $other->system || $this->variants !== $other->variants || \count($this->rules) !== \count($other->rules)) {
            return false;
        }

        return array_all($this->rules, static fn (Rule $rule, int $position): bool => $rule->equals($other->rules[$position]));
    }

    /**
     * @return array{number: int, rules: list<array{id: string, title: string, text: string, category: ?string}>, variants: list<string>, system: ?string, note: ?string, publishedAt: string, publishedBy: string, requiresAcceptance: bool}
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'rules' => array_map(static fn (Rule $rule): array => $rule->toArray(), $this->rules),
            'variants' => $this->variants,
            'system' => $this->system,
            'note' => $this->note,
            'publishedAt' => $this->publishedAt->format(\DATE_ATOM),
            'publishedBy' => $this->publishedBy,
            'requiresAcceptance' => $this->requiresAcceptance,
        ];
    }
}
