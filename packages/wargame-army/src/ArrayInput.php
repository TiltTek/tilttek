<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use JsonException;
use Tilttek\WargameArmy\Exception\InvalidArmyList;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;

/**
 * The reading of a decoded array — a catalogue or an army list from configuration, JSON or a form —: its keys, then
 * each value with its type, every fault the exception of what is read.
 *
 * @internal
 */
final readonly class ArrayInput
{
    /**
     * The longest JSON text read, in bytes.
     */
    public const int JSON_BYTES = 4_194_304;

    public function __construct(private Fault $fault)
    {
    }

    /**
     * @throws InvalidCatalogue|InvalidArmyList when the text is longer than 4 MiB or is not JSON
     */
    public function json(string $json, string $what): mixed
    {
        if (\strlen($json) > self::JSON_BYTES) {
            throw $this->fault->because(\sprintf('%s in JSON has at most %d bytes.', ucfirst(Identifiers::a($what)), self::JSON_BYTES));
        }

        try {
            return json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw $this->fault->because(\sprintf('%s in JSON does not read: %s.', ucfirst(Identifiers::a($what)), $jsonException->getMessage()));
        }
    }

    /**
     * The array itself, once it holds every required key and no other key than the optional ones.
     *
     * @param list<string> $required
     * @param list<string> $optional
     *
     * @return array<string, mixed>
     *
     * @throws InvalidCatalogue|InvalidArmyList when the value is not an array of those keys
     */
    public function object(mixed $data, string $what, array $required, array $optional = []): array
    {
        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw $this->fault->because(\sprintf('%s is an object of keys.', ucfirst(Identifiers::a($what))));
        }

        $object = [];

        foreach ($data as $key => $value) {
            if (!\in_array($key, $required, true) && !\in_array($key, $optional, true)) {
                throw $this->fault->because(\sprintf('%s has no key "%s".', ucfirst(Identifiers::a($what)), substr((string) $key, 0, 64)));
            }

            $object[$key] = $value;
        }

        foreach ($required as $key) {
            if (!\array_key_exists($key, $object)) {
                throw $this->fault->because(\sprintf('%s needs its "%s".', ucfirst(Identifiers::a($what)), $key));
            }
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws InvalidCatalogue|InvalidArmyList when the value is not a string
     */
    public function string(array $object, string $key, string $what): string
    {
        $value = $object[$key] ?? null;

        if (!\is_string($value)) {
            throw $this->fault->because(\sprintf('The "%s" of %s is a string.', $key, Identifiers::a($what)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws InvalidCatalogue|InvalidArmyList when the value is present and neither a string nor null
     */
    public function nullableString(array $object, string $key, string $what): ?string
    {
        $value = $object[$key] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw $this->fault->because(\sprintf('The "%s" of %s is a string or null.', $key, Identifiers::a($what)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws InvalidCatalogue|InvalidArmyList when the value is present and not an integer
     */
    public function int(array $object, string $key, string $what, ?int $default = null): int
    {
        $value = \array_key_exists($key, $object) ? $object[$key] : $default;

        if (!\is_int($value)) {
            throw $this->fault->because(\sprintf('The "%s" of %s is an integer.', $key, Identifiers::a($what)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws InvalidCatalogue|InvalidArmyList when the value is present and neither an integer nor null
     */
    public function nullableInt(array $object, string $key, string $what): ?int
    {
        $value = $object[$key] ?? null;

        if (null !== $value && !\is_int($value)) {
            throw $this->fault->because(\sprintf('The "%s" of %s is an integer or null.', $key, Identifiers::a($what)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws InvalidCatalogue|InvalidArmyList when the value is present and not a boolean
     */
    public function bool(array $object, string $key, string $what): bool
    {
        $value = $object[$key] ?? false;

        if (!\is_bool($value)) {
            throw $this->fault->because(\sprintf('The "%s" of %s is true or false.', $key, Identifiers::a($what)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @return list<mixed>
     *
     * @throws InvalidCatalogue|InvalidArmyList when the value is present and not a list of at most `$max` entries
     */
    public function list(array $object, string $key, string $what, int $max): array
    {
        $value = $object[$key] ?? [];

        if (!\is_array($value) || !array_is_list($value)) {
            throw $this->fault->because(\sprintf('The "%s" of %s is a list.', $key, Identifiers::a($what)));
        }

        if (\count($value) > $max) {
            throw $this->fault->because(\sprintf('%s has at most %d "%s".', ucfirst(Identifiers::a($what)), $max, $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @return list<string>
     *
     * @throws InvalidCatalogue|InvalidArmyList when the value is present and not a list of at most `$max` strings
     */
    public function strings(array $object, string $key, string $what, int $max): array
    {
        $strings = [];

        foreach ($this->list($object, $key, $what, $max) as $value) {
            if (!\is_string($value)) {
                throw $this->fault->because(\sprintf('The "%s" of %s are strings.', $key, Identifiers::a($what)));
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * A map of string keys to integers, such as option codes to their counts.
     *
     * @param array<string, mixed> $object
     *
     * @return array<array-key, int>
     *
     * @throws InvalidCatalogue|InvalidArmyList when the value is present and not an object of at most `$max` integers
     */
    public function counts(array $object, string $key, string $what, int $max): array
    {
        $value = $object[$key] ?? [];

        // An object whose keys are 0, 1, 2… decodes as a list: its keys are kept as the strings they were.
        if (!\is_array($value)) {
            throw $this->fault->because(\sprintf('The "%s" of %s is an object of integers.', $key, Identifiers::a($what)));
        }

        if (\count($value) > $max) {
            throw $this->fault->because(\sprintf('%s has at most %d "%s".', ucfirst(Identifiers::a($what)), $max, $key));
        }

        $counts = [];

        foreach ($value as $name => $count) {
            if (!\is_int($count)) {
                throw $this->fault->because(\sprintf('The "%s" of %s is an object of integers.', $key, Identifiers::a($what)));
            }

            $counts[(string) $name] = $count;
        }

        return $counts;
    }
}
