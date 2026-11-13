<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls;

use Tilttek\SecretRolls\Exception\InvalidSecretRoll;

/**
 * The checks and the canonical JSON the commitments and the hashes of the journal are computed over.
 *
 * @internal
 */
final class Encoding
{
    /** The label every hashed document starts with, so that a hash of this library is never one of another format. */
    public const string VERSION = 'tilttek-secret-rolls/1';

    /** The longest scene identifier, in bytes. */
    public const int MAX_SCENE = 128;

    /** The longest roller reference, in bytes. */
    public const int MAX_ROLLER = 320;

    /** The longest context, in bytes. */
    public const int MAX_CONTEXT = 1024;

    private function __construct()
    {
    }

    /**
     * The SHA-256, in hexadecimal, of the canonical JSON of the values: no escaped slash or Unicode character, so that
     * any tool that writes the same JSON computes the same hash.
     *
     * @param list<mixed> $values
     */
    public static function hash(array $values): string
    {
        return hash('sha256', self::json($values));
    }

    public static function json(mixed $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @throws InvalidSecretRoll when the scene is not 1 to 128 letters, digits, dots, colons, underscores or hyphens
     */
    public static function scene(string $scene): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9._:-]{1,'.self::MAX_SCENE.'}$/D', $scene)) {
            throw InvalidSecretRoll::because(\sprintf('A scene is 1 to %d letters, digits, dots, colons, underscores or hyphens.', self::MAX_SCENE));
        }

        return $scene;
    }

    /**
     * Text the application gives — a roller, a context —, checked: null, or 1 to `$max` bytes of UTF-8 without a
     * control character.
     *
     * @throws InvalidSecretRoll
     */
    public static function text(?string $text, string $what, int $max): ?string
    {
        if (null !== $text && ('' === $text || \strlen($text) > $max || 1 !== preg_match('/^\P{Cc}+$/Du', $text))) {
            throw InvalidSecretRoll::because(\sprintf('A %s is 1 to %d bytes of UTF-8 text without a control character.', $what, $max));
        }

        return $text;
    }

    /**
     * @throws InvalidSecretRoll when the value is not 64 lower-case hexadecimal digits
     */
    public static function hex64(string $hex, string $what): string
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $hex)) {
            throw InvalidSecretRoll::because(\sprintf('A %s is 64 lower-case hexadecimal digits.', $what));
        }

        return $hex;
    }

    /**
     * The identifier of the roll committed at a position of a scene: 32 hexadecimal digits, the same every time.
     */
    public static function rollId(string $scene, int $position): string
    {
        return substr(self::hash([self::VERSION, $scene, $position]), 0, 32);
    }
}
