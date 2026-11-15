<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\Exception\InvalidBounty;

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
     * @throws InvalidBounty
     */
    public static function identifier(string $value, string $what): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,99}$/D', $value)) {
            throw InvalidBounty::because(\sprintf('%s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter or a digit.', $what, self::IDENTIFIER_LENGTH));
        }

        return $value;
    }

    /**
     * An opaque reference to what the application keeps: 1 to 500 bytes without whitespace or control characters.
     *
     * @throws InvalidBounty
     */
    public static function reference(string $value, string $what): string
    {
        if ('' === $value || \strlen($value) > self::REFERENCE_LENGTH || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            throw InvalidBounty::because(\sprintf('%s is 1 to %d bytes without whitespace or control characters.', $what, self::REFERENCE_LENGTH));
        }

        return $value;
    }

    /**
     * @throws InvalidBounty
     */
    public static function member(string $value): ResourceReference
    {
        try {
            return ResourceReference::fromString($value);
        } catch (InvalidResource $invalidResource) {
            throw InvalidBounty::because($invalidResource->getMessage());
        }
    }

    /**
     * A reference given by a caller, which is never of the kind `erased`: only an erasure draws one.
     *
     * @throws InvalidBounty
     */
    public static function notErased(ResourceReference $reference, string $what): void
    {
        if (Pseudonym::isErased($reference)) {
            throw InvalidBounty::erased($what, $reference);
        }
    }
}
