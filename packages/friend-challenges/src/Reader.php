<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use BackedEnum;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;

/**
 * Reads the fields of a record from storage, each of the type it must have, and refuses a record with a field missing,
 * of another type or not expected.
 *
 * @internal
 */
final readonly class Reader
{
    /**
     * @param array<array-key, mixed> $record
     * @param list<string>            $fields the fields the record holds, no more and no fewer
     */
    public function __construct(
        private array $record,
        private string $what,
        array $fields,
    ) {
        if (\count($record) !== \count($fields) || \count(array_diff_key(array_flip($fields), $record)) > 0) {
            throw InvalidChallenge::because(\sprintf('%s holds the fields %s.', $what, implode(', ', $fields)));
        }
    }

    public function string(string $field): string
    {
        $value = $this->record[$field];

        if (!\is_string($value)) {
            throw InvalidChallenge::because(\sprintf('The field "%s" of %s is a text.', $field, $this->what));
        }

        return $value;
    }

    public function nullableString(string $field): ?string
    {
        return null === $this->record[$field] ? null : $this->string($field);
    }

    public function int(string $field): int
    {
        $value = $this->record[$field];

        if (!\is_int($value)) {
            throw InvalidChallenge::because(\sprintf('The field "%s" of %s is a whole number.', $field, $this->what));
        }

        return $value;
    }

    public function time(string $field): DateTimeImmutable
    {
        return Instant::read($this->string($field));
    }

    public function nullableTime(string $field): ?DateTimeImmutable
    {
        $value = $this->nullableString($field);

        return null === $value ? null : Instant::read($value);
    }

    public function member(string $field): ResourceReference
    {
        return Check::member($this->string($field));
    }

    public function nullableMember(string $field): ?ResourceReference
    {
        $value = $this->nullableString($field);

        return null === $value ? null : Check::member($value);
    }

    /**
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public function enum(string $field, string $enum): BackedEnum
    {
        return $enum::tryFrom($this->string($field)) ?? throw InvalidChallenge::because(\sprintf('The field "%s" of %s is not one of its values.', $field, $this->what));
    }

    /**
     * The references of a list of distinct members, of `$most` at most.
     *
     * @return list<ResourceReference>
     */
    public function members(string $field, int $most): array
    {
        $members = [];

        foreach ($this->list($field, $most) as $value) {
            if (!\is_string($value)) {
                throw InvalidChallenge::because(\sprintf('The field "%s" of %s lists texts.', $field, $this->what));
            }

            $member = Check::member($value);

            if (isset($members[$member->toString()])) {
                throw InvalidChallenge::because(\sprintf('The field "%s" of %s names %s twice.', $field, $this->what, $value));
            }

            $members[$member->toString()] = $member;
        }

        return array_values($members);
    }

    /**
     * @return list<mixed>
     */
    public function list(string $field, int $most): array
    {
        $value = $this->record[$field];

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidChallenge::because(\sprintf('The field "%s" of %s is a list.', $field, $this->what));
        }

        if (\count($value) > $most) {
            throw InvalidChallenge::because(\sprintf('The field "%s" of %s lists %d items at most.', $field, $this->what, $most));
        }

        return $value;
    }
}
