<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Contract;

use Tilttek\Bingo\BingoGame;
use Tilttek\Bingo\Card;
use Tilttek\Bingo\Exception\BingoConflict;

/**
 * Where the games and their cards are kept. Every change of a game or a card stores its next version, and is refused
 * when the version stored is not the one it was made from: two gestures recorded at once both tick, one after the
 * other, by the retries of `Bingo`.
 */
interface BingoStore
{
    public function game(string $game): ?BingoGame;

    /**
     * Keeps a new game, at version 1.
     *
     * @throws BingoConflict when a game has its identifier already
     */
    public function addGame(BingoGame $game): void;

    /**
     * Keeps the game's next version.
     *
     * @throws BingoConflict when the version stored is not `$game->version() - 1`
     */
    public function saveGame(BingoGame $game): void;

    public function card(string $game, int $serial): ?Card;

    /** The card the player holds in the game, if any. */
    public function cardOf(string $game, string $player): ?Card;

    /**
     * Keeps a new card, at version 1, unless another card of the game has its fingerprint: no two cards of a game are
     * the same.
     *
     * @return bool false, and nothing kept, when another card of the game has the fingerprint
     *
     * @throws BingoConflict when the game has a card with its serial, or a card of its player, already; a printed card
     *                       has no player
     */
    public function addCard(Card $card, string $fingerprint): bool;

    /**
     * Keeps the card's next version.
     *
     * @throws BingoConflict when the version stored is not `$card->version() - 1`
     */
    public function saveCard(Card $card): void;

    /**
     * The cards the player holds in the games that are open, at most `$limit`; never a printed card.
     *
     * @return list<Card>
     */
    public function openCardsOf(string $player, int $limit): array;
}
