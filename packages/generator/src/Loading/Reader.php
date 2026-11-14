<?php

declare(strict_types=1);

namespace Tilttek\Generator\Loading;

use Tilttek\Generator\Definition\Attribution;
use Tilttek\Generator\Definition\Body;
use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Definition\Entry;
use Tilttek\Generator\Definition\EntryList;
use Tilttek\Generator\Definition\Field;
use Tilttek\Generator\Definition\GenerateField;
use Tilttek\Generator\Definition\GeneratorDefinition;
use Tilttek\Generator\Definition\GraphBody;
use Tilttek\Generator\Definition\GridBody;
use Tilttek\Generator\Definition\MapLayout;
use Tilttek\Generator\Definition\MarkovChain;
use Tilttek\Generator\Definition\ObjectBody;
use Tilttek\Generator\Definition\StateValue;
use Tilttek\Generator\Definition\Template;
use Tilttek\Generator\Definition\TemplateField;
use Tilttek\Generator\Definition\TextBody;
use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * Reads lists and generators from decoded YAML or JSON documents into a catalog, and says where a document is wrong.
 *
 * A document holds `lists` and `generators` maps by name, or one list (`list`, `entries` and an optional `markov`),
 * or one generator (`generator` and its keys). A list is a sequence of entries, or a map of its `entries`, its
 * `markov` chain and its attribution. A list, an entry and a generator say where their text comes from by `source`,
 * `licence` and `attribution`.
 */
final class Reader
{
    private const string NAME = '/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/D';

    private const array GENERATOR_KEYS = ['locale', 'state', 'vars', 'templates', 'filters', 'fields', 'map', ...self::ATTRIBUTION_KEYS];

    private const array ATTRIBUTION_KEYS = ['source', 'licence', 'attribution'];

    private const array LIST_KEYS = ['entries', 'markov', ...self::ATTRIBUTION_KEYS];

    private const array MARKOV_KEYS = ['order', 'min_length', 'max_length', 'allow_entries'];

    /**
     * Whether a text is a valid name of a list or a generator: letters, digits, `_` and `-`, in parts joined by dots.
     */
    public static function isName(string $name): bool
    {
        return 1 === preg_match(self::NAME, $name);
    }

    /**
     * @param array<string, mixed> $documents decoded documents, by the name of their source
     *
     * @throws InvalidDefinition
     */
    public function catalog(array $documents): Catalog
    {
        $lists = [];
        $generators = [];

        foreach ($documents as $source => $document) {
            $document = $this->map($document, $source);

            if (\array_key_exists('list', $document)) {
                $this->keys($document, ['list', ...self::LIST_KEYS], $source);
                $lists[] = $this->list($this->name($document['list'], $source), $document['entries'] ?? null, $source, $document['markov'] ?? null, $this->attribution($document, $source));

                continue;
            }

            if (\array_key_exists('generator', $document)) {
                $name = $this->name($document['generator'], $source);
                unset($document['generator']);
                $generators[] = $this->generator($name, $document, $source);

                continue;
            }

            $this->keys($document, ['lists', 'generators'], $source);

            foreach ($this->map($document['lists'] ?? [], $source.' > lists') as $name => $entries) {
                $lists[] = $this->listOf($this->name($name, $source), $entries, $source.' > lists > '.$name);
            }

            foreach ($this->map($document['generators'] ?? [], $source.' > generators') as $name => $generator) {
                $generators[] = $this->generator($this->name($name, $source), $this->map($generator, $source.' > generators > '.$name), $source.' > generators > '.$name);
            }
        }

        return new Catalog($lists, $generators);
    }

    /**
     * A list written as a sequence of entries, or as a map of its `entries`, its `markov` chain and its attribution.
     */
    private function listOf(string $name, mixed $list, string $at): EntryList
    {
        if (!\is_array($list) || array_is_list($list)) {
            return $this->list($name, $list, $at);
        }

        $list = $this->map($list, $at);
        $this->keys($list, self::LIST_KEYS, $at);

        return $this->list($name, $list['entries'] ?? null, $at, $list['markov'] ?? null, $this->attribution($list, $at));
    }

    private function list(string $name, mixed $entries, string $at, mixed $markov = null, ?Attribution $attribution = null): EntryList
    {
        if (!\is_array($entries) || [] === $entries || !array_is_list($entries)) {
            throw InvalidDefinition::at($at, 'a list is a sequence of at least one entry');
        }

        $list = new EntryList($name, ...array_map(fn (mixed $entry, int $index): Entry => $this->entry($entry, $at.' > '.$index), $entries, array_keys($entries)));
        $list = $attribution instanceof Attribution ? $list->attributed($attribution) : $list;

        return null === $markov ? $list : $list->invent($this->markov($markov, $at.' > markov'));
    }

    /**
     * The `source`, `licence` and `attribution` of a list, an entry or a generator, or null when it has none of them.
     *
     * @param array<string, mixed> $map
     */
    private function attribution(array $map, string $at): ?Attribution
    {
        $texts = array_intersect_key($map, array_flip(self::ATTRIBUTION_KEYS));

        if ([] === $texts) {
            return null;
        }

        if (!array_all($texts, static fn (mixed $text): bool => \is_string($text))) {
            throw InvalidDefinition::at($at, 'the source, the licence and the attribution are texts');
        }

        /** @var array<string, string> $texts */
        try {
            return new Attribution($texts['source'] ?? null, $texts['licence'] ?? null, $texts['attribution'] ?? null);
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidDefinition::at($at, $invalidDefinition->getMessage());
        }
    }

    /**
     * `markov: true`, or `markov: {order: 2, min_length: 3, max_length: 16, allow_entries: false}`.
     */
    private function markov(mixed $markov, string $at): MarkovChain
    {
        if (true === $markov) {
            return new MarkovChain();
        }

        $markov = $this->map($markov, $at);
        $this->keys($markov, self::MARKOV_KEYS, $at);
        $order = $markov['order'] ?? 2;
        $minLength = $markov['min_length'] ?? 3;
        $maxLength = $markov['max_length'] ?? 16;
        $allowEntries = $markov['allow_entries'] ?? false;

        if (!\is_int($order) || !\is_int($minLength) || !\is_int($maxLength) || !\is_bool($allowEntries)) {
            throw InvalidDefinition::at($at, 'a chain is true, or a map whose order and lengths are whole numbers and whose "allow_entries" is true or false');
        }

        try {
            return new MarkovChain($order, $minLength, $maxLength, $allowEntries);
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidDefinition::at($at, $invalidDefinition->getMessage());
        }
    }

    private function entry(mixed $entry, string $at): Entry
    {
        if (\is_scalar($entry)) {
            return new Entry((string) $entry);
        }

        $entry = $this->map($entry, $at);
        $text = $entry['text'] ?? null;
        $weight = $entry['weight'] ?? 1;

        if (!\is_scalar($text) || !\is_int($weight)) {
            throw InvalidDefinition::at($at, 'an entry has a text, and its weight is a whole number');
        }

        $tags = [];

        foreach ($this->map($entry['tags'] ?? [], $at.' > tags') as $tag => $values) {
            $tags[$tag] = $this->scalars($values, $at.' > tags > '.$tag);
        }

        $fields = array_diff_key($entry, array_flip(Entry::RESERVED));

        foreach ($fields as $field => $value) {
            $fields[$field] = \is_array($value) ? $this->scalars($value, $at.' > '.$field) : self::scalar($value, $at.' > '.$field);
        }

        $attribution = $this->attribution($entry, $at);

        try {
            return new Entry((string) $text, $weight, $fields, $tags, $attribution);
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidDefinition::at($at, $invalidDefinition->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $generator
     */
    private function generator(string $name, array $generator, string $at): GeneratorDefinition
    {
        $this->keys($generator, self::GENERATOR_KEYS, $at);
        $locale = $generator['locale'] ?? null;

        if (null !== $locale && !\is_string($locale)) {
            throw InvalidDefinition::at($at, 'the locale is a text such as "fr" or "en_CA"');
        }

        $vars = [];

        foreach ($this->map($generator['vars'] ?? [], $at.' > vars') as $var => $source) {
            $vars[$var] = self::template($source, $at.' > vars > '.$var);
        }

        $state = [];

        foreach ($this->map($generator['state'] ?? [], $at.' > state') as $value => $templates) {
            $state[$value] = $this->state($templates, $at.' > state > '.$value);
        }

        $body = $this->body($generator, $at);

        try {
            return new GeneratorDefinition($name, $body, $vars, $locale, $state, $this->attribution($generator, $at));
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidDefinition::at($at, $invalidDefinition->getMessage());
        }
    }

    /**
     * `weather: "{{ pick('weather') }}"`, a value given once and kept; or `weather: {start: …, next: …}`.
     */
    private function state(mixed $templates, string $at): StateValue
    {
        if (!\is_array($templates)) {
            return new StateValue(self::template($templates, $at));
        }

        $templates = $this->map($templates, $at);
        $this->keys($templates, ['start', 'next'], $at);
        $next = $templates['next'] ?? null;

        return new StateValue(self::template($templates['start'] ?? null, $at.' > start'), null === $next ? null : self::template($next, $at.' > next'));
    }

    /**
     * @param array<string, mixed> $generator
     */
    private function body(array $generator, string $at): Body
    {
        $kinds = array_values(array_intersect(['templates', 'fields', 'map'], array_keys($generator)));

        if (1 !== \count($kinds)) {
            throw InvalidDefinition::at($at, 'a generator has exactly one of "templates", "fields" and "map"');
        }

        if (\array_key_exists('filters', $generator) && 'templates' !== $kinds[0]) {
            throw InvalidDefinition::at($at, 'only a generator with "templates" has "filters"');
        }

        return match ($kinds[0]) {
            'templates' => $this->text($generator['templates'], $generator['filters'] ?? [], $at),
            'fields' => $this->fields($this->map($generator['fields'], $at.' > fields'), $at.' > fields'),
            default => $this->grid($this->map($generator['map'], $at.' > map'), $at.' > map'),
        };
    }

    private function text(mixed $templates, mixed $filters, string $at): TextBody
    {
        $templates = \is_string($templates) ? [$templates] : $templates;

        if (!\is_array($templates) || [] === $templates || !array_is_list($templates)) {
            throw InvalidDefinition::at($at.' > templates', 'templates are a template or a sequence of at least one');
        }

        if (!\is_array($filters) || !array_is_list($filters)) {
            throw InvalidDefinition::at($at.' > filters', 'filters are a sequence of filter names');
        }

        return new TextBody(
            array_map(static function (mixed $template) use ($at): Template {
                if (\is_array($template)) {
                    $weight = $template['weight'] ?? 1;

                    if (!\is_int($weight) || $weight < 1) {
                        throw InvalidDefinition::at($at.' > templates', 'the weight of a template is a whole number of at least 1');
                    }

                    return new Template(self::template($template['template'] ?? null, $at.' > templates'), $weight);
                }

                return new Template(self::template($template, $at.' > templates'));
            }, $templates),
            array_map(static fn (mixed $filter): string => self::template($filter, $at.' > filters'), $filters),
        );
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function fields(array $fields, string $at): ObjectBody
    {
        $read = [];

        foreach ($fields as $name => $field) {
            $read[$name] = $this->field($field, $at.' > '.$name);
        }

        return new ObjectBody($read);
    }

    private function field(mixed $field, string $at): Field
    {
        if (!\is_array($field)) {
            return new TemplateField(self::template($field, $at));
        }

        $count = $field['count'] ?? null;

        if (!\is_string($field['generate'] ?? null) || (null !== $count && (!\is_int($count) || $count < 1))) {
            throw InvalidDefinition::at($at, 'a field is a template, or "generate" with a generator name and an optional "count" of at least 1');
        }

        return new GenerateField($field['generate'], $count);
    }

    /**
     * @param array<string, mixed> $map
     */
    private function grid(array $map, string $at): Body
    {
        $cell = $this->field($map['cell'] ?? null, $at.' > cell');

        if ('graph' === ($map['layout'] ?? null)) {
            $nodes = $map['nodes'] ?? null;
            $extra = $map['extra_edges'] ?? 0;

            if ((!\is_int($nodes) || $nodes < 1) && (!\is_string($nodes) || '' === $nodes) || !\is_int($extra) || $extra < 0) {
                throw InvalidDefinition::at($at, 'a graph has "nodes", a number of at least 1 or a dice expression, and "extra_edges" of at least 0');
            }

            return new GraphBody($nodes, $extra, $cell);
        }

        $layout = MapLayout::tryFrom(\is_string($map['layout'] ?? null) ? $map['layout'] : '');
        $width = $map['width'] ?? null;
        $height = $map['height'] ?? null;

        if (null === $layout || !\is_int($width) || !\is_int($height) || $width < 1 || $height < 1) {
            throw InvalidDefinition::at($at, 'a map has a "layout" — square, hex or graph — and a square or hex map a "width" and a "height" of at least 1');
        }

        return new GridBody($layout, $width, $height, $cell);
    }

    /**
     * @return array<string, mixed>
     */
    private function map(mixed $value, string $at): array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw InvalidDefinition::at($at, 'a map of names is expected');
        }

        $map = [];

        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string>         $allowed
     */
    private function keys(array $map, array $allowed, string $at): void
    {
        $unknown = array_diff(array_keys($map), $allowed);

        if ([] !== $unknown) {
            throw InvalidDefinition::at($at, \sprintf('unknown keys "%s"; the keys are "%s"', implode('", "', $unknown), implode('", "', $allowed)));
        }
    }

    private function name(mixed $name, string $at): string
    {
        // A key made of digits is an integer in PHP.
        $name = \is_int($name) ? (string) $name : $name;

        if (!\is_string($name) || !self::isName($name)) {
            throw InvalidDefinition::at($at, \sprintf('"%s" is not a name: letters, digits, "_" and "-", in parts joined by dots', \is_scalar($name) ? (string) $name : get_debug_type($name)));
        }

        return $name;
    }

    private static function template(mixed $source, string $at): string
    {
        if (!\is_string($source)) {
            throw InvalidDefinition::at($at, 'a template is a text');
        }

        return $source;
    }

    private static function scalar(mixed $value, string $at): bool|float|int|string
    {
        if (!\is_scalar($value)) {
            throw InvalidDefinition::at($at, 'a field is a scalar or a sequence of scalars');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function scalars(mixed $values, string $at): array
    {
        $values = \is_array($values) ? $values : [$values];

        return array_values(array_map(static fn (mixed $value): string => (string) self::scalar($value, $at), $values));
    }
}
