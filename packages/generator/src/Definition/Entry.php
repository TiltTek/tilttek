<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use ArrayAccess;
use LogicException;
use Override;
use Stringable;
use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * One entry of a list: its text, its weight, its fields — a grammatical gender, a price, a plural form —, its tags, on
 * which a pick filters, and where its text comes from when it is not where its list comes from. Printed in a template, an entry is its text; `entry.price` reads a field, `entry.tags` the
 * tags.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class Entry implements ArrayAccess, Stringable
{
    /**
     * The names an entry keeps for itself; a field cannot take them.
     */
    public const array RESERVED = ['text', 'weight', 'tags', 'source', 'licence', 'attribution'];

    /**
     * @param array<string, scalar|list<scalar>> $fields
     * @param array<string, list<string>>        $tags
     */
    public function __construct(
        public string $text,
        public int $weight = 1,
        private array $fields = [],
        private array $tags = [],
        public ?Attribution $attribution = null,
    ) {
        if ($weight < 1) {
            throw InvalidDefinition::at($text, 'the weight of an entry is a whole number of at least 1');
        }

        foreach (self::RESERVED as $name) {
            if (\array_key_exists($name, $fields)) {
                throw InvalidDefinition::at($text, \sprintf('"%s" is not a field name', $name));
            }
        }
    }

    /**
     * @return array<string, scalar|list<scalar>>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, list<string>>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * Whether every tag asked for has one of the values asked for.
     *
     * @param array<string, list<string>> $where
     */
    public function matches(array $where): bool
    {
        return array_all($where, fn (array $values, int|string $tag): bool => [] !== array_intersect($this->tags[$tag] ?? [], $values));
    }

    /**
     * A field, or the first value of the tag of that name when no field has it.
     */
    public function grammar(string $name): ?string
    {
        $value = $this->fields[$name] ?? $this->tags[$name][0] ?? null;

        return \is_scalar($value) ? (string) $value : null;
    }

    /**
     * The entry as data: its text and its fields.
     *
     * @return array<string, scalar|list<scalar>>
     */
    public function toArray(): array
    {
        return ['text' => $this->text] + $this->fields;
    }

    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return 'text' === $offset || 'tags' === $offset || \array_key_exists($offset, $this->fields);
    }

    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'text' => $this->text,
            'tags' => $this->tags,
            default => $this->fields[$offset] ?? null,
        };
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('An entry cannot be changed.');
    }

    #[Override]
    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('An entry cannot be changed.');
    }

    #[Override]
    public function __toString(): string
    {
        return $this->text;
    }
}
