<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use SensitiveParameter;
use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * Draws the layout of a card from a secret of the server alone: the seed of card `n` of a game is the HMAC-SHA256 of
 * the game and the serial under the secret, and a seeded generator picks and shuffles the squares. The same secret
 * draws the same card again, so that a printed card is checked by its serial; without the secret, no player can tell
 * the cards of others or choose their own. No seed comes from the browser.
 */
final readonly class CardDealer
{
    /** The shortest secret, in bytes. */
    public const int SHORTEST_SECRET = 32;

    public function __construct(
        #[SensitiveParameter]
        private string $secret,
    ) {
        if (\strlen($secret) < self::SHORTEST_SECRET) {
            throw InvalidBingo::because(\sprintf('The secret that draws the cards is at least %d bytes.', self::SHORTEST_SECRET));
        }
    }

    /**
     * The layout of card `$serial` of the game: the square of each cell, row by row, null for the free centre.
     *
     * @return list<string|null>
     */
    public function layout(BingoGame $game, int $serial): array
    {
        $seed = hash_hmac('sha256', \sprintf("tilttek-bingo\0%s\0%d", $game->id, $serial), $this->secret, true);
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));
        $squares = $game->pool->squares;
        $picked = $randomizer->shuffleArray($randomizer->pickArrayKeys($squares, $game->grid->squares()));
        $layout = array_map(static fn (int $key): string => $squares[$key]->id, $picked);
        $centre = $game->grid->centre();

        if (null !== $centre) {
            array_splice($layout, $centre, 0, [null]);
        }

        return $layout;
    }

    /**
     * The label a printed card shows: its serial and the check code the secret draws for it, under a key of its own,
     * so that the code tells nothing of the layout.
     */
    public function label(BingoGame $game, int $serial): SerialLabel
    {
        $mac = hash_hmac('sha256', \sprintf("tilttek-bingo-label\0%s\0%d", $game->id, $serial), $this->secret, true);

        return SerialLabel::of($serial, SerialLabel::encode(substr($mac, 0, 5)));
    }

    /**
     * What tells two layouts apart, whatever the game: the same layout gives the same fingerprint.
     *
     * @param list<string|null> $layout
     */
    public static function fingerprint(array $layout): string
    {
        return hash('sha256', implode("\n", $layout));
    }
}
