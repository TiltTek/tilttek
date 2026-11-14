<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import;

use Tilttek\Generator\Definition\Attribution;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Import\Perchance\Branch;
use Tilttek\Generator\Import\Perchance\Choice;
use Tilttek\Generator\Import\Perchance\Dice;
use Tilttek\Generator\Import\Perchance\Range;
use Tilttek\Generator\Import\Perchance\Reference;
use Tilttek\Generator\Import\Perchance\TableParser;
use Tilttek\Generator\Import\Perchance\Text;

/**
 * Reads tables written in the subset of Perchance's syntax that has no JavaScript — sections and their entries,
 * weights, references, inline choices, ranges and dice — into lists and generators.
 *
 * A line that starts without indentation names a section, and the lines under it indented by a tab or two spaces are
 * its entries; `//` starts a comment at the start of a line or after a space; `title` names the tables and is not a
 * section. `^2` or `^0.5` at the end of an entry or of a choice weighs it. `[name]` picks in a section,
 * `[name.selectUnique(3)]` three different entries and `[name.selectMany(3)]` three entries that may repeat, joined
 * by commas; `{a|b|c}` chooses one of its parts, which may hold references and choices; `{1-6}` is a whole number in
 * that range; `2d6+1` is a roll of dice. A backslash keeps the next character as written. Names are compared without
 * regard to case, their spaces read as hyphens. Markdown is read in its fenced blocks marked `perhaps` or `perchance`
 * when it has any.
 *
 * A section whose entries are plain text becomes a list; any other, and the section drawn first — `output`, or the
 * first one —, becomes a generator, each entry a template. Refused, with the line of the problem: an unknown or empty
 * section, a bracket or a brace that is not closed or holds anything else, an entry outside a section, an indentation
 * of one space, a weight that is not positive, and texts, sections or nesting past the bounds below.
 */
final readonly class PerchanceImporter
{
    /**
     * The largest text read, in bytes.
     */
    public const int MAX_BYTES = 1_048_576;

    /**
     * The most sections, and the most entries of a section.
     */
    public const int MAX_SECTIONS = TableParser::MAX_SECTIONS;

    /**
     * The deepest nesting of choices in an entry.
     */
    public const int MAX_DEPTH = TableParser::MAX_DEPTH;

    /**
     * The most entries `selectUnique()` and `selectMany()` take.
     */
    public const int MAX_SELECT = TableParser::MAX_SELECT;

    /**
     * The heaviest weight.
     */
    public const int MAX_WEIGHT = TableParser::MAX_WEIGHT;

    /**
     * The digits after the point a weight keeps.
     */
    private const int WEIGHT_DECIMALS = 3;

    /**
     * @param string       $prefix      put before every name, such as `tavern.`
     * @param ?Attribution $attribution where the tables come from, given to every list and generator they make
     * @param ?string      $locale      the language of the generators, when they need one
     */
    public function __construct(
        private string $prefix = '',
        private ?Attribution $attribution = null,
        private ?string $locale = null,
    ) {
    }

    /**
     * @throws InvalidDefinition
     */
    public function import(string $text): Imported
    {
        if (\strlen($text) > self::MAX_BYTES) {
            throw InvalidDefinition::at('perchance', \sprintf('the tables hold at most %d bytes', self::MAX_BYTES));
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            throw InvalidDefinition::at('perchance', 'the tables are written in UTF-8');
        }

        $sections = new TableParser($this->prefix)->parse($text);
        $start = \array_key_exists('output', $sections) ? 'output' : array_key_first($sections);

        if (null === $start) {
            throw InvalidDefinition::at('perchance', 'the tables have no section');
        }

        $start = (string) $start;

        if ([] === $sections[$start]) {
            throw InvalidDefinition::at('perchance > '.$start, 'the section drawn first has no entry');
        }

        $generators = [];

        foreach ($sections as $name => $entries) {
            if ((string) $name === $start || !array_all($entries, static fn (Branch $entry): bool => $entry->isPlain())) {
                $generators[(string) $name] = true;
            }
        }

        return new Imported($this->document($sections, $generators), $this->prefix.$start);
    }

    /**
     * @param array<array-key, list<Branch>> $sections
     * @param array<string, true>            $generators
     *
     * @return array<string, array<array-key, mixed>>
     */
    private function document(array $sections, array $generators): array
    {
        $credit = $this->attribution?->toArray() ?? [];
        $locale = null === $this->locale ? [] : ['locale' => $this->locale];
        $document = [];

        foreach ($sections as $name => $entries) {
            $name = (string) $name;

            if ([] === $entries) {
                continue;
            }

            $weights = $this->weights(array_map(static fn (Branch $entry): string => $entry->weight, $entries));

            if (!isset($generators[$name])) {
                $document['lists'][$this->prefix.$name] = ['entries' => array_map(static fn (Branch $entry, int $weight): array|string => 1 === $weight ? $entry->text() : ['text' => $entry->text(), 'weight' => $weight], $entries, $weights)] + $credit;

                continue;
            }

            $templates = [];

            foreach ($entries as $index => $entry) {
                $counter = 0;
                $template = $this->template($entry->segments, $sections, $generators, $counter, 'perchance > '.$name);
                $templates[] = 1 === $weights[$index] ? $template : ['template' => $template, 'weight' => $weights[$index]];
            }

            $document['generators'][$this->prefix.$name] = $locale + ['templates' => $templates] + $credit;
        }

        return $document;
    }

    /**
     * Weights as whole numbers in the same proportions: scaled by the most digits after the point, at most three, then
     * divided by their greatest common divisor.
     *
     * @param non-empty-list<string> $weights
     *
     * @return non-empty-list<int>
     */
    private function weights(array $weights): array
    {
        $decimals = min(self::WEIGHT_DECIMALS, max(array_map(static fn (string $weight): int => str_contains($weight, '.') ? \strlen($weight) - (int) strpos($weight, '.') - 1 : 0, $weights)));
        $scaled = array_map(static fn (string $weight): int => max(1, (int) round((float) $weight * 10 ** $decimals)), $weights);
        $divisor = array_reduce($scaled, static function (int $carry, int $weight): int {
            while (0 !== $weight) {
                [$carry, $weight] = [$weight, $carry % $weight];
            }

            return $carry;
        }, 0);

        return array_map(static fn (int $weight): int => intdiv($weight, $divisor), $scaled);
    }

    /**
     * @param list<Text|Dice|Range|Reference|Choice> $segments
     * @param array<array-key, list<Branch>>         $sections
     * @param array<string, true>                    $generators
     */
    private function template(array $segments, array $sections, array $generators, int &$counter, string $at): string
    {
        $template = '';
        $plain = '';

        foreach ($segments as $segment) {
            if ($segment instanceof Text) {
                $plain .= $segment->text;

                continue;
            }

            $template .= TemplateText::literal($plain).match (true) {
                $segment instanceof Dice => '{{ roll('.TemplateText::string($segment->expression).') }}',
                $segment instanceof Range => '{{ between('.$segment->min.', '.$segment->max.') }}',
                $segment instanceof Reference => '{{ '.$this->reference($segment, $sections, $generators, $at).' }}',
                default => $this->choice($segment, $sections, $generators, $counter, $at),
            };
            $plain = '';
        }

        return $template.TemplateText::literal($plain);
    }

    /**
     * A reference as a pick in a list or a generator drawn, once or several times joined by commas.
     *
     * @param array<array-key, list<Branch>> $sections
     * @param array<string, true>            $generators
     */
    private function reference(Reference $reference, array $sections, array $generators, string $at): string
    {
        if (!\array_key_exists($reference->name, $sections)) {
            throw InvalidDefinition::at($at, \sprintf('the section "%s" does not exist', $reference->name));
        }

        if ([] === $sections[$reference->name]) {
            throw InvalidDefinition::at($at, \sprintf('the section "%s" has no entry', $reference->name));
        }

        $call = (isset($generators[$reference->name]) ? 'generate(' : 'pick(').TemplateText::string($this->prefix.$reference->name);

        return match ($reference->method) {
            null => $call.')',
            'selectUnique' => $call.', '.$reference->count.")|join(', ')",
            default => '['.implode(', ', array_fill(0, $reference->count, $call.')'))."]|join(', ')",
        };
    }

    /**
     * A choice drawn by weight among its parts: a whole number from 1 to the total of their weights, compared to their
     * running totals.
     *
     * @param array<array-key, list<Branch>> $sections
     * @param array<string, true>            $generators
     */
    private function choice(Choice $choice, array $sections, array $generators, int &$counter, string $at): string
    {
        $weights = $this->weights(array_map(static fn (Branch $branch): string => $branch->weight, $choice->branches));
        $variable = 'choice_'.++$counter;
        $template = '{% set '.$variable.' = between(1, '.array_sum($weights).') %}';
        $running = 0;
        $last = \count($choice->branches) - 1;

        foreach ($choice->branches as $index => $branch) {
            $running += $weights[$index];
            $template .= match ($index) {
                0 => '{% if '.$variable.' <= '.$running.' %}',
                $last => '{% else %}',
                default => '{% elseif '.$variable.' <= '.$running.' %}',
            };
            $template .= $this->template($branch->segments, $sections, $generators, $counter, $at);
        }

        return $template.'{% endif %}';
    }
}
