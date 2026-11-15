<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;

/**
 * The checks of what the library takes: identifiers, references, members and texts.
 *
 * @internal
 */
final class Check
{
    public const int IDENTIFIER_LENGTH = 100;

    public const int REFERENCE_LENGTH = 500;

    private function __construct()
    {
    }

    /**
     * An identifier chosen by the application: 1 to 100 letters, digits, "_", ".", ":" or "-", starting with a letter or
     * a digit — a ULID, a UUID, `summer-2026.7`.
     *
     * @throws InvalidChallenge
     */
    public static function identifier(string $value, string $what): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,99}$/D', $value)) {
            throw InvalidChallenge::because(\sprintf('%s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter or a digit.', $what, self::IDENTIFIER_LENGTH));
        }

        return $value;
    }

    /**
     * An opaque reference to what the application keeps: 1 to 500 bytes without whitespace or control characters.
     *
     * @throws InvalidChallenge
     */
    public static function reference(string $value, string $what): string
    {
        if ('' === $value || \strlen($value) > self::REFERENCE_LENGTH || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            throw InvalidChallenge::because(\sprintf('%s is 1 to %d bytes without whitespace or control characters.', $what, self::REFERENCE_LENGTH));
        }

        return $value;
    }

    /**
     * A text a member writes, in UTF-8, trimmed, of `$most` characters at most; a line break only where `$lines` allows
     * it, and no other control character.
     *
     * @throws InvalidChallenge
     */
    public static function text(string $value, string $what, int $most, bool $required, bool $lines = false): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw InvalidChallenge::because(\sprintf('%s is not UTF-8.', $what));
        }

        $value = trim($value);
        $forbidden = $lines ? '/[\x00-\x09\x0B-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';

        if (1 === preg_match($forbidden, $value)) {
            throw InvalidChallenge::because(\sprintf('%s holds a control character.', $what));
        }

        if ($required && '' === $value) {
            throw InvalidChallenge::because(\sprintf('%s is required.', $what));
        }

        if (mb_strlen($value) > $most) {
            throw InvalidChallenge::because(\sprintf('%s is %d characters at most.', $what, $most));
        }

        return $value;
    }

    /**
     * @throws InvalidChallenge
     */
    public static function member(string $value): ResourceReference
    {
        try {
            return ResourceReference::fromString($value);
        } catch (InvalidResource $invalidResource) {
            throw InvalidChallenge::because($invalidResource->getMessage());
        }
    }

    /**
     * A reference given by a caller, which is never of the kind `erased`: only an erasure draws one.
     *
     * @throws InvalidChallenge
     */
    public static function notErased(ResourceReference $reference, string $what): void
    {
        if (Pseudonym::isErased($reference)) {
            throw InvalidChallenge::erased($what, $reference);
        }
    }
}
