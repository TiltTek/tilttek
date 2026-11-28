<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter;

use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Internal\Check;

/**
 * How a system makes a random character: its attribute values and its other fields, in the order they are rolled.
 */
final readonly class CreationRules
{
    /** The most fields of one set of rules. */
    public const int MAX_FIELDS = 64;

    /**
     * @var array<string, Field>
     */
    private array $byKey;

    /**
     * @param string      $key    the key of the rules, `lanterns.wanderer`
     * @param string      $system the key of the system in `tilttek/rpg-systems` whose attributes the stats fill
     * @param list<Field> $fields rolled in this order: a field's generator names only fields before it
     *
     * @throws InvalidRules
     */
    public function __construct(
        public string $key,
        public string $system,
        public ?Stats $stats = null,
        public array $fields = [],
    ) {
        Check::key($key, 'creation rules');
        Check::key($system, 'a system');

        if (!$stats instanceof Stats && [] === $fields) {
            throw InvalidRules::because(\sprintf('The creation rules "%s" have stats, fields, or both.', $key));
        }

        if (\count($fields) > self::MAX_FIELDS) {
            throw InvalidRules::because(\sprintf('The creation rules "%s" have at most %d fields.', $key, self::MAX_FIELDS));
        }

        $byKey = [];

        foreach ($fields as $field) {
            if (isset($byKey[$field->key])) {
                throw InvalidRules::because(\sprintf('The creation rules "%s" have two fields "%s".', $key, $field->key));
            }

            foreach ($field->references as $reference) {
                if (!isset($byKey[$reference])) {
                    throw InvalidRules::because(\sprintf('The field "%s" names its generator by the field "%s", which is not before it.', $field->key, $reference));
                }

                if (1 !== $byKey[$reference]->count) {
                    throw InvalidRules::because(\sprintf('The field "%s" names its generator by the field "%s", which gives a list.', $field->key, $reference));
                }
            }

            $byKey[$field->key] = $field;
        }

        $this->byKey = $byKey;
    }

    public function hasField(string $key): bool
    {
        return isset($this->byKey[$key]);
    }

    /**
     * @throws InvalidRules when the rules have no such field
     */
    public function field(string $key): Field
    {
        return $this->byKey[$key] ?? throw InvalidRules::because(\sprintf('The creation rules "%s" have no field "%s".', $this->key, $key));
    }
}
