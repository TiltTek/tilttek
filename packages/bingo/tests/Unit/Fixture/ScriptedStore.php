<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Unit\Fixture;

use Override;
use Tilttek\Bingo\BingoGame;
use Tilttek\Bingo\Card;
use Tilttek\Bingo\Contract\BingoStore;
use Tilttek\Bingo\Exception\BingoConflict;
use Tilttek\Bingo\Store\InMemoryBingoStore;

/**
 * A store of tests: it refuses the next saves of games or cards as if another request had saved first, finds the
 * next fingerprints taken, or lets another request deal a card in between.
 */
final class ScriptedStore implements BingoStore
{
    public int $gameConflicts = 0;

    public int $cardConflicts = 0;

    public int $takenFingerprints = 0;

    public ?Card $dealtInBetween = null;

    public int $gameSaves = 0;

    public int $cardSaves = 0;

    public int $cardReads = 0;

    public int $cardOfReads = 0;

    public function __construct(
        public readonly InMemoryBingoStore $inner = new InMemoryBingoStore(),
    ) {
    }

    #[Override]
    public function game(string $game): ?BingoGame
    {
        return $this->inner->game($game);
    }

    #[Override]
    public function addGame(BingoGame $game): void
    {
        $this->inner->addGame($game);
    }

    #[Override]
    public function saveGame(BingoGame $game): void
    {
        ++$this->gameSaves;

        if ($this->gameConflicts > 0) {
            --$this->gameConflicts;

            throw BingoConflict::at('game', $game->id, $game->version() - 1, $game->version());
        }

        $this->inner->saveGame($game);
    }

    #[Override]
    public function card(string $game, int $serial): ?Card
    {
        ++$this->cardReads;

        return $this->inner->card($game, $serial);
    }

    #[Override]
    public function cardOf(string $game, string $player): ?Card
    {
        ++$this->cardOfReads;

        return $this->inner->cardOf($game, $player);
    }

    #[Override]
    public function addCard(Card $card, string $fingerprint): bool
    {
        if ($this->dealtInBetween instanceof Card) {
            $this->inner->addCard($this->dealtInBetween, 'in-between');
            $this->dealtInBetween = null;
        }

        if ($this->takenFingerprints > 0) {
            --$this->takenFingerprints;

            return false;
        }

        return $this->inner->addCard($card, $fingerprint);
    }

    #[Override]
    public function saveCard(Card $card): void
    {
        ++$this->cardSaves;

        if ($this->cardConflicts > 0) {
            --$this->cardConflicts;

            throw BingoConflict::at('card', $card->game, $card->version() - 1, $card->version());
        }

        $this->inner->saveCard($card);
    }

    #[Override]
    public function openCardsOf(string $player, int $limit): array
    {
        return $this->inner->openCardsOf($player, $limit);
    }
}
