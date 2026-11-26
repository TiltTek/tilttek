<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use Tilttek\Handouts\Exception\InvalidHandout;

/**
 * The media types an attachment may have, so that a handout never points players at a file the application cannot
 * show safely. By default: PNG, JPEG, WebP, AVIF and GIF images, PDF documents, MP3 and Ogg sounds, MP4 and WebM
 * videos, and plain text. SVG is not among them: an application that sanitises its SVG files adds `image/svg+xml`.
 *
 * A media type is written `type/subtype` in lowercase, 3 to 255 characters, without parameters.
 */
final readonly class MediaTypes
{
    public const array DEFAULTS = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/avif',
        'image/gif',
        'application/pdf',
        'audio/mpeg',
        'audio/ogg',
        'video/mp4',
        'video/webm',
        'text/plain',
    ];

    public const int MAX_TYPES = 100;

    private const string FORMAT = '/^(?=.{3,255}\z)[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*\z/';

    /** @var array<string, string> */
    private array $allowed;

    /**
     * @param list<string> $types 1 to 100 media types
     *
     * @throws InvalidHandout
     */
    public function __construct(array $types = self::DEFAULTS)
    {
        if ([] === $types || \count($types) > self::MAX_TYPES) {
            throw InvalidHandout::because(\sprintf('The allowed media types are 1 to %d; %d are given.', self::MAX_TYPES, \count($types)));
        }

        $allowed = [];

        foreach ($types as $type) {
            $allowed[self::check($type)] = $type;
        }

        $this->allowed = $allowed;
    }

    /**
     * @throws InvalidHandout
     */
    public static function check(string $type): string
    {
        if (1 !== preg_match(self::FORMAT, $type)) {
            throw InvalidHandout::because(\sprintf('A media type is "type/subtype" in lowercase, 3 to 255 characters, without parameters; "%s" is not.', $type));
        }

        return $type;
    }

    public function allows(string $type): bool
    {
        return isset($this->allowed[$type]);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->allowed);
    }
}
