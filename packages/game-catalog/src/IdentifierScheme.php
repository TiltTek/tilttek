<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * An open database that knows a game. The catalogue keeps its identifier and builds the link to its page, and copies
 * nothing else from it: the data of BoardGameGeek and RPGGeek stay on their sites. Wikidata publishes its data under
 * CC0, which makes its identifier a free anchor of an entry.
 */
enum IdentifierScheme: string
{
    case BoardGameGeek = 'boardgamegeek';
    case RpgGeek = 'rpggeek';
    case Wikidata = 'wikidata';

    /**
     * Whether the value is an identifier of the database: digits for BoardGameGeek and RPGGeek, "Q" and digits for
     * Wikidata, without a leading zero, at most 12 digits.
     */
    public function accepts(string $value): bool
    {
        $pattern = self::Wikidata === $this ? '/^Q[1-9][0-9]{0,11}$/D' : '/^[1-9][0-9]{0,11}$/D';

        return 1 === preg_match($pattern, $value);
    }

    /**
     * The page of the game on the database.
     */
    public function link(string $value): string
    {
        return match ($this) {
            self::BoardGameGeek => 'https://boardgamegeek.com/boardgame/'.$value,
            self::RpgGeek => 'https://rpggeek.com/rpgitem/'.$value,
            self::Wikidata => 'https://www.wikidata.org/wiki/'.$value,
        };
    }
}
