<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import;

use JsonException;
use Tilttek\Generator\Definition\Attribution;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Import\Tracery\Action;
use Tilttek\Generator\Import\Tracery\FreeVariables;
use Tilttek\Generator\Import\Tracery\RuleParser;
use Tilttek\Generator\Import\Tracery\Tag;
use Tilttek\Generator\Import\Tracery\Text;
use Tilttek\Generator\Loading\Reader;

/**
 * Reads a Tracery grammar — a JSON object of symbols, each with its rules — into lists and generators.
 *
 * A symbol whose rules are plain text becomes a list; any other, and the symbol to start from, becomes a generator,
 * each rule a template. `#symbol#` picks in a list or draws a generator; the modifiers `.capitalize`, `.capitalizeAll`,
 * `.a` and `.s` become the filters `upper_first`, `title`, `article('indefinite')` and `plural`, with the grammar of
 * the import's language; an action `[name:#rule#]` or `[name:one,other]` sets a variable for the rest of its rule, and
 * the generators drawn after it in that rule receive it by `generate(…, with: …)`, as far down as they read it. A
 * backslash keeps the next character as written.
 *
 * Refused, with the place of the problem: a document that is not such an object, a name that is not a name of the
 * library, an unknown symbol, a variable read before any action sets it, `POP`, an action without a value, an action
 * inside an action's value, other modifiers, and documents, symbols, rules or nesting past the bounds below.
 */
final readonly class TraceryImporter
{
    /**
     * The largest document read, in bytes.
     */
    public const int MAX_BYTES = 1_048_576;

    /**
     * The most symbols of a grammar, and the most rules of a symbol.
     */
    public const int MAX_SYMBOLS = 4096;

    /**
     * The deepest nesting of actions and tags in a rule.
     */
    public const int MAX_DEPTH = RuleParser::MAX_DEPTH;

    /**
     * @param string       $prefix      put before every name, such as `story.`
     * @param ?Attribution $attribution where the grammar comes from, given to every list and generator it makes
     * @param string       $locale      the language of the generators' grammar filters
     * @param string       $start       the symbol to draw from first
     */
    public function __construct(
        private string $prefix = '',
        private ?Attribution $attribution = null,
        private string $locale = 'en',
        private string $start = 'origin',
    ) {
    }

    /**
     * @throws InvalidDefinition
     */
    public function import(string $json): Imported
    {
        $rules = $this->rules($this->decode($json));

        if (!isset($rules[$this->start])) {
            throw InvalidDefinition::at('tracery', \sprintf('the grammar has no symbol "%s" to start from', $this->start));
        }

        $generators = [];

        foreach ($rules as $symbol => $symbolRules) {
            if ((string) $symbol === $this->start || !array_all($symbolRules, Tag::isPlain(...))) {
                $generators[(string) $symbol] = true;
            }
        }

        $free = new FreeVariables($rules, $generators);

        if ([] !== $free->of($this->start)) {
            throw InvalidDefinition::at('tracery > '.$this->start, \sprintf('the variable "%s" is read before any action sets it', $free->of($this->start)[0]));
        }

        return new Imported($this->document($rules, $free), $this->prefix.$this->start);
    }

    /**
     * @return non-empty-array<array-key, mixed>
     */
    private function decode(string $json): array
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw InvalidDefinition::at('tracery', \sprintf('a grammar holds at most %d bytes', self::MAX_BYTES));
        }

        try {
            $grammar = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidDefinition::at('tracery', $jsonException->getMessage());
        }

        if (!\is_array($grammar) || [] === $grammar || array_is_list($grammar) || \count($grammar) > self::MAX_SYMBOLS) {
            throw InvalidDefinition::at('tracery', \sprintf('a grammar is an object of 1 to %d symbols', self::MAX_SYMBOLS));
        }

        return $grammar;
    }

    /**
     * The rules of each symbol, read.
     *
     * @param array<array-key, mixed> $grammar
     *
     * @return array<array-key, list<list<Text|Tag|Action>>>
     */
    private function rules(array $grammar): array
    {
        $parser = new RuleParser();
        $rules = [];

        foreach ($grammar as $symbol => $texts) {
            // A symbol made of digits is a key of integer in PHP.
            $symbol = (string) $symbol;
            $at = 'tracery > '.$symbol;

            if (1 !== preg_match(RuleParser::SYMBOL, $symbol) || !Reader::isName($this->prefix.$symbol)) {
                throw InvalidDefinition::at($at, 'a symbol is a name of letters, digits, "_" and "-"');
            }

            $texts = \is_array($texts) ? $texts : [$texts];

            if ([] === $texts || !array_is_list($texts) || \count($texts) > self::MAX_SYMBOLS) {
                throw InvalidDefinition::at($at, \sprintf('a symbol has a rule, or a sequence of 1 to %d rules', self::MAX_SYMBOLS));
            }

            foreach ($texts as $index => $text) {
                if (!\is_string($text) && !\is_int($text) && !\is_float($text)) {
                    throw InvalidDefinition::at($at.' > '.$index, 'a rule is a text');
                }

                $rules[$symbol][] = $parser->parse((string) $text, $at.' > '.$index);
            }
        }

        return $rules;
    }

    /**
     * @param array<array-key, list<list<Text|Tag|Action>>> $rules
     *
     * @return array<string, array<array-key, mixed>>
     */
    private function document(array $rules, FreeVariables $free): array
    {
        $credit = $this->attribution?->toArray() ?? [];
        $document = [];

        foreach ($rules as $symbol => $symbolRules) {
            $symbol = (string) $symbol;

            if (!$free->isGenerator($symbol)) {
                $document['lists'][$this->prefix.$symbol] = ['entries' => array_map(static fn (array $rule): string => implode('', array_map(static fn (Text|Tag|Action $text): string => $text instanceof Text ? $text->text : '', $rule)), $symbolRules)] + $credit;

                continue;
            }

            $templates = [];

            foreach ($symbolRules as $rule) {
                $templates[] = $this->template($rule, $free, 'tracery > '.$symbol);
            }

            $document['generators'][$this->prefix.$symbol] = ['locale' => $this->locale, 'templates' => $templates] + $credit;
        }

        return $document;
    }

    /**
     * @param list<Text|Tag|Action> $segments
     */
    private function template(array $segments, FreeVariables $free, string $at): string
    {
        $template = '';
        $local = [];

        foreach ($segments as $segment) {
            if ($segment instanceof Text) {
                $template .= TemplateText::literal($segment->text);

                continue;
            }

            foreach ($segment instanceof Action ? [$segment] : $segment->actions as $action) {
                $template .= '{% set v_'.$action->name.' = '.$this->choice($action, $local, $free, $at).' %}';
                $local[$action->name] = true;
            }

            if ($segment instanceof Tag && '' !== $segment->symbol) {
                $template .= '{{ '.$this->symbol($segment, $local, $free).' }}';
            }
        }

        return $template;
    }

    /**
     * The value of an action: its one rule, or one of its rules chosen at random.
     *
     * @param array<string, true> $local
     */
    private function choice(Action $action, array $local, FreeVariables $free, string $at): string
    {
        $expressions = array_map(fn (array $value): string => $this->expression($value, $local, $free, $at), $action->values);

        if (1 === \count($expressions)) {
            return $expressions[0];
        }

        return '['.implode(', ', $expressions).'][between(0, '.(\count($expressions) - 1).')]';
    }

    /**
     * A rule as one Twig expression, its parts joined by `~`.
     *
     * @param list<Text|Tag|Action> $segments
     * @param array<string, true>   $local
     */
    private function expression(array $segments, array $local, FreeVariables $free, string $at): string
    {
        $parts = [];

        foreach ($segments as $segment) {
            if ($segment instanceof Action || ($segment instanceof Tag && [] !== $segment->actions)) {
                throw InvalidDefinition::at($at, "an action inside an action's value is not supported");
            }

            if ($segment instanceof Text) {
                $parts[] = TemplateText::string($segment->text);
            } elseif ('' !== $segment->symbol) {
                $parts[] = '('.$this->symbol($segment, $local, $free).')';
            }
        }

        return [] === $parts ? "''" : implode(' ~ ', $parts);
    }

    /**
     * `#symbol.modifiers#`: a variable the rule set or received, a pick in a list, or a generator drawn with the
     * variables it receives; then the modifiers' filters.
     *
     * @param array<string, true> $local
     */
    private function symbol(Tag $tag, array $local, FreeVariables $free): string
    {
        $name = TemplateText::string($this->prefix.$tag->symbol);
        $variables = $free->of($tag->symbol);

        $expression = match (true) {
            isset($local[$tag->symbol]) || !$free->isSymbol($tag->symbol) => 'v_'.$tag->symbol,
            !$free->isGenerator($tag->symbol) => 'pick('.$name.')',
            [] === $variables => 'generate('.$name.')',
            default => 'generate('.$name.', with: {'.implode(', ', array_map(static fn (string $variable): string => 'v_'.$variable.': v_'.$variable, $variables)).'})',
        };

        foreach ($tag->modifiers as $modifier) {
            $expression .= '|'.RuleParser::MODIFIERS[$modifier];
        }

        return $expression;
    }
}
