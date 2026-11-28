<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Format;

use Tilttek\RpgSystems\Exception\InvalidDocument;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Translations;

/**
 * One mapping of a decoded document, read field by field with the place of each in the messages.
 *
 * @internal
 */
final readonly class Fields
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data, public string $path)
    {
    }

    /**
     * @throws InvalidDocument when the value is not a mapping of names
     */
    public static function of(mixed $value, string $path): self
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw InvalidDocument::at($path, 'a mapping of names to values is expected.');
        }

        $data = [];

        foreach ($value as $name => $item) {
            if (!\is_string($name)) {
                throw InvalidDocument::at($path, \sprintf('the name "%s" is not a text.', $name));
            }

            $data[$name] = $item;
        }

        return new self($data, $path);
    }

    /**
     * @param list<string> $allowed
     *
     * @throws InvalidDocument when the mapping holds another name
     */
    public function only(array $allowed): self
    {
        foreach (array_keys($this->data) as $name) {
            if (!\in_array($name, $allowed, true)) {
                throw InvalidDocument::at($this->path, \sprintf('"%s" is not one of %s.', $name, implode(', ', $allowed)));
            }
        }

        return $this;
    }

    public function value(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * @return ($required is true ? string : ?string)
     *
     * @throws InvalidDocument
     */
    public function string(string $name, bool $required = true): ?string
    {
        $value = $this->data[$name] ?? null;

        if (null === $value && !$required) {
            return null;
        }

        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            throw InvalidDocument::at($this->at($name), 'a text is expected.');
        }

        // A YAML version written 5.2 is read as a number: it stands for the text it was written as.
        return (string) $value;
    }

    /**
     * @throws InvalidDocument
     */
    public function int(string $name): ?int
    {
        $value = $this->data[$name] ?? null;

        if (null !== $value && !\is_int($value)) {
            throw InvalidDocument::at($this->at($name), 'a whole number is expected.');
        }

        return $value;
    }

    /**
     * @throws InvalidDocument
     */
    public function map(string $name): self
    {
        return self::of($this->data[$name] ?? [], $this->at($name));
    }

    /**
     * @return list<mixed>
     *
     * @throws InvalidDocument
     */
    public function list(string $name, int $max): array
    {
        $value = $this->data[$name] ?? [];

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidDocument::at($this->at($name), 'a list is expected.');
        }

        if (\count($value) > $max) {
            throw InvalidDocument::at($this->at($name), \sprintf('more than %d items.', $max));
        }

        return $value;
    }

    /**
     * Texts by locale, `{en: Strength, fr: Force}`; a single text stands for the default locale's.
     *
     * @throws InvalidDocument
     */
    public function texts(string $name, string $defaultLocale): Translations
    {
        $value = $this->data[$name] ?? null;

        if (null === $value) {
            return new Translations();
        }

        $texts = \is_string($value) ? [$defaultLocale => $value] : [];

        if (!\is_string($value)) {
            $fields = self::of($value, $this->at($name));

            foreach ($fields->names() as $locale) {
                $texts[$locale] = $fields->string($locale);
            }
        }

        return $this->record(static fn (): Translations => new Translations($texts, 'The text'), $name);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->data);
    }

    public function at(string $name): string
    {
        return '' === $this->path ? $name : $this->path.'.'.$name;
    }

    /**
     * Makes a record, its refusal given back with the place it was read from.
     *
     * @template T
     *
     * @param callable(): T $make
     *
     * @return T
     *
     * @throws InvalidDocument
     */
    public function record(callable $make, ?string $name = null): mixed
    {
        try {
            return $make();
        } catch (InvalidRecord $invalidRecord) {
            throw InvalidDocument::at(null === $name ? $this->path : $this->at($name), $invalidRecord->getMessage(), $invalidRecord);
        }
    }
}
