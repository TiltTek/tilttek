<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Store;

use Override;
use Tilttek\Bingo\BingoGame;
use Tilttek\Bingo\Card;
use Tilttek\Bingo\Contract\BingoStore;
use Tilttek\Bingo\Exception\BingoConflict;

/**
 * The games and their cards in memory, for tests and for a game that lives in one process. It keeps copies, so that a
 * game or a card changed and not saved leaves the stored one as it was.
 */
final class InMemoryBingoStore implements BingoStore
{
    /** @var array<string, BingoGame> */
    private array $games = [];

    /** @var array<string, array<int, Card>> by game, then serial */
    private array $cards = [];

    /** @var array<string, array<string, int>> the serial of each player's card, by game */
    private array $players = [];

    /** @var array<string, array<string, int>> the serial of the card of each fingerprint, by game */
    private array $fingerprints = [];

    #[Override]
    public function game(string $game): ?BingoGame
    {
        return isset($this->games[$game]) ? clone $this->games[$game] : null;
    }

    #[Override]
    public function addGame(BingoGame $game): void
    {
        if (isset($this->games[$game->id])) {
            throw BingoConflict::at('game', $game->id, 0, $this->games[$game->id]->version());
        }

        $this->games[$game->id] = clone $game;
    }

    #[Override]
    public function saveGame(BingoGame $game): void
    {
        $stored = $this->games[$game->id] ?? null;

        if ($stored?->version() !== $game->version() - 1) {
            throw BingoConflict::at('game', $game->id, $game->version() - 1, $stored?->version());
        }

        $this->games[$game->id] = clone $game;
    }

    #[Override]
    public function card(string $game, int $serial): ?Card
    {
        return isset($this->cards[$game][$serial]) ? clone $this->cards[$game][$serial] : null;
    }

    #[Override]
    public function cardOf(string $game, string $player): ?Card
    {
        $serial = $this->players[$game][$player] ?? null;

        return null === $serial ? null : $this->card($game, $serial);
    }

    #[Override]
    public function addCard(Card $card, string $fingerprint): bool
    {
        if (isset($this->cards[$card->game][$card->serial]) || (null !== $card->player && isset($this->players[$card->game][$card->player]))) {
            throw BingoConflict::at('card', \sprintf('%s/%d', $card->game, $card->serial), 0, 1);
        }

        if (isset($this->fingerprints[$card->game][$fingerprint])) {
            return false;
        }

        $this->cards[$card->game][$card->serial] = clone $card;

        if (null !== $card->player) {
            $this->players[$card->game][$card->player] = $card->serial;
        }

        $this->fingerprints[$card->game][$fingerprint] = $card->serial;

        return true;
    }

    #[Override]
    public function saveCard(Card $card): void
    {
        $stored = $this->cards[$card->game][$card->serial] ?? null;

        if ($stored?->version() !== $card->version() - 1) {
            throw BingoConflict::at('card', \sprintf('%s/%d', $card->game, $card->serial), $card->version() - 1, $stored?->version());
        }

        $this->cards[$card->game][$card->serial] = clone $card;
    }

    #[Override]
    public function openCardsOf(string $player, int $limit): array
    {
        $cards = [];

        foreach ($this->players as $game => $serials) {
            $serial = $serials[$player] ?? null;

            if (null === $serial || !$this->games[$game]->isOpen()) {
                continue;
            }

            $cards[] = clone $this->cards[$game][$serial];

            if (\count($cards) >= $limit) {
                break;
            }
        }

        return $cards;
    }
}
