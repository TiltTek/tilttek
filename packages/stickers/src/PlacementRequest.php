<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

use JsonException;
use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Tilttek\Stickers\Exception\InvalidSticker;

/**
 * What a holder asks to place, read from a form or the JSON a sticker editor sends: the sticker's code, the surface as
 * a resource reference, and the transform. Reading checks shapes and absolute bounds; the rules of the site are
 * checked when the request is placed.
 */
final readonly class PlacementRequest
{
    public const int MAX_JSON_LENGTH = 4096;

    private const array KEYS = ['sticker', 'surface', 'x', 'y', 'scale', 'rotation'];

    /**
     * @throws InvalidSticker when the sticker's code or the surface is refused
     */
    public function __construct(
        public string $sticker,
        public ResourceReference $surface,
        public Transform $transform,
    ) {
        Guard::code($sticker, 'sticker');
        Guard::reference($surface, 'surface');
    }

    /**
     * @throws InvalidSticker when the value is not a request of the keys `sticker`, `surface`, `x`, `y` and the
     *                        optional `scale` and `rotation`, of their types and within their bounds
     */
    public static function fromArray(mixed $data): self
    {
        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw InvalidSticker::because('A placement request is an object of keys.');
        }

        foreach (array_keys($data) as $key) {
            if (!\in_array($key, self::KEYS, true)) {
                throw InvalidSticker::because(\sprintf('A placement request has no key "%s".', substr((string) $key, 0, 64)));
            }
        }

        $sticker = $data['sticker'] ?? null;
        $surface = $data['surface'] ?? null;

        if (!\is_string($sticker) || !\is_string($surface)) {
            throw InvalidSticker::because('A placement request names its sticker and its surface as strings.');
        }

        try {
            $reference = ResourceReference::fromString($surface);
        } catch (InvalidResource $invalidResource) {
            throw InvalidSticker::because('The surface of a placement request is a resource reference: '.$invalidResource->getMessage());
        }

        return new self($sticker, $reference, new Transform(
            self::int($data, 'x', null),
            self::int($data, 'y', null),
            self::int($data, 'scale', 100),
            self::int($data, 'rotation', 0),
        ));
    }

    /**
     * @throws InvalidSticker when the text is not the JSON of a request, or longer than {@see self::MAX_JSON_LENGTH}
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_JSON_LENGTH) {
            throw InvalidSticker::because(\sprintf('The JSON of a placement request is at most %d bytes.', self::MAX_JSON_LENGTH));
        }

        try {
            $data = json_decode($json, true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidSticker::because('The JSON of a placement request does not read: '.$jsonException->getMessage());
        }

        return self::fromArray($data);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function int(array $data, string $key, ?int $default): int
    {
        $value = \array_key_exists($key, $data) ? $data[$key] : $default;

        if (!\is_int($value)) {
            throw InvalidSticker::because(\sprintf('The "%s" of a placement request is an integer.', $key));
        }

        return $value;
    }
}
