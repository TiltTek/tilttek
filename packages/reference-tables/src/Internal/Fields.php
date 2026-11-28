<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Internal;

use Tilttek\ReferenceTables\Exception\InvalidDocument;
use Tilttek\RpgSystems\Exception\RpgSystemsException;
use Tilttek\RpgSystems\Translations;

/**
 * One mapping of a decoded document, read field by field, each message naming the field's place.
 *
 * @internal
 */
final readonly class Fields
{
    /**
     * @param array<array-key, mixed> $data
     */
    private function __construct(public array $data, public string $path)
    {
    }

    /**
     * @param list<string> $allowed the names the mapping may hold
     *
     * @throws InvalidDocument when the value is not a mapping of names, or holds a name it may not
     */
    public static function of(mixed $value, string $path, array $allowed): self
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw InvalidDocument::at($path, 'a mapping of names to values is expected.');
        }

        $data = [];

        foreach ($value as $name => $item) {
            if (!\in_array($name, $allowed, true)) {
                throw InvalidDocument::at($path, \sprintf('"%s" is not one of %s.', $name, implode(', ', $allowed)));
            }

            $data[$name] = $item;
        }

        return new self($data, $path);
    }

    public function at(string $name): string
    {
        return '' === $this->path ? $name : $this->path.'.'.$name;
    }

    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * @throws InvalidDocument when the field is missing or not a text
     */
    public function string(string $name): string
    {
        $value = $this->data[$name] ?? null;

        // A page or a key written 12 in YAML is read as a number: it stands for the text it was written as.
        if (!\is_string($value) && !\is_int($value)) {
            throw InvalidDocument::at($this->at($name), 'a text is expected.');
        }

        return (string) $value;
    }

    /**
     * @throws InvalidDocument when the field is present and not a text
     */
    public function optionalString(string $name): ?string
    {
        return $this->has($name) ? $this->string($name) : null;
    }

    /**
     * @return list<mixed>
     *
     * @throws InvalidDocument when the field is not a list of 1 to `$max` items
     */
    public function list(string $name, int $max): array
    {
        $value = $this->data[$name] ?? null;

        if (!\is_array($value) || [] === $value || !array_is_list($value) || \count($value) > $max) {
            throw InvalidDocument::at($this->at($name), \sprintf('a list of 1 to %d items is expected.', $max));
        }

        return $value;
    }

    /**
     * Texts by locale, `{en: Hostile, fr: Hostile}`; a single text is the default locale's.
     *
     * @throws InvalidDocument
     */
    public function texts(string $name, string $locale): Translations
    {
        return self::translations($this->data[$name] ?? null, $this->at($name), $locale);
    }

    /**
     * @throws InvalidDocument
     */
    public static function translations(mixed $value, string $path, string $locale): Translations
    {
        if (\is_string($value)) {
            $value = [$locale => $value];
        }

        if (!\is_array($value) || [] === $value || array_is_list($value)) {
            throw InvalidDocument::at($path, 'a text, or texts by locale, is expected.');
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            // A numeric key, `5`, is read as an integer: no locale is written so.
            if (!\is_string($locale)) {
                throw InvalidDocument::at($path, \sprintf('"%d" is not a locale such as "en", "fr_CA" or "es-419".', $locale));
            }

            if (!\is_string($text)) {
                throw InvalidDocument::at($path.'.'.$locale, 'a text is expected.');
            }

            $texts[$locale] = $text;
        }

        try {
            return new Translations($texts, 'The text');
        } catch (RpgSystemsException $rpgSystemsException) {
            throw InvalidDocument::at($path, lcfirst($rpgSystemsException->getMessage()), $rpgSystemsException);
        }
    }
}
