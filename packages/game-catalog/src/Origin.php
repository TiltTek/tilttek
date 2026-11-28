<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Internal\Identifier;

/**
 * Where an entry comes from: entered by hand, or sent by a source — a port that feeds the catalogue — under the key
 * the source gives it, so that the source sending it again updates it rather than adding it twice.
 */
final readonly class Origin
{
    private function __construct(public ?string $source, public ?string $key)
    {
    }

    public static function manual(): self
    {
        return new self(null, null);
    }

    /**
     * @throws Exception\InvalidEntry when the source or the key is not an identifier
     */
    public static function source(string $source, string $key): self
    {
        return new self(Identifier::check($source, 'a source'), Identifier::check($key, 'an entry of a source'));
    }

    public function isManual(): bool
    {
        return null === $this->source;
    }

    /**
     * The source and its key, as `books/b-12`; `null` for an entry entered by hand.
     */
    public function key(): ?string
    {
        return null === $this->source ? null : $this->source.'/'.$this->key;
    }
}
