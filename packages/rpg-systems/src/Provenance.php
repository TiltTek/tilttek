<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * Where a record comes from: the title of its source, who holds it, where it is published, its licence and the
 * attribution the licence asks a work to show. Every record of the reference carries one.
 */
final readonly class Provenance
{
    public ?Url $url;

    /**
     * @param string  $title       the title of the source, `System Reference Document 5.2`
     * @param string  $attribution the statement a work that shows the material displays; required by a licence that
     *                             asks for attribution, optional otherwise
     * @param ?string $author      who holds the source
     * @param ?string $url         where the source is published, an absolute http or https address
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $title,
        public Licence $licence,
        public string $attribution = '',
        public ?string $author = null,
        ?string $url = null,
    ) {
        Text::check($title, 'The title of a source');
        Text::check($attribution, \sprintf('The attribution of the source "%s"', $title), required: $licence->requiresAttribution());

        if (null !== $author) {
            Text::check($author, \sprintf('The author of the source "%s"', $title));
        }

        try {
            $this->url = null === $url ? null : Url::fromString($url);
        } catch (UrlException $urlException) {
            throw InvalidRecord::because(\sprintf('The address of the source "%s" is not an absolute http or https address: %s', $title, $urlException->getMessage()));
        }
    }

    /**
     * A key two equal provenances share, to show each source once.
     */
    public function identity(): string
    {
        return hash('xxh128', implode("\0", [$this->title, $this->licence->value, $this->attribution, $this->author ?? '', $this->url?->toString() ?? '']));
    }

    public function equals(self $other): bool
    {
        return $this->identity() === $other->identity();
    }
}
