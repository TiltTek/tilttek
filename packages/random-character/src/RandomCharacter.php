<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter;

use Tilttek\Generator\Definition\Attribution;
use Tilttek\RandomCharacter\Exception\InvalidEdit;

/**
 * A character rolled by creation rules: its attribute values, its fields, the seed that rolls it again, the
 * attributions of the generators it drew from, and what was changed by hand since.
 *
 * It is an immutable value: an edit gives a new character, the seed kept, the value marked as edited.
 */
final readonly class RandomCharacter
{
    /** The longest text an edit gives a field, in characters. */
    public const int TEXT_LENGTH = 4_096;

    /**
     * @param string                                      $rules        the key of the creation rules
     * @param string                                      $system       the key of their system
     * @param string                                      $seed         the seed that rolls the same character with the same rules and tables
     * @param array<string, int>                          $stats        the attribute values, by key, in the order of the rules
     * @param array<string, int|string|array<mixed>|null> $fields       the field values, by key, in the order of the rules
     * @param list<Attribution>                           $attributions the sources of the tables the fields were drawn from, each once
     * @param list<string>                                $edited       the values changed by hand, `stats.str` or `fields.name`, each once
     */
    public function __construct(
        public string $rules,
        public string $system,
        public string $seed,
        public array $stats = [],
        public array $fields = [],
        public array $attributions = [],
        public array $edited = [],
    ) {
    }

    /**
     * @throws InvalidEdit when the character has no such attribute
     */
    public function stat(string $key): int
    {
        return $this->stats[$key] ?? throw InvalidEdit::because(\sprintf('The character has no attribute "%s".', $key));
    }

    /**
     * @return int|string|array<mixed>|null
     *
     * @throws InvalidEdit when the character has no such field
     */
    public function field(string $key): int|string|array|null
    {
        if (!\array_key_exists($key, $this->fields)) {
            throw InvalidEdit::because(\sprintf('The character has no field "%s".', $key));
        }

        return $this->fields[$key];
    }

    /**
     * The character with an attribute set by hand. The system's bounds are the sheet's to check.
     *
     * @throws InvalidEdit when the character has no such attribute, or the value is beyond `Stats::MAX_VALUE`
     */
    public function withStat(string $key, int $value): self
    {
        if (!isset($this->stats[$key])) {
            throw InvalidEdit::because(\sprintf('The character has no attribute "%s".', $key));
        }

        if (abs($value) > Stats::MAX_VALUE) {
            throw InvalidEdit::because(\sprintf('An attribute lies from %d to %d.', -Stats::MAX_VALUE, Stats::MAX_VALUE));
        }

        $stats = $this->stats;
        $stats[$key] = $value;

        return new self($this->rules, $this->system, $this->seed, $stats, $this->fields, $this->attributions, $this->mark('stats.'.$key));
    }

    /**
     * The character with a field set by hand: a number, a text, or a list of up to `Field::MAX_COUNT` of them; any
     * other value is refused.
     *
     * @param int|string|array<mixed> $value
     *
     * @throws InvalidEdit when the character has no such field, or the value is not one it may take
     */
    public function withField(string $key, int|string|array $value): self
    {
        if (!\array_key_exists($key, $this->fields)) {
            throw InvalidEdit::because(\sprintf('The character has no field "%s".', $key));
        }

        if (\is_array($value)) {
            if (!array_is_list($value) || \count($value) > Field::MAX_COUNT) {
                throw InvalidEdit::because(\sprintf('A field holds a list of at most %d values.', Field::MAX_COUNT));
            }

            foreach ($value as $item) {
                $this->check($item);
            }
        } else {
            $this->check($value);
        }

        $fields = $this->fields;
        $fields[$key] = $value;

        return new self($this->rules, $this->system, $this->seed, $this->stats, $fields, $this->attributions, $this->mark('fields.'.$key));
    }

    /**
     * Whether a value was changed by hand since the roll: the seed alone no longer gives this character.
     */
    public function isEdited(): bool
    {
        return [] !== $this->edited;
    }

    /**
     * The character as plain arrays — what an application keeps or serves as JSON.
     *
     * @return array{rules: string, system: string, seed: string, stats: array<string, int>, fields: array<string, int|string|array<mixed>|null>, attributions: list<array{source?: string, licence?: string, attribution?: string}>, edited: list<string>}
     */
    public function toArray(): array
    {
        return [
            'rules' => $this->rules,
            'system' => $this->system,
            'seed' => $this->seed,
            'stats' => $this->stats,
            'fields' => $this->fields,
            'attributions' => array_map(static fn (Attribution $attribution): array => $attribution->toArray(), $this->attributions),
            'edited' => $this->edited,
        ];
    }

    /**
     * @return list<string>
     */
    private function mark(string $value): array
    {
        return \in_array($value, $this->edited, true) ? $this->edited : [...$this->edited, $value];
    }

    /**
     * @throws InvalidEdit
     */
    private function check(mixed $value): void
    {
        if (\is_int($value)) {
            return;
        }

        if (!\is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > self::TEXT_LENGTH) {
            throw InvalidEdit::because(\sprintf('A field holds numbers, or texts in UTF-8 of at most %d characters.', self::TEXT_LENGTH));
        }
    }
}
