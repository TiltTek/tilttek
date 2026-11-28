<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Import;

use Tilttek\GameCatalog\CollectionStatus;
use Tilttek\GameCatalog\Exception\InvalidCollectionFile;
use Tilttek\GameCatalog\IdentifierScheme;

/**
 * Which columns of a collection file the reader takes, by their header: the identifier of each game in an open
 * database, its statuses, the priority of a wish and the plays. Every other column is left unread — the name of a
 * game, a rating, a comment: the catalogue takes the identifier of a game and nothing else of the database it comes
 * from. Headers are matched without case and without the spaces around them.
 */
final readonly class Columns
{
    private const array DELIMITERS = [',', ';', "\t", '|'];

    public string $identifier;

    public ?string $schemeColumn;

    /** @var array<string, CollectionStatus> by header */
    public array $statuses;

    public ?string $priority;

    public ?string $plays;

    /**
     * @param ?IdentifierScheme               $scheme       the database of every identifier, or null to read it from a column
     * @param ?string                         $schemeColumn the column of the database of each row: `boardgamegeek`, `rpggeek` or `wikidata`
     * @param array<string, CollectionStatus> $statuses     by header: a cell of 1, "yes", "true" or "x" gives the status
     * @param ?string                         $priority     the priority of a wish, 1 to 5
     * @param ?string                         $plays        the games played
     *
     * @throws InvalidCollectionFile when the database is given both ways or neither, or the delimiter is not one of `,`, `;`, a tab or `|`
     */
    public function __construct(
        string $identifier,
        public ?IdentifierScheme $scheme = null,
        ?string $schemeColumn = null,
        array $statuses = [],
        ?string $priority = null,
        ?string $plays = null,
        public string $delimiter = ',',
    ) {
        if ($scheme instanceof IdentifierScheme !== (null === $schemeColumn)) {
            throw InvalidCollectionFile::because('The database of the identifiers is given once: for the whole file, or by a column.');
        }

        if (!\in_array($delimiter, self::DELIMITERS, true)) {
            throw InvalidCollectionFile::because('The delimiter of a collection file is ",", ";", a tab or "|".');
        }

        $this->identifier = self::header($identifier);
        $this->schemeColumn = null === $schemeColumn ? null : self::header($schemeColumn);
        $this->priority = null === $priority ? null : self::header($priority);
        $this->plays = null === $plays ? null : self::header($plays);
        $headers = [];

        foreach ($statuses as $header => $status) {
            $headers[self::header($header)] = $status;
        }

        $this->statuses = $headers;
    }

    /**
     * The columns of the collection a member exports from BoardGameGeek or RPGGeek, whose identifiers are all of the
     * given database.
     */
    public static function geekExport(IdentifierScheme $scheme): self
    {
        return new self('objectid', $scheme, statuses: [
            'own' => CollectionStatus::Owned,
            'prevowned' => CollectionStatus::PreviouslyOwned,
            'preordered' => CollectionStatus::Preordered,
            'fortrade' => CollectionStatus::ForTrade,
            'want' => CollectionStatus::WantInTrade,
            'wanttobuy' => CollectionStatus::WantToBuy,
            'wanttoplay' => CollectionStatus::WantToPlay,
            'wishlist' => CollectionStatus::Wishlist,
        ], priority: 'wishlistpriority', plays: 'numplays');
    }

    /**
     * A header as the reader compares it: without case and without the spaces around it.
     */
    public static function header(string $header): string
    {
        return strtolower(trim($header));
    }
}
