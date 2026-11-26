<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use Phil\Resource\ResourceReference;

/**
 * A file a handout shows — a map, a letter, a portrait, a recording —, named by the reference the application's
 * storage gives it, with its media type and a description that stands in for it where it cannot be seen or heard.
 * The library never reads, stores nor serves the file: the application does, after it checks the player may see the
 * handout.
 *
 * The description is 1 to 500 characters of UTF-8 without control characters nor surrounding spaces.
 */
final readonly class Attachment
{
    public const int MAX_DESCRIPTION = 500;

    public function __construct(
        public ResourceReference $file,
        public string $mediaType,
        public string $description,
    ) {
        MediaTypes::check($mediaType);
        Text::line($description, 'The description of an attachment', self::MAX_DESCRIPTION);
    }
}
