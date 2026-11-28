<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Format;

use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Tilttek\RandomCharacter\CreationRules;
use Tilttek\RandomCharacter\Exception\InvalidDocument;
use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Field;
use Tilttek\RandomCharacter\Internal\Fields;
use Tilttek\RandomCharacter\RulesCatalogue;
use Tilttek\RandomCharacter\StatMethod;
use Tilttek\RandomCharacter\Stats;

/**
 * Reads the creation rules a system declares from a YAML or JSON document of the format `tilttek-random-character/1`,
 * every refusal naming its place in the document. It keeps nothing between two calls: one serves every request.
 */
final readonly class RulesReader
{
    /** The format a document names in its `format` field. */
    public const string FORMAT = 'tilttek-random-character/1';

    /** The largest document read by default, in bytes. */
    public const int MAX_BYTES = 1_048_576;

    private const array DOCUMENT = ['format', 'system', 'rules'];

    private const array RULES = ['key', 'system', 'stats', 'fields'];

    private const array STATS = ['method', 'attributes', 'roll', 'values'];

    private const array FIELD = ['key', 'generator', 'roll', 'count', 'unique'];

    /**
     * @param int $maxBytes the largest document read, in bytes
     */
    public function __construct(private int $maxBytes = self::MAX_BYTES)
    {
    }

    /**
     * The rules of a document's text.
     *
     * @throws InvalidDocument
     */
    public function parse(string $text, Syntax $syntax): RulesCatalogue
    {
        return $this->catalogue($this->decode($text, $syntax));
    }

    /**
     * The rules of a file, `.yaml`, `.yml` or `.json`.
     *
     * @throws InvalidDocument
     */
    public function load(string $path): RulesCatalogue
    {
        $syntax = Syntax::ofPath($path);
        $size = is_file($path) && is_readable($path) ? filesize($path) : false;

        if (false === $size) {
            throw InvalidDocument::at('', \sprintf('the file "%s" cannot be read.', basename($path)));
        }

        if ($size > $this->maxBytes) {
            throw InvalidDocument::at('', \sprintf('the file "%s" is longer than %d bytes.', basename($path), $this->maxBytes));
        }

        return $this->parse((string) file_get_contents($path), $syntax);
    }

    /**
     * A document's text decoded, not yet read as rules.
     *
     * @return array<mixed>
     *
     * @throws InvalidDocument
     */
    public function decode(string $text, Syntax $syntax): array
    {
        if (\strlen($text) > $this->maxBytes) {
            throw InvalidDocument::at('', \sprintf('the document is longer than %d bytes.', $this->maxBytes));
        }

        try {
            $document = Syntax::Yaml === $syntax
                ? Yaml::parse($text, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE)
                : json_decode($text, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException|ParseException $exception) {
            throw InvalidDocument::at('', \sprintf('the document is not valid %s: %s', strtoupper($syntax->value), $exception->getMessage()), $exception);
        }

        if (!\is_array($document)) {
            throw InvalidDocument::at('', 'the document is not a mapping.');
        }

        return $document;
    }

    /**
     * The rules of a decoded document.
     *
     * @param array<mixed> $document
     *
     * @throws InvalidDocument
     */
    public function catalogue(array $document): RulesCatalogue
    {
        $fields = Fields::of($document, '', self::DOCUMENT);

        if (!$fields->has('format') || self::FORMAT !== $fields->data['format']) {
            throw InvalidDocument::at('format', \sprintf('the document must name the format "%s".', self::FORMAT));
        }

        $system = $fields->optionalString('system');
        $rules = [];

        foreach ($fields->list('rules', RulesCatalogue::MAX_RULES) as $index => $value) {
            $rules[] = $this->rules($value, \sprintf('rules[%d]', $index), $system);
        }

        return $this->build('rules', static fn (): RulesCatalogue => new RulesCatalogue(...$rules));
    }

    /**
     * @throws InvalidDocument
     */
    private function rules(mixed $value, string $path, ?string $system): CreationRules
    {
        $fields = Fields::of($value, $path, self::RULES);
        $system = $fields->optionalString('system') ?? $system ?? throw InvalidDocument::at($fields->at('system'), 'the rules name no system, and the document none for them.');
        $stats = $fields->has('stats') ? $this->stats($fields->data['stats'], $fields->at('stats')) : null;
        $list = [];

        if ($fields->has('fields')) {
            foreach ($fields->list('fields', CreationRules::MAX_FIELDS) as $index => $field) {
                $list[] = $this->field($field, \sprintf('%s.fields[%d]', $path, $index));
            }
        }

        return $this->build($path, static fn (): CreationRules => new CreationRules($fields->string('key'), $system, $stats, $list));
    }

    /**
     * @throws InvalidDocument
     */
    private function stats(mixed $value, string $path): Stats
    {
        $fields = Fields::of($value, $path, self::STATS);
        $method = StatMethod::tryFrom($fields->string('method'))
            ?? throw InvalidDocument::at($fields->at('method'), \sprintf('the method is one of %s.', implode(', ', array_column(StatMethod::cases(), 'value'))));
        $attributes = [];

        foreach ($fields->list('attributes', Stats::MAX_ATTRIBUTES) as $index => $attribute) {
            $attributes[] = \is_string($attribute) ? $attribute : throw InvalidDocument::at(\sprintf('%s[%d]', $fields->at('attributes'), $index), 'a text is expected.');
        }

        $values = [];

        if ($fields->has('values')) {
            foreach ($fields->list('values', Stats::MAX_ATTRIBUTES) as $index => $number) {
                $values[] = \is_int($number) ? $number : throw InvalidDocument::at(\sprintf('%s[%d]', $fields->at('values'), $index), 'a whole number is expected.');
            }
        }

        return $this->build($path, static fn (): Stats => new Stats($method, $attributes, $fields->optionalString('roll'), $values));
    }

    /**
     * @throws InvalidDocument
     */
    private function field(mixed $value, string $path): Field
    {
        $fields = Fields::of($value, $path, self::FIELD);
        $key = $fields->string('key');
        $count = $fields->optionalInt('count', 1);

        if ($fields->has('generator') === $fields->has('roll')) {
            throw InvalidDocument::at($path, 'a field has either a generator or a roll.');
        }

        if ($fields->has('roll')) {
            if ($fields->has('unique')) {
                throw InvalidDocument::at($fields->at('unique'), 'a rolled field has no "unique".');
            }

            return $this->build($path, static fn (): Field => Field::rolled($key, $fields->string('roll'), $count));
        }

        $unique = $fields->optionalBool('unique');

        return $this->build($path, static fn (): Field => Field::generated($key, $fields->string('generator'), $count, $unique));
    }

    /**
     * Builds a value, a refusal of the rules given back at its place in the document.
     *
     * @template T
     *
     * @param callable(): T $build
     *
     * @return T
     *
     * @throws InvalidDocument
     */
    private function build(string $path, callable $build): mixed
    {
        try {
            return $build();
        } catch (InvalidRules $invalidRules) {
            throw InvalidDocument::at($path, lcfirst($invalidRules->getMessage()), $invalidRules);
        }
    }
}
