<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\Internal\Identifier;

/**
 * One entry related to another: an expansion to its base game, a new edition to the old one, a translation to its
 * original. A link `IntegratesWith` reads the same from both entries.
 */
final readonly class Link
{
    public const int MAX_ORDINAL = 10_000;

    /**
     * @param ?int $ordinal where the entry stands among the links of its type to the same entry: the release order of
     *                      the expansions, 0 to 10 000
     *
     * @throws InvalidEntry when an identifier is not one, an entry is linked to itself, or the ordinal is out of bounds
     */
    public function __construct(public string $from, public LinkType $type, public string $to, public ?int $ordinal = null)
    {
        Identifier::check($from, 'an entry');
        Identifier::check($to, 'an entry');

        if ($from === $to) {
            throw InvalidEntry::because('An entry is not linked to itself.');
        }

        if (null !== $ordinal && ($ordinal < 0 || $ordinal > self::MAX_ORDINAL)) {
            throw InvalidEntry::because(\sprintf('The ordinal of a link is from 0 to %d.', self::MAX_ORDINAL));
        }
    }

    /**
     * What makes the link unique, whatever its ordinal: a symmetric link has the same key from both entries.
     */
    public function key(): string
    {
        $entries = [$this->from, $this->to];

        if ($this->type->isSymmetric()) {
            sort($entries);
        }

        return $entries[0].' '.$this->type->value.' '.$entries[1];
    }

    /**
     * The other entry of the link, seen from the given one.
     */
    public function other(string $entry): string
    {
        return $entry === $this->from ? $this->to : $this->from;
    }
}
