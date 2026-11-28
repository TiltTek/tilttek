<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Package;

use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Text;

/**
 * A file of the source a data package was imported from — the PDF or the text the publisher distributes —, with the
 * address it was taken from, its size and its SHA-256 digest, so that anyone can check the import against it.
 */
final readonly class UpstreamFile
{
    /** The largest upstream file a manifest names, in bytes. */
    public const int MAX_BYTES = 2_000_000_000;

    public Url $url;

    /** The language of the file's text, normalised: `en`, `fr_CA`. */
    public ?string $locale;

    /**
     * @param string  $title  what the file is, `SRD 5.2.1, English`
     * @param string  $url    where the publisher distributes it, an absolute http or https address
     * @param string  $sha256 the SHA-256 digest of the file, 64 lowercase hexadecimal digits
     * @param int     $bytes  its size
     * @param ?string $locale the language of its text, `en` or `fr_CA`
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $title,
        string $url,
        public string $sha256,
        public int $bytes,
        ?string $locale = null,
    ) {
        Text::check($title, 'The title of an upstream file');

        try {
            $this->url = Url::fromString($url);
        } catch (UrlException $urlException) {
            throw InvalidRecord::because(\sprintf('The address of the upstream file "%s" is not an absolute http or https address: %s', $title, $urlException->getMessage()));
        }

        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $sha256)) {
            throw InvalidRecord::because(\sprintf('The digest of the upstream file "%s" is not 64 lowercase hexadecimal digits.', $title));
        }

        if ($bytes < 1 || $bytes > self::MAX_BYTES) {
            throw InvalidRecord::because(\sprintf('The size of the upstream file "%s" is not between 1 and %d bytes.', $title, self::MAX_BYTES));
        }

        $this->locale = null === $locale ? null : Text::locale($locale);
    }
}
