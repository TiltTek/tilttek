<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Internal;

use Tilttek\RandomCharacter\Exception\InvalidDocument;

/**
 * One mapping of a decoded document, read field by field, each message naming the field's place.
 *
 * @internal
 */
final readonly class Fields
{
    /**
     * @param array<string, mixed> $data
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

        if (!\is_string($value)) {
            throw InvalidDocument::at($this->at($name), 'a text is expected.');
        }

        return $value;
    }

    /**
     * @throws InvalidDocument when the field is present and not a text
     */
    public function optionalString(string $name): ?string
    {
        return $this->has($name) ? $this->string($name) : null;
    }

    /**
     * @throws InvalidDocument when the field is present and not a whole number
     */
    public function optionalInt(string $name, int $default): int
    {
        if (!$this->has($name)) {
            return $default;
        }

        return \is_int($this->data[$name]) ? $this->data[$name] : throw InvalidDocument::at($this->at($name), 'a whole number is expected.');
    }

    /**
     * @throws InvalidDocument when the field is present and not true or false
     */
    public function optionalBool(string $name): bool
    {
        if (!$this->has($name)) {
            return false;
        }

        return \is_bool($this->data[$name]) ? $this->data[$name] : throw InvalidDocument::at($this->at($name), 'true or false is expected.');
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
}
